<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('olt_qinq_provisions', function (Blueprint $table): void {
            $table->unsignedInteger('profile_id')->nullable()->after('ont_line_profile');
            $table->unsignedInteger('dba_profile_id')->nullable()->after('profile_id');
            $table->string('port', 50)->nullable()->after('dba_profile_id');
        });
    }

    public function down(): void
    {
        Schema::table('olt_qinq_provisions', function (Blueprint $table): void {
            $table->dropColumn(['profile_id', 'dba_profile_id', 'port']);
        });
    }
};
