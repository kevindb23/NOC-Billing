<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\Router;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class RouterApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_router_persistence_contract_is_installation_wide_and_relational(): void
    {
        $this->assertTrue(Schema::hasTable('routers'));
        $this->assertNotContains('organization_id', Schema::getColumnListing('routers'));
        $this->assertContains('created_by', Schema::getColumnListing('routers'));

        $createdBy = collect(Schema::getColumns('routers'))->firstWhere('name', 'created_by');
        $this->assertTrue($createdBy['nullable']);
        $this->assertTrue(collect(Schema::getForeignKeys('routers'))->contains(
            fn (array $foreignKey): bool => $foreignKey['columns'] === ['created_by']
                && $foreignKey['foreign_table'] === 'users',
        ));

        $indexes = Schema::getIndexes('routers');
        $uniqueIndexes = collect($indexes)
            ->filter(fn (array $index): bool => $index['unique'] ?? false)
            ->map(fn (array $index): array => $index['columns'])
            ->values()
            ->all();

        $this->assertContains(['public_id'], $uniqueIndexes);
        $this->assertContains(['name'], $uniqueIndexes);

        $indexedColumns = collect($indexes)
            ->flatMap(fn (array $index): array => $index['columns'])
            ->unique()
            ->values()
            ->all();
        $this->assertContains('vendor', $indexedColumns);
        $this->assertContains('driver', $indexedColumns);
        $this->assertContains('status', $indexedColumns);
        $this->assertContains('management_ip', $indexedColumns);
        $this->assertContains('last_contact_at', $indexedColumns);

        $user = User::factory()->create();
        $router = Router::create([
            'name' => 'Core MikroTik',
            'hostname' => 'core-router.example.test',
            'management_ip' => '192.0.2.10',
            'vendor' => 'mikrotik',
            'model' => 'CCR2004',
            'software_version' => '7.15',
            'serial_number' => 'MT-123',
            'driver' => 'mikrotik_router',
            'preferred_transport' => 'api',
            'status' => 'active',
            'capabilities' => ['system_info' => true],
            'last_contact_at' => '2026-09-13 12:00:00',
            'last_synchronized_at' => '2026-09-13 12:01:00',
            'notes' => 'Core router',
            'metadata' => ['site' => 'main'],
        ]);

        $this->assertNotEmpty($router->public_id);
        $this->assertNull($router->created_by);
        $this->assertIsArray($router->capabilities);
        $this->assertIsArray($router->metadata);
        $this->assertInstanceOf(Carbon::class, $router->last_contact_at);
        $this->assertInstanceOf(Carbon::class, $router->last_synchronized_at);

        $router->created_by = $user->id;
        $router->save();

        $this->assertTrue($router->creator->is($user));

        AuditLog::create([
            'actor_user_id' => $user->id,
            'action' => 'router.created',
            'auditable_type' => Router::class,
            'auditable_id' => $router->id,
        ]);

        $this->assertTrue($router->auditLogs()->exists());

        $router->delete();

        $this->assertInstanceOf(Carbon::class, $router->deleted_at);
    }
}
