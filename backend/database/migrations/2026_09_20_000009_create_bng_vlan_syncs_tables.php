<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('bng_vlan_syncs', function (Blueprint $table): void {
            $table->id();
            $table->ulid('public_id')->unique();
            $table->foreignId('bng_id')->constrained('bngs')->cascadeOnDelete();
            $table->foreignId('olt_id')->constrained('olts')->cascadeOnDelete();
            $table->string('vlan_mode', 20);
            $table->boolean('enabled')->default(true);
            $table->timestamps();
            $table->unique(['bng_id', 'olt_id', 'vlan_mode']);
        });

        Schema::create('bng_vlan_interfaces', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('bng_id')->constrained('bngs')->cascadeOnDelete();
            $table->foreignId('olt_id')->constrained('olts')->cascadeOnDelete();
            $table->string('vlan_mode', 20);
            $table->unsignedSmallInteger('outer_vlan')->nullable();
            $table->unsignedSmallInteger('inner_vlan')->nullable();
            $table->string('interface_name', 255);
            $table->string('status', 20)->default('pending');
            $table->text('last_error')->nullable();
            $table->timestamps();
            $table->unique(['bng_id', 'olt_id', 'interface_name']);
            $table->index(['bng_id', 'olt_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bng_vlan_interfaces');
        Schema::dropIfExists('bng_vlan_syncs');
    }
};
