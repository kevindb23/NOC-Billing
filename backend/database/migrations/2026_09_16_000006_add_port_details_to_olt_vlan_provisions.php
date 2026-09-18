<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('olt_vlan_provisions', function (Blueprint $table): void {
            $table->unsignedTinyInteger('frame')->nullable()->after('service_mode');
            $table->unsignedTinyInteger('slot')->nullable()->after('frame');
            $table->unsignedTinyInteger('port_number')->nullable()->after('slot');
            $table->string('port', 20)->nullable()->after('port_number');
        });
    }

    public function down(): void
    {
        Schema::table('olt_vlan_provisions', function (Blueprint $table): void {
            $table->dropColumn(['frame', 'slot', 'port_number', 'port']);
        });
    }
};
