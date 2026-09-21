<?php

namespace App\Services;

use App\Models\BngRadiusServer;
use App\Models\Customer;
use Closure;
use Illuminate\Support\Facades\Log;
use PDO;
use Throwable;

class RadiusSubscriberSyncService
{
    /**
     * @param (callable(BngRadiusServer): PDO)|null $connector
     */
    public function __construct(private readonly ?Closure $connector = null)
    {
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
            ->chunkById(100, function ($customers) use ($statement, &$count): void {
                foreach ($customers as $customer) {
                    $this->writeCustomer($statement, $customer);
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
    public function syncEnabledServersForCustomer(Customer $customer): void
    {
        $customer = $customer->fresh(['subscriberServices.subscriptions.planVersion.plan']);
        if (! $customer || blank($customer->ppp_username)) {
            return;
        }

        BngRadiusServer::query()
            ->where('sync_subscribers', true)
            ->each(function (BngRadiusServer $server) use ($customer): void {
                try {
                    $statement = $this->upsertStatement($this->connect($server));
                    $this->writeCustomer($statement, $customer);
                } catch (Throwable $exception) {
                    Log::warning('Unable to sync subscriber to RADIUS database.', [
                        'radius_server_id' => $server->id,
                        'customer_id' => $customer->id,
                        'exception' => $exception,
                    ]);
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
        $serviceIsActive = $subscription?->service?->status === 'active'
            || $customer->subscriberServices->contains(fn ($service) => $service->status === 'active');

        return [
            'username' => (string) $customer->ppp_username,
            'status' => $customer->status === 'active' && $subscription?->status === 'active' && $serviceIsActive ? 'ACTIVE' : 'SUSPENDED',
            'expires_at' => $subscription?->ends_on?->toDateTimeString(),
            'plan' => mb_substr((string) ($subscription?->plan_name_snapshot ?: $subscription?->planVersion?->plan?->name ?: 'unassigned'), 0, 32),
        ];
    }

    private function writeCustomer($statement, Customer $customer): void
    {
        $statement->execute(self::subscriberRow($customer));
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
