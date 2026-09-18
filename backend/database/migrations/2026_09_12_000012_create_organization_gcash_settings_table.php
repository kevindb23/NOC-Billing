<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
return new class extends Migration {
    public function up(): void { Schema::create('organization_gcash_settings', function (Blueprint $table): void { $table->id(); $table->foreignId('organization_id')->constrained()->cascadeOnDelete(); $table->foreignId('user_id')->constrained()->cascadeOnDelete(); $table->boolean('enabled')->default(false); $table->string('account_name')->nullable(); $table->string('mobile_number')->nullable(); $table->text('instructions')->nullable(); $table->timestamps(); $table->unique(['organization_id', 'user_id']); }); }
    public function down(): void { Schema::dropIfExists('organization_gcash_settings'); }
};
