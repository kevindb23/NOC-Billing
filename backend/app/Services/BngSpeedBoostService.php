<?php

namespace App\Services;

use App\Models\BngRadiusServer;
use App\Models\Plan;
use App\Models\Subscription;
use PDO;
use RuntimeException;

class BngSpeedBoostService
{
    /** @param callable|null $connector Returns a PDO connection for tests or custom database drivers. */
    public function __construct(private $connector = null) {}

    public static function filterIdValue(float|int $downloadMbps, float|int $uploadMbps): string
    {
        return self::mbpsToKbps($downloadMbps).'/'.self::mbpsToKbps($uploadMbps);
    }

    public static function mbpsToKbps(float|int|string $mbps): int
    {
        $value = round((float) $mbps * 1000);
        if ($value < 1 || $value > 4294967295) {
            throw new RuntimeException('Speed must be greater than zero and within the supported range.');
        }

        return (int) $value;
    }

    /** @return list<string> */
    public function usernamesForPlan(Plan $plan): array
    {
        return Subscription::query()
            ->where('status', 'active')
            ->whereHas('planVersion', fn ($query) => $query->where('plan_id', $plan->id))
            ->whereHas('service', function ($query): void {
                $query->where('status', 'active')
                    ->whereHas('customer', fn ($customer) => $customer->whereNotNull('ppp_username')->where('ppp_username', '<>', '')->where('status', 'active'));
            })
            ->with('service.customer')
            ->get()
            ->map(fn (Subscription $subscription) => $subscription->service?->customer?->ppp_username)
            ->filter(fn ($username) => is_string($username) && trim($username) !== '')
            ->map(fn (string $username) => trim($username))
            ->unique()
            ->values()
            ->all();
    }

    public function applyToUsers(BngRadiusServer $server, array $usernames, string $rateValue): int
    {
        return $this->replaceManagedRows($server, [], $usernames, $rateValue);
    }

    /** @param list<array{username:string,value:string}> $oldRows */
    public function replaceManagedRows(BngRadiusServer $server, array $oldRows, array $usernames, string $rateValue): int
    {
        if ($usernames === []) {
            throw new RuntimeException('No active subscribers with PPP usernames were found for the selected Plan.');
        }

        $pdo = $this->connection($server);
        $pdo->beginTransaction();

        try {
            $removeManaged = $pdo->prepare("DELETE FROM radreply WHERE username = :username AND attribute = 'Filter-Id' AND value = :value");
            $delete = $pdo->prepare("DELETE FROM radreply WHERE username = :username AND attribute = 'Filter-Id'");
            $insert = $pdo->prepare("INSERT INTO radreply (username, attribute, op, value) VALUES (:username, 'Filter-Id', ':=', :value)");

            foreach ($oldRows as $row) $removeManaged->execute(['username' => $row['username'], 'value' => $row['value']]);
            foreach ($usernames as $username) {
                $delete->execute(['username' => $username]);
                $insert->execute(['username' => $username, 'value' => $rateValue]);
            }

            $pdo->commit();
        } catch (\Throwable $exception) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            throw new RuntimeException('Unable to synchronize Speed Boost rows to the RADIUS database.', 0, $exception);
        }

        return count($usernames);
    }

    /** @param list<array{username:string,value:string}> $managedRows */
    public function removeManagedRows(BngRadiusServer $server, array $managedRows): void
    {
        if ($managedRows === []) return;

        $pdo = $this->connection($server);
        $pdo->beginTransaction();

        try {
            $delete = $pdo->prepare("DELETE FROM radreply WHERE username = :username AND attribute = 'Filter-Id' AND value = :value");
            foreach ($managedRows as $row) $delete->execute(['username' => $row['username'], 'value' => $row['value']]);
            $pdo->commit();
        } catch (\Throwable $exception) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            throw new RuntimeException('Unable to remove managed Speed Boost rows from the RADIUS database.', 0, $exception);
        }
    }

    private function connection(BngRadiusServer $server): PDO
    {
        if ($this->connector) return ($this->connector)($server);

        $database = $server->database_name;
        if (!$database || !$server->database_username || !$server->database_password) {
            throw new RuntimeException('The selected RADIUS server is missing database credentials.');
        }

        try {
            return new PDO(
                'mysql:host='.$server->server_address.';port=3306;dbname='.rawurlencode($database),
                $server->database_username,
                $server->database_password,
                [PDO::ATTR_TIMEOUT => 5, PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
            );
        } catch (\Throwable $exception) {
            throw new RuntimeException('Unable to connect to the RADIUS database.', 0, $exception);
        }
    }
}
