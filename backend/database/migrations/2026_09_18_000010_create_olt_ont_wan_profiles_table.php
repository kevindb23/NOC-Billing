<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
return new class extends Migration {
    public function up(): void { Schema::create('olt_ont_wan_profiles', function (Blueprint $table): void { $table->id(); $table->foreignId('olt_id')->constrained('olts')->cascadeOnDelete(); $table->unsignedTinyInteger('profile_id'); $table->string('profile_name', 190); $table->boolean('nat_enabled')->default(false); $table->string('status')->default('draft'); $table->text('notes')->nullable(); $table->timestamps(); $table->unique(['olt_id', 'profile_id']); }); }
    public function down(): void { Schema::dropIfExists('olt_ont_wan_profiles'); }
};
