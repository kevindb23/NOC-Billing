<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private array $tables = [
        'roles', 'role_assignments', 'customers', 'billing_cycles', 'plans', 'plan_versions',
        'billing_accounts', 'subscriber_services', 'subscriptions', 'invoices', 'invoice_items',
        'payments', 'payment_allocations', 'credits', 'adjustments', 'balance_transactions',
        'audit_logs', 'organization_brandings', 'organization_email_settings',
        'organization_notification_settings', 'organization_paymongo_settings',
        'organization_gcash_settings', 'organization_billing_settings', 'gcash_manual_payments',
        'billing_statements', 'personal_access_tokens',
    ];

    public function up(): void
    {
        $this->assertSingleInstallation();

        foreach ($this->tables as $table) {
            if (! Schema::hasTable($table) || ! Schema::hasColumn($table, 'organization_id')) {
                continue;
            }

            $this->dropOrganizationIndexesAndForeignKeys($table);

            Schema::table($table, function (Blueprint $blueprint): void {
                $blueprint->dropColumn('organization_id');
            });
        }

        if (Schema::hasTable('organization_user')) {
            Schema::drop('organization_user');
        }

        if (Schema::hasTable('organizations')) {
            Schema::drop('organizations');
        }

        $this->addGlobalIndexes();
    }

    public function down(): void
    {
        throw new RuntimeException('The single-installation migration is irreversible because organization ownership was intentionally removed. Restore the database backup instead.');
    }

    private function assertSingleInstallation(): void
    {
        if (Schema::hasTable('organizations') && DB::table('organizations')->count() > 1) {
            throw new RuntimeException('Cannot remove organization scoping while more than one organization exists. Consolidate the data and retry.');
        }

        foreach ([
            'organization_brandings', 'organization_billing_settings',
        ] as $table) {
            if (Schema::hasTable($table) && DB::table($table)->count() > 1) {
                throw new RuntimeException("Cannot flatten {$table}: more than one installation-wide record exists.");
            }
        }

        $this->assertNoFlattenedDuplicates('roles', ['name']);
        $this->assertNoFlattenedDuplicates('role_assignments', ['user_id', 'role_id']);
        $this->assertNoFlattenedDuplicates('plan_versions', ['plan_id', 'version']);
        $this->assertNoFlattenedDuplicates('invoices', [
            'billing_account_id', 'billing_period_start', 'billing_period_end',
        ]);
        $this->assertNoFlattenedDuplicates('payment_allocations', ['payment_id', 'invoice_id']);
        $this->assertNoFlattenedDuplicates('organization_email_settings', ['user_id']);
        $this->assertNoFlattenedDuplicates('organization_notification_settings', ['user_id']);
        $this->assertNoFlattenedDuplicates('organization_paymongo_settings', ['user_id']);
        $this->assertNoFlattenedDuplicates('organization_gcash_settings', ['user_id']);
        $this->assertNoFlattenedDuplicates('gcash_manual_payments', ['reference_number']);
    }

    private function assertNoFlattenedDuplicates(string $table, array $columns): void
    {
        if (! Schema::hasTable($table)) {
            return;
        }

        foreach ($columns as $column) {
            if (! Schema::hasColumn($table, $column)) {
                return;
            }
        }

        $query = DB::table($table);
        foreach ($columns as $column) {
            $query->whereNotNull($column);
        }

        $duplicate = $query
            ->select($columns)
            ->groupBy($columns)
            ->havingRaw('COUNT(*) > 1')
            ->first();

        if ($duplicate !== null) {
            throw new RuntimeException("Cannot flatten {$table}: duplicate values would violate the global unique constraint on ".implode(', ', $columns).'.');
        }
    }

    private function dropOrganizationIndexesAndForeignKeys(string $table): void
    {
        if (Schema::getConnection()->getDriverName() !== 'sqlite') {
            foreach (Schema::getForeignKeys($table) as $foreignKey) {
                if (in_array('organization_id', $foreignKey['columns'] ?? [], true)) {
                    Schema::table($table, function (Blueprint $blueprint) use ($foreignKey): void {
                        $blueprint->dropForeign($foreignKey['name']);
                    });
                }
            }
        }

        foreach (Schema::getIndexes($table) as $index) {
            if (($index['primary'] ?? false) || ! in_array('organization_id', $index['columns'] ?? [], true)) {
                continue;
            }

            Schema::table($table, function (Blueprint $blueprint) use ($index): void {
                $blueprint->dropIndex($index['name']);
            });
        }
    }

    private function addGlobalIndexes(): void
    {
        $uniqueIndexes = [
            'roles' => [['name']],
            'role_assignments' => [['user_id', 'role_id']],
            'customers' => [['customer_number']],
            'billing_cycles' => [['name']],
            'plans' => [['code']],
            'plan_versions' => [['plan_id', 'version']],
            'billing_accounts' => [['account_number']],
            'subscriber_services' => [['service_number']],
            'invoices' => [['invoice_number'], ['billing_account_id', 'billing_period_start', 'billing_period_end']],
            'payments' => [['payment_number'], ['idempotency_key']],
            'payment_allocations' => [['payment_id', 'invoice_id']],
            'billing_statements' => [['statement_number']],
            'organization_email_settings' => [['user_id']],
            'organization_notification_settings' => [['user_id']],
            'organization_paymongo_settings' => [['user_id']],
            'organization_gcash_settings' => [['user_id']],
            'gcash_manual_payments' => [['reference_number']],
        ];

        foreach ($uniqueIndexes as $table => $definitions) {
            if (! Schema::hasTable($table)) {
                continue;
            }

            foreach ($definitions as $columns) {
                if (collect($columns)->contains(fn (string $column): bool => ! Schema::hasColumn($table, $column))) {
                    continue;
                }

                if (! $this->hasIndex($table, $columns, true)) {
                    $indexName = $this->indexName('uniq', $table, $columns);
                    Schema::table($table, function (Blueprint $blueprint) use ($columns, $indexName): void {
                        $blueprint->unique($columns, $indexName);
                    });
                }
            }
        }

        $indexes = [
            'customers' => [['legal_name'], ['email']],
            'billing_accounts' => [['customer_id']],
            'subscriber_services' => [['customer_id'], ['billing_account_id']],
            'subscriptions' => [['subscriber_service_id'], ['billing_account_id'], ['plan_version_id']],
            'invoice_items' => [['invoice_id'], ['subscription_id']],
            'gcash_manual_payments' => [['invoice_id'], ['statement_id']],
        ];

        foreach ($indexes as $table => $definitions) {
            if (! Schema::hasTable($table)) {
                continue;
            }

            foreach ($definitions as $columns) {
                if (collect($columns)->contains(fn (string $column): bool => ! Schema::hasColumn($table, $column))) {
                    continue;
                }

                if (! $this->hasIndex($table, $columns)) {
                    $indexName = $this->indexName('idx', $table, $columns);
                    Schema::table($table, function (Blueprint $blueprint) use ($columns, $indexName): void {
                        $blueprint->index($columns, $indexName);
                    });
                }
            }
        }
    }

    private function hasIndex(string $table, array $columns, bool $unique = false): bool
    {
        foreach (Schema::getIndexes($table) as $index) {
            if (($index['unique'] ?? false) === $unique && ($index['columns'] ?? []) === $columns) {
                return true;
            }
        }

        return false;
    }

    private function indexName(string $prefix, string $table, array $columns): string
    {
        return $prefix.'_'.substr($table, 0, 24).'_'.substr(md5(implode('_', $columns)), 0, 10);
    }
};
