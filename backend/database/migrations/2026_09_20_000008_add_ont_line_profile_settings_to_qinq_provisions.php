<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('olt_qinq_provisions', function (Blueprint $table): void {
            $table->boolean('tr069_management_enabled')->default(true)->after('dba_profile_id');
            $table->unsignedTinyInteger('tr069_ip_index')->default(1)->after('tr069_management_enabled');
            $table->boolean('omcc_encrypt_enabled')->default(true)->after('tr069_ip_index');
        });
    }

    public function down(): void
    {
        Schema::table('olt_qinq_provisions', function (Blueprint $table): void {
            $table->dropColumn(['tr069_management_enabled', 'tr069_ip_index', 'omcc_encrypt_enabled']);
        });
    }
};
