<?php

namespace App\Services;

use App\Models\BngRadiusServer;
use App\Models\Customer;
use Closure;
use PDO;
use RuntimeException;
use Throwable;

class RadiusSubscriberSyncService
{
    /**
     * @param (callable(BngRadiusServer): PDO)|null $connector
     */
    public function __construct(private readonly ?Closure $connector = null)
    {
    }

    /** @return array{ready:bool,enabled_server_count:int,servers:array<int,array{id:int,name:string,status:string}>} */
    public static function readiness(): array
    {
        $servers = BngRadiusServer::query()
            ->where('sync_subscribers', true)
            ->orderBy('name')
            ->get(['id', 'name', 'status']);

        return [
            'ready' => $servers->isNotEmpty(),
            'enabled_server_count' => $servers->count(),
            'servers' => $servers->map(fn (BngRadiusServer $server): array => [
                'id' => $server->id,
                'name' => $server->name,
                'status' => $server->status,
            ])->values()->all(),
        ];
    }

    public function assertReady(): void
    {
        if (self::readiness()['ready']) {
            return;
        }

        throw \Illuminate\Validation\ValidationException::withMessages([
            'radius' => 'Subscriber management is unavailable until at least one RADIUS server has subscriber synchronization enabled.',
        ]);
    }

    /**
     * Backfill every local subscriber with a PPP username into one RADIUS database.
     * The external table is intentionally accessed with prepared statements because
     * the database credentials are supplied by a user-managed RADIUS server.
     *
     * @return array{synced:int}
     */
    public function syncServer(BngRadiusServer $server): array
    {
        $pdo = $this->connect($server);
        $statement = $this->upsertStatement($pdo);
        $count = 0;

        Customer::query()
            ->whereNotNull('ppp_username')
            ->where('ppp_username', '!=', '')
            ->with(['subscriberServices.subscriptions.planVersion.plan'])
            ->orderBy('id')
            ->chunkById(100, function ($customers) use ($statement, $pdo, &$count): void {
                foreach ($customers as $customer) {
                    $this->writeCustomer($statement, $pdo, $customer);
                    $count++;
                }
            });

        return ['synced' => $count];
    }

    /**
     * Push one local subscriber to every RADIUS server that has synchronization enabled.
     * A RADIUS outage must not roll back a local subscriber save; the failure is logged
     * and the next edit, subscription change, or backfill can retry it.
     */
    public function syncEnabledServersForCustomer(Customer $customer, ?string $previousUsername = null): void
    {
        $customer = $customer->fresh(['subscriberServices.subscriptions.planVersion.plan']);
        if (! $customer) {
            return;
        }

        BngRadiusServer::query()
            ->where('sync_subscribers', true)
            ->each(function (BngRadiusServer $server) use ($customer, $previousUsername): void {
                try {
                    $pdo = $this->connect($server);
                    if (filled($previousUsername) && $previousUsername !== (string) $customer->ppp_username) {
                        $this->removeAuthentication($pdo, $previousUsername);
                    }
                    $statement = $this->upsertStatement($pdo);
                    if (filled($customer->ppp_username)) {
                        $this->writeCustomer($statement, $pdo, $customer);
                    }
                } catch (Throwable $exception) {
                    throw new RuntimeException(
                        "Subscriber synchronization failed on RADIUS server '{$server->name}'. Verify its database connection and schema.",
                        0,
                        $exception,
                    );
                }
        });
    }

    /**
     * Remove a permanently deleted subscriber from every enabled RADIUS server.
     * This is deliberately separate from authentication cleanup because the
     * business subscriber row must also be removed from isp_subscribers.
     */
    public function removeCustomerFromEnabledServers(Customer $customer): void
    {
        $username = trim((string) $customer->ppp_username);
        if ($username === '') {
            return;
        }

        BngRadiusServer::query()
            ->where('sync_subscribers', true)
            ->each(function (BngRadiusServer $server) use ($username): void {
                try {
                    $pdo = $this->connect($server);

                    if ($this->tableExists($pdo, 'radcheck')) {
                        $statement = $pdo->prepare("DELETE FROM radcheck WHERE username = :username");
                        $statement->execute(['username' => $username]);
                    }

                    if ($this->tableExists($pdo, 'isp_subscribers')) {
                        $statement = $pdo->prepare('DELETE FROM isp_subscribers WHERE username = :username');
                        $statement->execute(['username' => $username]);
                    }
                } catch (Throwable $exception) {
                    throw new RuntimeException(
                        "Subscriber cleanup failed on RADIUS server '{$server->name}'. Verify its database connection and schema.",
                        0,
                        $exception,
                    );
                }
            });
    }

    /** @return array{username:string,status:string,expires_at:string|null,plan:string} */
    public static function subscriberRow(Customer $customer): array
    {
        $subscriptions = $customer->subscriberServices
            ->flatMap(fn ($service) => $service->subscriptions)
            ->sortByDesc('id');
        $subscription = $subscriptions->firstWhere('status', 'active') ?: $subscriptions->first();
        // A newly created subscriber may not have a billing service yet. The
        // customer status is still sufficient for the initial RADIUS sync;
        // service/subscription state only suspends an already provisioned user.
        $hasServices = $customer->subscriberServices->isNotEmpty();
        $serviceIsActive = ! $hasServices
            || $subscription?->service?->status === 'active'
            || $customer->subscriberServices->contains(fn ($service) => $service->status === 'active');
        $hasSubscriptions = $subscriptions->isNotEmpty();
        $subscriptionIsActive = ! $hasSubscriptions || $subscription?->status === 'active';

        return [
            'username' => (string) $customer->ppp_username,
            'status' => $customer->status === 'active' && $subscriptionIsActive && $serviceIsActive ? 'ACTIVE' : 'SUSPENDED',
            'expires_at' => $subscription?->ends_on?->toDateTimeString(),
            'plan' => mb_substr((string) ($subscription?->plan_name_snapshot ?: $subscription?->planVersion?->plan?->name ?: 'unassigned'), 0, 32),
        ];
    }

