<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('olts', function (Blueprint $table): void {
            $table->unsignedSmallInteger('vlan_start_id')->default(1)->after('tr069_vlan_start_id');
        });
    }

    public function down(): void
    {
        Schema::table('olts', fn (Blueprint $table) => $table->dropColumn('vlan_start_id'));
    }
};
