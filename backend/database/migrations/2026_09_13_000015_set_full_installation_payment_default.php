<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (DB::getDriverName() === 'mysql') {
            DB::statement('ALTER TABLE organization_billing_settings MODIFY installation_amortization_months SMALLINT UNSIGNED NOT NULL DEFAULT 0');
        }

    }

    public function down(): void
    {
        if (DB::getDriverName() === 'mysql') {
            DB::statement('ALTER TABLE organization_billing_settings MODIFY installation_amortization_months SMALLINT UNSIGNED NOT NULL DEFAULT 24');
        }
    }
};
