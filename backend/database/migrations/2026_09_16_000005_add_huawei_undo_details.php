<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('olt_qinq_provisions', function (Blueprint $table): void {
            $table->unsignedInteger('service_port_id')->nullable()->after('qinq_type');
            $table->unsignedSmallInteger('frame')->nullable()->after('service_port_id');
            $table->unsignedSmallInteger('slot')->nullable()->after('frame');
            $table->unsignedSmallInteger('port_number')->nullable()->after('slot');
        });
    }

    public function down(): void
    {
        Schema::table('olt_qinq_provisions', function (Blueprint $table): void {
            $table->dropColumn(['service_port_id', 'frame', 'slot', 'port_number']);
        });
    }
};
