<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (Schema::getConnection()->getDriverName() !== 'sqlite') {
            DB::statement('ALTER TABLE bng_cgnat_policies MODIFY subscriber_interface VARCHAR(255) NULL');
            DB::statement('ALTER TABLE bng_cgnat_policies MODIFY internet_interface VARCHAR(255) NULL');
        }

        Schema::create('bng_forwarding_rules', function (Blueprint $table): void {
            $table->id();
            $table->ulid('public_id')->unique();
            $table->foreignId('bng_id')->constrained('bngs')->cascadeOnDelete();
            $table->string('name');
            $table->string('customer_interface');
            $table->string('internet_interface');
            $table->string('status')->default('draft');
            $table->text('notes')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bng_forwarding_rules');
        if (Schema::getConnection()->getDriverName() !== 'sqlite') {
            DB::statement("UPDATE bng_cgnat_policies SET subscriber_interface = '' WHERE subscriber_interface IS NULL");
            DB::statement("UPDATE bng_cgnat_policies SET internet_interface = '' WHERE internet_interface IS NULL");
            DB::statement('ALTER TABLE bng_cgnat_policies MODIFY subscriber_interface VARCHAR(255) NOT NULL');
            DB::statement('ALTER TABLE bng_cgnat_policies MODIFY internet_interface VARCHAR(255) NOT NULL');
        }
    }
};
