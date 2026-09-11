<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('customers') || ! Schema::hasTable('subscribers')) {
            return;
        }

        Schema::disableForeignKeyConstraints();

        try {
            Schema::rename('subscribers', 'customers');
            $this->renameColumnIfPresent('customers', 'subscriber_number', 'customer_number');
            $this->renameColumnIfPresent('billing_accounts', 'subscriber_id', 'customer_id');
            $this->renameColumnIfPresent('subscriber_services', 'subscriber_id', 'customer_id');
        } finally {
            Schema::enableForeignKeyConstraints();
        }
    }

    public function down(): void
    {
        if (! Schema::hasTable('customers') || Schema::hasTable('subscribers')) {
            return;
        }

        Schema::disableForeignKeyConstraints();

        try {
            $this->renameColumnIfPresent('billing_accounts', 'customer_id', 'subscriber_id');
            $this->renameColumnIfPresent('subscriber_services', 'customer_id', 'subscriber_id');
            $this->renameColumnIfPresent('customers', 'customer_number', 'subscriber_number');
            Schema::rename('customers', 'subscribers');
        } finally {
            Schema::enableForeignKeyConstraints();
        }
    }

    private function renameColumnIfPresent(string $table, string $from, string $to): void
    {
        if (Schema::hasTable($table) && Schema::hasColumn($table, $from) && ! Schema::hasColumn($table, $to)) {
            Schema::table($table, fn (Blueprint $blueprint) => $blueprint->renameColumn($from, $to));
        }
    }
};
