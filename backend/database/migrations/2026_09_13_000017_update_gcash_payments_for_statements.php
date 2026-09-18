<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('gcash_manual_payments', function (Blueprint $table): void {
            $table->foreignId('statement_id')->nullable()->after('invoice_id')->constrained('billing_statements')->nullOnDelete();
            $table->foreignId('invoice_id')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('gcash_manual_payments', function (Blueprint $table): void {
            $table->dropForeign(['statement_id']);
            $table->dropColumn('statement_id');
            $table->foreignId('invoice_id')->nullable(false)->change();
        });
    }
};
