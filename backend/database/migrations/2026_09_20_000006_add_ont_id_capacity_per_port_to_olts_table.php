<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('olts', function (Blueprint $table): void {
            $table->unsignedSmallInteger('ont_id_capacity_per_port')->default(64)->after('vlan_start_id');
        });
    }

    public function down(): void
    {
        Schema::table('olts', fn (Blueprint $table) => $table->dropColumn('ont_id_capacity_per_port'));
    }
};
