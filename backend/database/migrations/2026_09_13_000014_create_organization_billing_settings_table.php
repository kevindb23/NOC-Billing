<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
return new class extends Migration {
    public function up(): void { Schema::create('organization_billing_settings', function (Blueprint $table): void { $table->id(); $table->foreignId('organization_id')->constrained()->cascadeOnDelete(); $table->unsignedTinyInteger('cycle_start_day')->default(20); $table->decimal('vat_rate', 5, 2)->default(12); $table->unsignedSmallInteger('installation_amortization_months')->default(0); $table->timestamps(); $table->unique('organization_id'); }); }
    public function down(): void { Schema::dropIfExists('organization_billing_settings'); }
};
