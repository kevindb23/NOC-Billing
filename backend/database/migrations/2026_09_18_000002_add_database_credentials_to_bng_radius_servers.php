<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('bng_radius_servers', function (Blueprint $table) {
            $table->string('database_name', 120)->nullable()->after('secret');
            $table->string('database_username', 120)->nullable()->after('database_name');
            $table->text('database_password')->nullable()->after('database_username');
        });
    }

    public function down(): void
    {
        Schema::table('bng_radius_servers', function (Blueprint $table) {
            $table->dropColumn(['database_name', 'database_username', 'database_password']);
        });
    }
};
