<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('olt_qinq_provisions', function (Blueprint $table): void {
            $table->string('qinq_type', 30)->default('s_vlan')->after('inner_vlan');
            $table->string('ont_line_profile', 190)->nullable()->after('qinq_type');
        });
    }

    public function down(): void
    {
        Schema::table('olt_qinq_provisions', function (Blueprint $table): void {
            $table->dropColumn(['qinq_type', 'ont_line_profile']);
        });
    }
};
