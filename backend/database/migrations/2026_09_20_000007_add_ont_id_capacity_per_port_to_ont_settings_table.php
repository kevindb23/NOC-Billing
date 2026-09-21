<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('ont_settings', function (Blueprint $table): void {
            $table->unsignedSmallInteger('ont_id_capacity_per_port')->default(64)->after('do_not_allow_rogue_onus');
        });
    }

    public function down(): void
    {
        Schema::table('ont_settings', fn (Blueprint $table) => $table->dropColumn('ont_id_capacity_per_port'));
    }
};
