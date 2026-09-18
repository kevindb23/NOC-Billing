<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('olt_qinq_provisions', function (Blueprint $table): void {
            $table->unsignedSmallInteger('outer_vlan')->nullable()->change();
            $table->unsignedSmallInteger('inner_vlan')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('olt_qinq_provisions', function (Blueprint $table): void {
            $table->unsignedSmallInteger('outer_vlan')->nullable(false)->change();
            $table->unsignedSmallInteger('inner_vlan')->nullable(false)->change();
        });
    }
};
