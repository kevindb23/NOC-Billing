<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('router_credentials', function (Blueprint $table): void {
            $table->id();
            $table->ulid('public_id')->unique();
            $table->foreignId('router_id')->constrained('routers')->cascadeOnDelete();
            $table->string('name');
            $table->string('auth_type');
            $table->text('username')->nullable();
            $table->text('password')->nullable();
            $table->text('private_key')->nullable();
            $table->text('private_key_passphrase')->nullable();
            $table->text('api_token')->nullable();
            $table->text('snmp_community')->nullable();
            $table->text('snmp_security')->nullable();
            $table->text('known_hosts')->nullable();
            $table->text('tls_ca_certificate')->nullable();
            $table->text('tls_client_certificate')->nullable();
            $table->text('tls_client_key')->nullable();
            $table->boolean('is_primary')->default(false);
            $table->unsignedInteger('version')->default(1);
            $table->timestamp('last_used_at')->nullable();
            $table->unsignedBigInteger('primary_router_key')->nullable()->storedAs(
                'CASE WHEN is_primary = 1 THEN router_id ELSE NULL END'
            );
            $table->timestamps();

            $table->unique('primary_router_key', 'router_credentials_one_primary');
            $table->index(['router_id', 'is_primary']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('router_credentials');
    }
};