    private function writeCustomer($statement, PDO $pdo, Customer $customer): void
    {
        $statement->execute(self::subscriberRow($customer));
        $this->syncAuthentication($pdo, $customer);
    }

    private function syncAuthentication(PDO $pdo, Customer $customer): void
    {
        $username = (string) $customer->ppp_username;
        $password = (string) $customer->ppp_password;
        if ($username === '') {
            return;
        }

        if ($this->tableExists($pdo, 'radcheck')) {
            $delete = $pdo->prepare("DELETE FROM radcheck WHERE username = :username AND attribute = 'Cleartext-Password'");
            $delete->execute(['username' => $username]);

            if (self::subscriberRow($customer)['status'] !== 'ACTIVE' || $password === '') {
                return;
            }

            $insert = $pdo->prepare("INSERT INTO radcheck (username, attribute, op, value) VALUES (:username, 'Cleartext-Password', ':=', :value)");
            $insert->execute(['username' => $username, 'value' => $password]);
            return;
        }

        if ($password === '') {
            throw new RuntimeException('The RADIUS database must contain a radcheck table or an isp_subscribers password column for PPPoE authentication.');
        }

        foreach (['password', 'ppp_password'] as $column) {
            if (! $this->columnExists($pdo, 'isp_subscribers', $column)) {
                continue;
            }

            $statement = $pdo->prepare("UPDATE isp_subscribers SET {$column} = :password WHERE username = :username");
            $statement->execute(['password' => $password, 'username' => $username]);
            return;
        }

        throw new RuntimeException('The RADIUS database must contain a radcheck table or an isp_subscribers password column for PPPoE authentication.');
    }

    private function removeAuthentication(PDO $pdo, string $username): void
    {
        if ($this->tableExists($pdo, 'radcheck')) {
            $statement = $pdo->prepare("DELETE FROM radcheck WHERE username = :username AND attribute = 'Cleartext-Password'");
            $statement->execute(['username' => $username]);
            return;
        }

        foreach (['password', 'ppp_password'] as $column) {
            if (! $this->columnExists($pdo, 'isp_subscribers', $column)) {
                continue;
            }

            $statement = $pdo->prepare("UPDATE isp_subscribers SET {$column} = '' WHERE username = :username");
            $statement->execute(['username' => $username]);
            return;
        }

        throw new RuntimeException('The RADIUS database must contain a radcheck table or an isp_subscribers password column for PPPoE authentication.');
    }

    private function tableExists(PDO $pdo, string $table): bool
    {
        if ($pdo->getAttribute(PDO::ATTR_DRIVER_NAME) === 'sqlite') {
            $statement = $pdo->prepare("SELECT 1 FROM sqlite_master WHERE type = 'table' AND name = :table LIMIT 1");
            $statement->execute(['table' => $table]);
            return (bool) $statement->fetchColumn();
        }

        $statement = $pdo->prepare("SELECT 1 FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = :table LIMIT 1");
        $statement->execute(['table' => $table]);
        return (bool) $statement->fetchColumn();
    }

    private function columnExists(PDO $pdo, string $table, string $column): bool
    {
        if ($pdo->getAttribute(PDO::ATTR_DRIVER_NAME) === 'sqlite') {
            $quotedTable = str_replace('"', '""', $table);
            $statement = $pdo->query("PRAGMA table_info(\"{$quotedTable}\")");
            foreach ($statement->fetchAll(PDO::FETCH_ASSOC) as $definition) {
                if ((string) ($definition['name'] ?? '') === $column) {
                    return true;
                }
            }
            return false;
        }

        $statement = $pdo->prepare("SELECT 1 FROM information_schema.columns WHERE table_schema = DATABASE() AND table_name = :table AND column_name = :column LIMIT 1");
        $statement->execute(['table' => $table, 'column' => $column]);
        return (bool) $statement->fetchColumn();
    }

    private function upsertStatement(PDO $pdo): mixed
    {
        $sql = $pdo->getAttribute(PDO::ATTR_DRIVER_NAME) === 'sqlite'
            ? "INSERT INTO isp_subscribers (username, status, expires_at, plan)
               VALUES (:username, :status, :expires_at, :plan)
               ON CONFLICT(username) DO UPDATE SET
                   status = excluded.status,
                   expires_at = excluded.expires_at,
                   plan = excluded.plan"
            : "INSERT INTO isp_subscribers (username, status, expires_at, plan)
               VALUES (:username, :status, :expires_at, :plan)
               ON DUPLICATE KEY UPDATE
                   status = VALUES(status),
                   expires_at = VALUES(expires_at),
                   plan = VALUES(plan)";

        return $pdo->prepare($sql);
    }

    private function connect(BngRadiusServer $server): PDO
    {
        if ($this->connector) {
            return ($this->connector)($server);
        }

        $dsn = sprintf(
            'mysql:host=%s;port=3306;dbname=%s;charset=utf8mb4',
            $server->server_address,
            $server->database_name
        );

        return new PDO($dsn, $server->database_username, $server->database_password, [
            PDO::ATTR_TIMEOUT => 5,
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_EMULATE_PREPARES => false,
        ]);
    }
}
