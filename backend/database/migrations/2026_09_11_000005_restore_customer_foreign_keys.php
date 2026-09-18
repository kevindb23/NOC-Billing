<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $this->addCustomerForeignKey('billing_accounts', 'billing_accounts_customer_id_fk');
        $this->addCustomerForeignKey('subscriber_services', 'subscriber_services_customer_id_fk');
    }

    public function down(): void
    {
        foreach ([
            ['billing_accounts', 'billing_accounts_customer_id_fk'],
            ['subscriber_services', 'subscriber_services_customer_id_fk'],
        ] as [$table, $constraint]) {
            if (Schema::hasTable($table)) {
                Schema::table($table, fn (Blueprint $blueprint) => $blueprint->dropForeign($constraint));
            }
        }
    }

    private function addCustomerForeignKey(string $table, string $constraint): void
    {
        if (DB::connection()->getDriverName() === 'sqlite') {
            return;
        }

        if (! Schema::hasTable($table) || ! Schema::hasColumn($table, 'customer_id') || ! Schema::hasTable('customers')) {
            return;
        }

        $orphans = DB::table($table)
            ->whereNotNull('customer_id')
            ->whereNotExists(fn ($query) => $query->selectRaw('1')->from('customers')->whereColumn('customers.id', $table.'.customer_id'))
            ->count();

        if ($orphans > 0) {
            throw new RuntimeException("Cannot add {$constraint}: {$orphans} orphan customer references exist in {$table}.");
        }

        $alreadyExists = DB::table('information_schema.referential_constraints')
            ->where('constraint_schema', DB::getDatabaseName())
            ->where('table_name', $table)
            ->where('constraint_name', $constraint)
            ->exists();

        if (! $alreadyExists) {
            Schema::table($table, fn (Blueprint $blueprint) => $blueprint
                ->foreign('customer_id', $constraint)
                ->references('id')
                ->on('customers')
                ->restrictOnDelete());
        }
    }
};
