<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
return new class extends Migration {
    public function up(): void { Schema::create('gcash_manual_payments', function (Blueprint $table): void { $table->id(); $table->foreignId('organization_id')->constrained()->cascadeOnDelete(); $table->foreignId('user_id')->constrained()->restrictOnDelete(); $table->foreignId('invoice_id')->constrained()->restrictOnDelete(); $table->string('reference_number')->index(); $table->unsignedBigInteger('amount_minor'); $table->char('currency', 3)->default('PHP'); $table->date('transferred_on'); $table->string('receipt_path')->nullable(); $table->text('notes')->nullable(); $table->string('status')->default('pending')->index(); $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete(); $table->timestamp('reviewed_at')->nullable(); $table->timestamps(); $table->unique(['organization_id', 'reference_number']); }); }
    public function down(): void { Schema::dropIfExists('gcash_manual_payments'); }
};
