<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class SingleInstallationMigrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_runtime_tables_do_not_retain_organization_columns(): void
    {
        foreach ([
            'customers', 'billing_cycles', 'plans', 'plan_versions', 'billing_accounts',
            'subscriber_services', 'subscriptions', 'invoices', 'invoice_items', 'payments',
            'payment_allocations', 'credits', 'adjustments', 'balance_transactions',
            'roles', 'role_assignments', 'audit_logs', 'personal_access_tokens',
            'organization_brandings', 'organization_email_settings',
            'organization_notification_settings', 'organization_paymongo_settings',
            'organization_gcash_settings', 'organization_billing_settings',
            'gcash_manual_payments', 'billing_statements',
        ] as $table) {
            $this->assertNotContains('organization_id', Schema::getColumnListing($table), $table.' still has organization_id');
        }

        $this->assertFalse(Schema::hasTable('organizations'));
        $this->assertFalse(Schema::hasTable('organization_user'));
    }

    public function test_flattening_restores_global_uniqueness_and_lookup_indexes(): void
    {
        $this->assertUniqueIndex('roles', ['name']);
        $this->assertUniqueIndex('role_assignments', ['user_id', 'role_id']);
        $this->assertUniqueIndex('organization_email_settings', ['user_id']);
        $this->assertUniqueIndex('organization_notification_settings', ['user_id']);
        $this->assertUniqueIndex('organization_paymongo_settings', ['user_id']);
        $this->assertUniqueIndex('organization_gcash_settings', ['user_id']);
        $this->assertUniqueIndex('invoices', [
            'billing_account_id', 'billing_period_start', 'billing_period_end',
        ]);

        $this->assertIndex('customers', ['legal_name']);
        $this->assertIndex('customers', ['email']);
        $this->assertIndex('billing_accounts', ['customer_id']);
        $this->assertIndex('subscriber_services', ['customer_id']);
        $this->assertIndex('subscriber_services', ['billing_account_id']);
        $this->assertIndex('subscriptions', ['subscriber_service_id']);
        $this->assertIndex('subscriptions', ['billing_account_id']);
        $this->assertIndex('subscriptions', ['plan_version_id']);
        $this->assertIndex('invoice_items', ['invoice_id']);
        $this->assertIndex('invoice_items', ['subscription_id']);
        $this->assertIndex('gcash_manual_payments', ['invoice_id']);
        $this->assertIndex('gcash_manual_payments', ['statement_id']);
    }

    public function test_flattening_rejects_conflicting_global_roles_before_schema_changes(): void
    {
        $roleUniqueIndex = collect(Schema::getIndexes('roles'))
            ->first(fn (array $index): bool => ($index['unique'] ?? false) && ($index['columns'] ?? []) === ['name']);
        if ($roleUniqueIndex) {
            Schema::table('roles', fn ($table) => $table->dropIndex($roleUniqueIndex['name']));
        }

        DB::table('roles')->insert([
            ['name' => 'Administrator', 'guard_name' => 'api'],
            ['name' => 'Administrator', 'guard_name' => 'api'],
        ]);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Cannot flatten roles');

        $this->runSingleInstallationMigration();
    }

    /** @param list<string> $columns */
    private function assertUniqueIndex(string $table, array $columns): void
    {
        $this->assertTrue($this->hasIndex($table, $columns, true), $table.' is missing unique index on '.implode(', ', $columns));
    }

    /** @param list<string> $columns */
    private function assertIndex(string $table, array $columns): void
    {
        $this->assertTrue($this->hasIndex($table, $columns), $table.' is missing index on '.implode(', ', $columns));
    }

    /** @param list<string> $columns */
    private function hasIndex(string $table, array $columns, bool $unique = false): bool
    {
        foreach (Schema::getIndexes($table) as $index) {
            if (($index['unique'] ?? false) === $unique && ($index['columns'] ?? []) === $columns) {
                return true;
            }
        }

        return false;
    }

    private function runSingleInstallationMigration(): void
    {
        $migration = require database_path('migrations/2026_09_13_000018_remove_organization_scoping.php');
        $migration->up();
    }
}
