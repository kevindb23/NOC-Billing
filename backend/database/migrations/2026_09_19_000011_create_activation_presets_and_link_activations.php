<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('activation_presets', function (Blueprint $table): void {
            $table->id();
            $table->ulid('public_id')->unique();
            $table->foreignId('olt_id')->constrained('olts')->cascadeOnDelete();
            $table->string('name', 190);
            $table->foreignId('dba_profile_id')->nullable()->constrained('olt_dba_profiles')->nullOnDelete();
            $table->foreignId('ont_line_profile_id')->nullable()->constrained('olt_qinq_provisions')->nullOnDelete();
            $table->foreignId('ont_service_profile_id')->nullable()->constrained('olt_ont_service_profiles')->nullOnDelete();
            $table->foreignId('ont_wan_profile_id')->nullable()->constrained('olt_ont_wan_profiles')->nullOnDelete();
            $table->foreignId('ont_tr069_server_profile_id')->nullable()->constrained('olt_ont_tr069_server_profiles')->nullOnDelete();
            $table->timestamps();
            $table->unique(['olt_id', 'name']);
        });

        Schema::table('activations', function (Blueprint $table): void {
            $table->foreignId('olt_id')->nullable()->after('subscription_id')->constrained('olts')->restrictOnDelete();
            $table->foreignId('activation_preset_id')->nullable()->after('olt_id')->constrained('activation_presets')->nullOnDelete();
            $table->foreignId('vlan_provision_id')->nullable()->after('activation_preset_id')->constrained('olt_vlan_provisions')->nullOnDelete();
            $table->foreignId('qinq_provision_id')->nullable()->after('vlan_provision_id')->constrained('olt_qinq_provisions')->nullOnDelete();
            $table->string('provisioning_type', 20)->nullable()->after('qinq_provision_id');
            $table->unsignedSmallInteger('c_vlan')->nullable()->change();
            $table->unsignedSmallInteger('s_vlan')->nullable()->change();
            $table->index(['olt_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::table('activations', function (Blueprint $table): void {
            $table->dropIndex(['activations_olt_id_status_index']);
            $table->dropForeign(['qinq_provision_id']);
            $table->dropForeign(['vlan_provision_id']);
            $table->dropForeign(['activation_preset_id']);
            $table->dropForeign(['olt_id']);
            $table->dropColumn(['olt_id', 'activation_preset_id', 'vlan_provision_id', 'qinq_provision_id', 'provisioning_type']);
            $table->unsignedSmallInteger('c_vlan')->nullable(false)->change();
            $table->unsignedSmallInteger('s_vlan')->nullable(false)->change();
        });

        Schema::dropIfExists('activation_presets');
    }
};
