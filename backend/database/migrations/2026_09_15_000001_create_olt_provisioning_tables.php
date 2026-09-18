<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('olt_vlan_provisions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('olt_id')->constrained('olts')->cascadeOnDelete();
            $table->unsignedSmallInteger('vlan_id');
            $table->string('name', 190);
            $table->string('service_mode', 30)->default('internet');
            $table->string('status', 30)->default('draft');
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->unique(['olt_id', 'vlan_id']);
        });

        Schema::create('olt_qinq_provisions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('olt_id')->constrained('olts')->cascadeOnDelete();
            $table->unsignedSmallInteger('outer_vlan');
            $table->unsignedSmallInteger('inner_vlan');
            $table->string('name', 190);
            $table->string('status', 30)->default('draft');
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->unique(['olt_id', 'outer_vlan', 'inner_vlan']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('olt_qinq_provisions');
        Schema::dropIfExists('olt_vlan_provisions');
    }
};
