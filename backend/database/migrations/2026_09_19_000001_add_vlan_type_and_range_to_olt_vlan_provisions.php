<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('olt_vlan_provisions', function (Blueprint $table): void {
            $table->string('vlan_type', 20)->default('smart')->after('vlan_id');
            $table->unsignedSmallInteger('vlan_to')->nullable()->after('vlan_type');
        });
    }

    public function down(): void
    {
        Schema::table('olt_vlan_provisions', function (Blueprint $table): void {
            $table->dropColumn(['vlan_type', 'vlan_to']);
        });
    }
};
