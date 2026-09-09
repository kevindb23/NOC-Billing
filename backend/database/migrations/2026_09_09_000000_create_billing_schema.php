<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('organizations', function (Blueprint $table) {
            $table->id();
            $table->ulid('public_id')->unique();
            $table->string('name');
            $table->string('slug')->unique();
            $table->string('status')->default('active')->index();
            $table->string('timezone')->default('UTC');
            $table->char('default_currency', 3)->default('PHP');
            $table->timestamps();
        });

        Schema::create('organization_user', function (Blueprint $table) {
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->boolean('is_default')->default(false);
            $table->string('status')->default('active');
            $table->timestamps();
            $table->primary(['organization_id', 'user_id']);
        });

        Schema::create('roles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->nullable()->constrained()->nullOnDelete();
            $table->string('name');
            $table->string('guard_name')->default('api');
            $table->timestamps();
            $table->unique(['organization_id', 'name']);
        });

        Schema::create('permissions', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique();
            $table->string('guard_name')->default('api');
            $table->timestamps();
        });

        Schema::create('role_assignments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('role_id')->constrained()->cascadeOnDelete();
            $table->timestamps();
            $table->unique(['organization_id', 'user_id', 'role_id']);
        });

        Schema::create('role_permissions', function (Blueprint $table) {
            $table->foreignId('role_id')->constrained()->cascadeOnDelete();
            $table->foreignId('permission_id')->constrained()->cascadeOnDelete();
            $table->primary(['role_id', 'permission_id']);
        });

        Schema::create('customers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->ulid('public_id')->unique();
            $table->string('customer_number');
            $table->string('customer_type')->default('residential');
            $table->string('legal_name');
            $table->string('first_name')->nullable();
            $table->string('last_name')->nullable();
            $table->string('email')->nullable();
            $table->string('phone')->nullable();
            $table->string('status')->default('active')->index();
            $table->text('notes')->nullable();
            $table->softDeletes();
            $table->timestamps();
            $table->unique(['organization_id', 'customer_number']);
            $table->index(['organization_id', 'legal_name']);
            $table->index(['organization_id', 'email']);
        });

        Schema::create('billing_cycles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('interval_unit')->default('month');
            $table->unsignedSmallInteger('interval_count')->default(1);
            $table->unsignedTinyInteger('billing_day')->default(1);
            $table->unsignedSmallInteger('grace_days')->default(7);
            $table->string('status')->default('active');
            $table->timestamps();
            $table->unique(['organization_id', 'name']);
        });

        Schema::create('plans', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('billing_cycle_id')->constrained()->restrictOnDelete();
            $table->ulid('public_id')->unique();
            $table->string('code');
            $table->string('name');
            $table->string('service_type')->default('internet');
            $table->text('description')->nullable();
            $table->string('status')->default('active')->index();
            $table->timestamps();
            $table->unique(['organization_id', 'code']);
        });

        Schema::create('plan_versions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('plan_id')->constrained()->restrictOnDelete();
            $table->unsignedInteger('version');
            $table->unsignedBigInteger('recurring_price_minor');
            $table->unsignedBigInteger('setup_fee_minor')->default(0);
            $table->char('currency', 3)->default('PHP');
            $table->unsignedBigInteger('download_kbps');
            $table->unsignedBigInteger('upload_kbps');
            $table->date('effective_from');
            $table->date('effective_until')->nullable();
            $table->string('status')->default('active');
            $table->timestamps();
            $table->unique(['organization_id', 'plan_id', 'version']);
        });

        Schema::create('billing_accounts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('customer_id')->constrained()->restrictOnDelete();
            $table->ulid('public_id')->unique();
            $table->string('account_number');
            $table->char('currency', 3)->default('PHP');
            $table->unsignedBigInteger('credit_limit_minor')->default(0);
            $table->string('status')->default('active')->index();
            $table->timestamps();
            $table->unique(['organization_id', 'account_number']);
            $table->index(['organization_id', 'customer_id']);
        });

        Schema::create('subscriber_services', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('customer_id')->constrained()->restrictOnDelete();
            $table->foreignId('billing_account_id')->constrained()->restrictOnDelete();
            $table->ulid('public_id')->unique();
            $table->string('service_number');
            $table->string('service_type')->default('internet');
            $table->string('status')->default('pending')->index();
            $table->timestamp('activated_at')->nullable();
            $table->timestamp('suspended_at')->nullable();
            $table->timestamp('terminated_at')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->unique(['organization_id', 'service_number']);
        });

        Schema::create('subscriptions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('subscriber_service_id')->constrained()->restrictOnDelete();
            $table->foreignId('billing_account_id')->constrained()->restrictOnDelete();
            $table->foreignId('plan_version_id')->constrained()->restrictOnDelete();
            $table->string('status')->default('active')->index();
            $table->date('starts_on');
            $table->date('ends_on')->nullable();
            $table->date('next_billing_date')->index();
            $table->unsignedTinyInteger('billing_day')->default(1);
            $table->unsignedBigInteger('price_snapshot_minor');
            $table->char('currency_snapshot', 3);
            $table->string('plan_name_snapshot');
            $table->timestamps();
        });

        Schema::create('invoices', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('billing_account_id')->constrained()->restrictOnDelete();
            $table->ulid('public_id')->unique();
            $table->string('invoice_number');
            $table->string('status')->default('draft')->index();
            $table->date('issue_date');
            $table->date('due_date')->index();
            $table->date('billing_period_start')->nullable();
            $table->date('billing_period_end')->nullable();
            $table->char('currency', 3)->default('PHP');
            $table->unsignedBigInteger('subtotal_minor')->default(0);
            $table->unsignedBigInteger('discount_minor')->default(0);
            $table->unsignedBigInteger('tax_minor')->default(0);
            $table->unsignedBigInteger('total_minor')->default(0);
            $table->unsignedBigInteger('amount_paid_minor')->default(0);
            $table->unsignedBigInteger('balance_due_minor')->default(0);
            $table->timestamp('voided_at')->nullable();
            $table->timestamps();
            $table->unique(['organization_id', 'invoice_number']);
            $table->unique(['organization_id', 'billing_account_id', 'billing_period_start', 'billing_period_end'], 'unique_billing_period');
        });

        Schema::create('invoice_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('invoice_id')->constrained()->restrictOnDelete();
            $table->foreignId('subscription_id')->nullable()->constrained()->nullOnDelete();
            $table->string('description');
            $table->decimal('quantity', 12, 4)->default(1);
            $table->unsignedBigInteger('unit_amount_minor');
            $table->unsignedBigInteger('line_total_minor');
            $table->unsignedBigInteger('tax_minor')->default(0);
            $table->date('service_period_start')->nullable();
            $table->date('service_period_end')->nullable();
            $table->timestamps();
        });

        Schema::create('payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('billing_account_id')->constrained()->restrictOnDelete();
            $table->ulid('public_id')->unique();
            $table->string('payment_number');
            $table->unsignedBigInteger('amount_minor');
            $table->char('currency', 3)->default('PHP');
            $table->string('payment_method')->default('cash');
            $table->string('reference')->nullable();
            $table->string('idempotency_key')->nullable();
            $table->string('status')->default('posted')->index();
            $table->timestamp('received_at');
            $table->timestamps();
            $table->unique(['organization_id', 'payment_number']);
            $table->unique(['organization_id', 'idempotency_key']);
        });

        Schema::create('payment_allocations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('payment_id')->constrained()->restrictOnDelete();
            $table->foreignId('invoice_id')->constrained()->restrictOnDelete();
            $table->unsignedBigInteger('amount_minor');
            $table->timestamps();
            $table->unique(['organization_id', 'payment_id', 'invoice_id']);
        });

        Schema::create('credits', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('billing_account_id')->constrained()->restrictOnDelete();
            $table->string('source_type');
            $table->unsignedBigInteger('source_id')->nullable();
            $table->unsignedBigInteger('amount_minor');
            $table->unsignedBigInteger('remaining_minor');
            $table->string('reason');
            $table->string('status')->default('available');
            $table->timestamps();
        });

        Schema::create('adjustments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('billing_account_id')->constrained()->restrictOnDelete();
            $table->foreignId('invoice_id')->nullable()->constrained()->restrictOnDelete();
            $table->string('adjustment_type');
            $table->unsignedBigInteger('amount_minor');
            $table->string('reason');
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('status')->default('posted');
            $table->timestamps();
        });

        Schema::create('balance_transactions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('billing_account_id')->constrained()->restrictOnDelete();
            $table->foreignId('invoice_id')->nullable()->constrained()->restrictOnDelete();
            $table->foreignId('payment_id')->nullable()->constrained()->restrictOnDelete();
            $table->foreignId('credit_id')->nullable()->constrained()->restrictOnDelete();
            $table->foreignId('adjustment_id')->nullable()->constrained()->restrictOnDelete();
            $table->string('transaction_type');
            $table->string('direction');
            $table->unsignedBigInteger('amount_minor');
            $table->char('currency', 3)->default('PHP');
            $table->string('description');
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->index(['organization_id', 'billing_account_id', 'created_at'], 'bt_org_account_created_idx');
        });

        Schema::create('audit_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('actor_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('action');
            $table->string('auditable_type');
            $table->unsignedBigInteger('auditable_id')->nullable();
            $table->json('old_values')->nullable();
            $table->json('new_values')->nullable();
            $table->string('correlation_id')->nullable()->index();
            $table->ipAddress('ip_address')->nullable();
            $table->text('user_agent')->nullable();
            $table->timestamps();
            $table->index(['organization_id', 'created_at']);
        });
    }

    public function down(): void
    {
        foreach ([
            'audit_logs', 'balance_transactions', 'adjustments', 'credits', 'payment_allocations',
            'payments', 'invoice_items', 'invoices', 'subscriptions', 'subscriber_services',
            'billing_accounts', 'plan_versions', 'plans', 'billing_cycles', 'customers',
            'role_permissions', 'role_assignments', 'permissions', 'roles', 'organization_user', 'organizations',
        ] as $table) {
            Schema::dropIfExists($table);
        }
    }
};
