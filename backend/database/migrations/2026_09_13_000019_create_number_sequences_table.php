<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('number_sequences')) {
            Schema::create('number_sequences', function (Blueprint $table): void {
                $table->string('sequence_key', 100)->primary();
                $table->unsignedBigInteger('next_value')->default(0);
                $table->timestamps();
            });
        }

        foreach ([
            ['customers', 'customers', 'customer_number', 'CUS-'],
            ['billing_accounts', 'billing_accounts', 'account_number', 'BA-'],
            ['subscriber_services', 'subscriber_services', 'service_number', 'SVC-'],
        ] as [$key, $table, $column, $prefix]) {
            $maximum = DB::table($table)->pluck($column)->reduce(
                fn (int $current, mixed $value): int => preg_match('/^'.preg_quote($prefix, '/').'([0-9]+)$/', (string) $value, $matches)
                    ? max($current, (int) $matches[1])
                    : $current,
                0,
            );
            DB::table('number_sequences')->insertOrIgnore([
                'sequence_key' => $key,
                'next_value' => $maximum,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('number_sequences');
    }
};
