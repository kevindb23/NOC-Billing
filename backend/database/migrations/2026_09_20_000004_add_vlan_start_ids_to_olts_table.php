<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('olts', function (Blueprint $table): void {
            $table->unsignedSmallInteger('s_vlan_start_id')->default(1)->after('ont_line_profile_start_id');
            $table->unsignedSmallInteger('c_vlan_start_id')->default(1)->after('s_vlan_start_id');
            $table->unsignedSmallInteger('tr069_vlan_start_id')->default(1)->after('c_vlan_start_id');
        });
    }

    public function down(): void
    {
        Schema::table('olts', fn (Blueprint $table) => $table->dropColumn(['s_vlan_start_id', 'c_vlan_start_id', 'tr069_vlan_start_id']));
    }
};
