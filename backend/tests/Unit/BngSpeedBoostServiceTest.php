<?php

namespace Tests\Unit;

use App\Models\BngRadiusServer;
use App\Services\BngSpeedBoostService;
use PDO;
use PHPUnit\Framework\TestCase;

class BngSpeedBoostServiceTest extends TestCase
{
    public function test_it_converts_mbps_to_accel_ppp_filter_id(): void
    {
        $this->assertSame('20000/20000', BngSpeedBoostService::filterIdValue(20, 20));
        $this->assertSame('12500/8000', BngSpeedBoostService::filterIdValue(12.5, 8));
    }

    public function test_it_replaces_filter_id_rows_for_each_user(): void
    {
        $pdo = $this->radiusDatabase();
        $pdo->exec("INSERT INTO radreply (username, attribute, op, value) VALUES ('subscriber01', 'Filter-Id', ':=', '1000/1000'), ('subscriber01', 'Service-Type', ':=', 'Framed-User')");

        $service = new BngSpeedBoostService(fn () => $pdo);
        $server = $this->radiusServer();
        $sync = $service->applyToUsers($server, ['subscriber01', 'subscriber02'], '20000/20000');

        $rows = $pdo->query("SELECT username, attribute, op, value FROM radreply ORDER BY username, attribute")->fetchAll(PDO::FETCH_ASSOC);

        $this->assertSame(2, $sync);
        $this->assertSame([
            ['username' => 'subscriber01', 'attribute' => 'Filter-Id', 'op' => ':=', 'value' => '20000/20000'],
            ['username' => 'subscriber01', 'attribute' => 'Service-Type', 'op' => ':=', 'value' => 'Framed-User'],
            ['username' => 'subscriber02', 'attribute' => 'Filter-Id', 'op' => ':=', 'value' => '20000/20000'],
        ], $rows);
    }

    public function test_it_removes_only_managed_filter_id_rows(): void
    {
        $pdo = $this->radiusDatabase();
        $pdo->exec("INSERT INTO radreply (username, attribute, op, value) VALUES ('subscriber01', 'Filter-Id', ':=', '20000/20000'), ('subscriber01', 'Service-Type', ':=', 'Framed-User')");

        $service = new BngSpeedBoostService(fn () => $pdo);
        $server = $this->radiusServer();
        $service->removeManagedRows($server, [
            ['username' => 'subscriber01', 'value' => '20000/20000'],
        ]);

        $rows = $pdo->query("SELECT attribute, value FROM radreply")->fetchAll(PDO::FETCH_ASSOC);

        $this->assertSame([
            ['attribute' => 'Service-Type', 'value' => 'Framed-User'],
        ], $rows);
    }

    public function test_it_does_not_report_success_when_no_users_are_resolved(): void
    {
        $service = new BngSpeedBoostService(fn () => $this->radiusDatabase());

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('No active subscribers with PPP usernames were found');

        $service->applyToUsers($this->radiusServer(), [], '20000/20000');
    }

    private function radiusDatabase(): PDO
    {
        $pdo = new PDO('sqlite::memory:');
        $pdo->exec('CREATE TABLE radreply (id INTEGER PRIMARY KEY AUTOINCREMENT, username VARCHAR(64), attribute VARCHAR(64), op VARCHAR(2), value VARCHAR(255))');

        return $pdo;
    }

    private function radiusServer(): BngRadiusServer
    {
        $server = new BngRadiusServer();
        $server->setRawAttributes([
            'server_address' => '127.0.0.1',
            'database_name' => 'radius',
            'database_username' => 'radius',
            'database_password' => 'secret',
        ]);

        return $server;
    }
}
