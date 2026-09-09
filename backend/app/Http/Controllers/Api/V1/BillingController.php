<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\BalanceTransaction;
use App\Models\BillingAccount;
use App\Models\Customer;
use App\Models\Credit;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\Payment;
use App\Models\PaymentAllocation;
use App\Models\Plan;
use App\Models\PlanVersion;
use App\Models\SubscriberService;
use App\Models\Subscription;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class BillingController extends Controller
{
    public function accounts(Request $request): JsonResponse
    {
        $org = $request->attributes->get('organization');
        return response()->json(['data' => BillingAccount::with('customer')->where('organization_id', $org->id)->latest()->paginate($request->integer('per_page', 20))]);
    }

    public function storeAccount(Request $request): JsonResponse
    {
        $data = $request->validate(['customer_id' => ['required', 'string']]);
        $org = $request->attributes->get('organization');
        $customer = Customer::where('organization_id', $org->id)->where('public_id', $data['customer_id'])->firstOrFail();
        $account = BillingAccount::create([
            'organization_id' => $org->id,
            'customer_id' => $customer->id,
            'account_number' => 'BA-'.str_pad((string) (BillingAccount::where('organization_id', $org->id)->count() + 1), 6, '0', STR_PAD_LEFT),
            'currency' => $org->default_currency,
            'status' => 'active',
        ]);
        return response()->json(['data' => $account->load('customer')], 201);
    }

    public function plans(Request $request): JsonResponse
    {
        $org = $request->attributes->get('organization');
        return response()->json(['data' => Plan::with('versions', 'billingCycle')->where('organization_id', $org->id)->latest()->paginate($request->integer('per_page', 20))]);
    }

    public function services(Request $request): JsonResponse
    {
        $org = $request->attributes->get('organization');
        return response()->json(['data' => SubscriberService::with(['customer', 'billingAccount'])->where('organization_id', $org->id)->latest()->paginate($request->integer('per_page', 20))]);
    }

    public function storeService(Request $request): JsonResponse
    {
        $data = $request->validate([
            'customer_id' => ['required', 'string'],
            'billing_account_id' => ['required', 'string'],
            'service_type' => ['nullable', 'string', 'max:50'],
        ]);
        $org = $request->attributes->get('organization');
        $customer = Customer::where('organization_id', $org->id)->where('public_id', $data['customer_id'])->firstOrFail();
        $account = BillingAccount::where('organization_id', $org->id)->where('public_id', $data['billing_account_id'])->firstOrFail();
        abort_unless($account->customer_id === $customer->id, 422, 'Billing account does not belong to the customer.');
        $service = SubscriberService::create([
            'organization_id' => $org->id, 'customer_id' => $customer->id, 'billing_account_id' => $account->id,
            'service_number' => 'SVC-'.str_pad((string) (SubscriberService::where('organization_id', $org->id)->count() + 1), 6, '0', STR_PAD_LEFT),
            'service_type' => $data['service_type'] ?? 'internet', 'status' => 'pending',
        ]);
        return response()->json(['data' => $service->load(['customer', 'billingAccount'])], 201);
    }

    public function subscriptions(Request $request): JsonResponse
    {
        $org = $request->attributes->get('organization');
        return response()->json(['data' => Subscription::with(['service', 'planVersion.plan'])->where('organization_id', $org->id)->latest()->paginate($request->integer('per_page', 20))]);
    }

    public function storeSubscription(Request $request): JsonResponse
    {
        $data = $request->validate([
            'subscriber_service_id' => ['required', 'string'], 'billing_account_id' => ['required', 'string'],
            'plan_version_id' => ['required', 'integer'], 'starts_on' => ['required', 'date'], 'next_billing_date' => ['required', 'date'],
        ]);
        $org = $request->attributes->get('organization');
        $service = SubscriberService::where('organization_id', $org->id)->where('public_id', $data['subscriber_service_id'])->firstOrFail();
        $account = BillingAccount::where('organization_id', $org->id)->where('public_id', $data['billing_account_id'])->firstOrFail();
        $version = PlanVersion::with('plan')->where('organization_id', $org->id)->findOrFail($data['plan_version_id']);
        abort_unless($service->billing_account_id === $account->id, 422, 'Service does not belong to the billing account.');
        $subscription = Subscription::create([
            'organization_id' => $org->id, 'subscriber_service_id' => $service->id, 'billing_account_id' => $account->id,
            'plan_version_id' => $version->id, 'status' => 'active', 'starts_on' => $data['starts_on'], 'next_billing_date' => $data['next_billing_date'],
            'billing_day' => 1, 'price_snapshot_minor' => $version->recurring_price_minor, 'currency_snapshot' => $version->currency,
            'plan_name_snapshot' => $version->plan->name,
        ]);
        return response()->json(['data' => $subscription->load(['service', 'planVersion.plan'])], 201);
    }

    public function invoices(Request $request): JsonResponse
    {
        $org = $request->attributes->get('organization');
        return response()->json(['data' => Invoice::with('billingAccount.customer')->where('organization_id', $org->id)->latest()->paginate($request->integer('per_page', 20))]);
    }

    public function storeInvoice(Request $request): JsonResponse
    {
        $data = $request->validate([
            'billing_account_id' => ['required', 'string'], 'subscription_id' => ['required', 'integer'],
            'issue_date' => ['required', 'date'], 'due_date' => ['required', 'date', 'after_or_equal:issue_date'],
        ]);
        $org = $request->attributes->get('organization');
        $account = BillingAccount::where('organization_id', $org->id)->where('public_id', $data['billing_account_id'])->firstOrFail();
        $subscription = Subscription::where('organization_id', $org->id)->findOrFail($data['subscription_id']);
        abort_unless($subscription->billing_account_id === $account->id, 422, 'Subscription does not belong to the billing account.');
        $invoice = DB::transaction(function () use ($org, $account, $subscription, $data): Invoice {
            $total = $subscription->price_snapshot_minor;
            $invoice = Invoice::create([
                'organization_id' => $org->id, 'billing_account_id' => $account->id,
                'invoice_number' => 'INV-'.now()->format('Ym').'-'.str_pad((string) (Invoice::where('organization_id', $org->id)->count() + 1), 6, '0', STR_PAD_LEFT),
                'status' => 'open', 'issue_date' => $data['issue_date'], 'due_date' => $data['due_date'],
                'billing_period_start' => $data['issue_date'], 'billing_period_end' => $data['due_date'],
                'currency' => $account->currency, 'subtotal_minor' => $total, 'total_minor' => $total, 'balance_due_minor' => $total,
            ]);
            $invoice->items()->create([
                'organization_id' => $org->id, 'subscription_id' => $subscription->id, 'description' => $subscription->plan_name_snapshot,
                'quantity' => 1, 'unit_amount_minor' => $total, 'line_total_minor' => $total,
                'service_period_start' => $data['issue_date'], 'service_period_end' => $data['due_date'],
            ]);
            BalanceTransaction::create([
                'organization_id' => $org->id, 'billing_account_id' => $account->id, 'invoice_id' => $invoice->id,
                'transaction_type' => 'invoice', 'direction' => 'debit', 'amount_minor' => $total, 'currency' => $account->currency, 'description' => 'Invoice '.$invoice->invoice_number,
            ]);
            return $invoice;
        });
        return response()->json(['data' => $invoice->load('items')], 201);
    }

    public function payments(Request $request): JsonResponse
    {
        $org = $request->attributes->get('organization');
        return response()->json(['data' => Payment::with(['billingAccount.customer', 'allocations.invoice'])->where('organization_id', $org->id)->latest()->paginate($request->integer('per_page', 20))]);
    }

    public function storePayment(Request $request): JsonResponse
    {
        $data = $request->validate([
            'billing_account_id' => ['required', 'string'], 'invoice_id' => ['required', 'string'],
            'amount_minor' => ['required', 'integer', 'min:1'], 'payment_method' => ['required', 'string', 'max:50'],
            'reference' => ['nullable', 'string', 'max:190'], 'idempotency_key' => ['nullable', 'string', 'max:190'],
        ]);
        $org = $request->attributes->get('organization');
        $account = BillingAccount::where('organization_id', $org->id)->where('public_id', $data['billing_account_id'])->firstOrFail();
        $payment = DB::transaction(function () use ($org, $account, $data): Payment {
            $invoice = Invoice::where('organization_id', $org->id)->where('public_id', $data['invoice_id'])->lockForUpdate()->firstOrFail();
            abort_unless($invoice->billing_account_id === $account->id, 422, 'Invoice does not belong to the billing account.');
            if (!empty($data['idempotency_key'])) {
                $existing = Payment::where('organization_id', $org->id)->where('idempotency_key', $data['idempotency_key'])->first();
                if ($existing) return $existing;
            }
            $payment = Payment::create([
                'organization_id' => $org->id, 'billing_account_id' => $account->id,
                'payment_number' => 'PAY-'.now()->format('Ym').'-'.str_pad((string) (Payment::where('organization_id', $org->id)->count() + 1), 6, '0', STR_PAD_LEFT),
                'amount_minor' => $data['amount_minor'], 'currency' => $account->currency, 'payment_method' => $data['payment_method'],
                'reference' => $data['reference'] ?? null, 'idempotency_key' => $data['idempotency_key'] ?? null, 'status' => 'posted', 'received_at' => now(),
            ]);
            $allocation = min($payment->amount_minor, $invoice->balance_due_minor);
            PaymentAllocation::create(['organization_id' => $org->id, 'payment_id' => $payment->id, 'invoice_id' => $invoice->id, 'amount_minor' => $allocation]);
            $invoice->amount_paid_minor += $allocation;
            $invoice->balance_due_minor -= $allocation;
            $invoice->status = $invoice->balance_due_minor === 0 ? 'paid' : 'partially_paid';
            $invoice->save();
            BalanceTransaction::create([
                'organization_id' => $org->id, 'billing_account_id' => $account->id, 'invoice_id' => $invoice->id, 'payment_id' => $payment->id,
                'transaction_type' => 'payment', 'direction' => 'credit', 'amount_minor' => $allocation, 'currency' => $account->currency, 'description' => 'Payment '.$payment->payment_number,
            ]);
            if ($payment->amount_minor > $allocation) {
                $credit = $payment->amount_minor - $allocation;
                Credit::create(['organization_id' => $org->id, 'billing_account_id' => $account->id, 'source_type' => 'payment', 'source_id' => $payment->id, 'amount_minor' => $credit, 'remaining_minor' => $credit, 'reason' => 'Payment overpayment']);
            }
            return $payment;
        });
        return response()->json(['data' => $payment->load('allocations.invoice')], 201);
    }
}
