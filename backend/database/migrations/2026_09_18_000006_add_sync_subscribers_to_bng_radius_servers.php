<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('bng_radius_servers', function (Blueprint $table): void {
            $table->boolean('sync_subscribers')->default(false)->after('database_password');
        });
    }

    public function down(): void
    {
        Schema::table('bng_radius_servers', function (Blueprint $table): void {
            $table->dropColumn('sync_subscribers');
        });
    }
};
