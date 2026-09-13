<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\BalanceTransaction;
use App\Models\BillingAccount;
use App\Models\BillingStatement;
use App\Models\BillingCycle;
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
use App\Models\OrganizationBillingSetting;
use App\Services\NumberGenerator;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class BillingController extends Controller
{
    private function calculateNextBillingDate(string $startsOn, int $cycleStartDay): string
    {
        $starts = \Illuminate\Support\Carbon::parse($startsOn)->startOfDay();
        $candidate = $starts->copy()->day(min($cycleStartDay, $starts->daysInMonth));
        if ($candidate->lte($starts)) {
            $candidate = $candidate->addMonth()->day(min($cycleStartDay, $candidate->daysInMonth));
        }
        return $candidate->toDateString();
    }

    public function billingSettings(Request $request): JsonResponse { return response()->json(['data' => OrganizationBillingSetting::firstOrCreate([])]); }
    public function updateBillingSettings(Request $request): JsonResponse { $settings = OrganizationBillingSetting::firstOrCreate([]); $settings->update($request->validate(['cycle_start_day' => ['required','integer','min:1','max:28'], 'vat_rate' => ['required','numeric','min:0','max:100'], 'installation_amortization_months' => ['required','integer','min:0','max:120']])); return response()->json(['data' => $settings->fresh()]); }
    public function statements(Request $request): JsonResponse { return response()->json(['data' => BillingStatement::with(['billingAccount.customer', 'invoice', 'subscription.planVersion.plan'])->latest()->paginate($request->integer('per_page', 20))]); }
    public function generateStatement(Request $request): JsonResponse { $data = $request->validate(['subscription_id' => ['required','integer'], 'issue_date' => ['required','date']]); $settings = OrganizationBillingSetting::firstOrCreate([]); $subscription = Subscription::with(['billingAccount','service.customer','planVersion'])->findOrFail($data['subscription_id']); $issue = \Illuminate\Support\Carbon::parse($data['issue_date']); $cycleStart = $issue->copy()->day(min($settings->cycle_start_day, $issue->daysInMonth)); $periodStart = $issue->lt($cycleStart) ? $cycleStart->copy()->subMonth() : $cycleStart; $periodEnd = $periodStart->copy()->addMonth()->subDay(); $daysInPeriod = $periodStart->daysInMonth; $prorated = $subscription->starts_on->gt($periodStart) && $subscription->starts_on->lt($periodEnd) ? (int) round($subscription->price_snapshot_minor * $subscription->starts_on->diffInDays($periodEnd->copy()->addDay()) / $daysInPeriod) : 0; $subtotal = $subscription->price_snapshot_minor + $prorated + (int) round(($subscription->planVersion?->setup_fee_minor ?? 0) / max(1, $settings->installation_amortization_months)); $tax = (int) round($subtotal * ($settings->vat_rate / 100)); $total = $subtotal + $tax; $statement = BillingStatement::create(['billing_account_id'=>$subscription->billing_account_id,'subscription_id'=>$subscription->id,'statement_number'=>NumberGenerator::next('billing_statements:'.now()->format('Ym'),'BS-'.now()->format('Ym').'-','billing_statements','statement_number'),'status'=>'open','issue_date'=>$data['issue_date'],'due_date'=>$periodStart->copy()->addDays(7),'billing_period_start'=>$periodStart,'billing_period_end'=>$periodEnd,'currency'=>$subscription->currency_snapshot,'subtotal_minor'=>$subtotal,'tax_minor'=>$tax,'total_minor'=>$total,'balance_due_minor'=>$total]); return response()->json(['data'=>$statement->load(['billingAccount.customer'])],201); }

    public function destroyStatement(Request $request, string $publicId): \Illuminate\Http\Response
    {
        $statement = BillingStatement::where('public_id', $publicId)->firstOrFail();
        abort_if($statement->invoice_id || $statement->status === 'paid', 422, 'A paid billing statement cannot be deleted.');
        $statement->delete();
        return response()->noContent();
    }
    public function accounts(Request $request): JsonResponse
    {
        return response()->json(['data' => BillingAccount::with('customer')->latest()->paginate($request->integer('per_page', 20))]);
    }

    public function storeAccount(Request $request): JsonResponse
    {
        $data = $request->validate(['customer_id' => ['required', 'string']]);
        $customer = Customer::where('public_id', $data['customer_id'])->firstOrFail();
        $account = BillingAccount::create([
            'customer_id' => $customer->id,
            'account_number' => NumberGenerator::next('billing_accounts', 'BA-', 'billing_accounts', 'account_number'),
            'currency' => config('app.currency', 'PHP'),
            'status' => 'active',
        ]);
        return response()->json(['data' => $account->load('customer')], 201);
    }

    public function destroyAccount(Request $request, string $publicId): \Illuminate\Http\Response
    {
        $account = BillingAccount::where('public_id', $publicId)->firstOrFail();
        $hasReferences = $account->subscriberServices()->exists()
            || $account->subscriptions()->exists()
            || $account->invoices()->exists()
            || $account->billingStatements()->exists()
            || $account->payments()->exists()
            || collect(['credits', 'adjustments', 'balance_transactions'])
                ->contains(fn (string $table): bool => Schema::hasTable($table)
                    && Schema::hasColumn($table, 'billing_account_id')
                    && DB::table($table)->where('billing_account_id', $account->id)->exists());

        abort_if($hasReferences, 422, 'This billing account cannot be deleted because it is linked to subscriber services or financial records.');

        $account->delete();

        return response()->noContent();
    }

    public function plans(Request $request): JsonResponse
    {
        return response()->json(['data' => Plan::with('versions', 'billingCycle')->latest()->paginate($request->integer('per_page', 20))]);
    }

    public function storePlan(Request $request): JsonResponse
    {
        $data = $request->validate(['code' => ['required', 'string', 'max:100'], 'name' => ['required', 'string', 'max:150'], 'price' => ['required', 'numeric', 'min:0'], 'currency' => ['sometimes', 'string', 'size:3'], 'service_type' => ['required', 'in:prepaid,postpaid'], 'description' => ['nullable', 'string'], 'status' => ['required', 'in:active,inactive']]);
        $cycle = BillingCycle::query()->orderBy('id')->firstOrFail();
        $plan = DB::transaction(function () use ($data, $cycle): Plan {
            $plan = Plan::create(['billing_cycle_id' => $cycle->id, 'code' => $data['code'], 'name' => $data['name'], 'service_type' => $data['service_type'], 'description' => $data['description'] ?? null, 'status' => $data['status']]);
            $plan->versions()->create(['version' => 1, 'recurring_price_minor' => (int) round($data['price'] * 100), 'setup_fee_minor' => 0, 'currency' => strtoupper($data['currency'] ?? 'PHP'), 'download_kbps' => 0, 'upload_kbps' => 0, 'effective_from' => now()->toDateString(), 'status' => 'active']);
            return $plan;
        });
        return response()->json(['data' => $plan->load('versions', 'billingCycle')], 201);
    }

    public function updatePlan(Request $request, string $publicId): JsonResponse
    {
        $data = $request->validate(['code' => ['sometimes', 'string', 'max:100'], 'name' => ['sometimes', 'string', 'max:150'], 'price' => ['sometimes', 'numeric', 'min:0'], 'currency' => ['sometimes', 'string', 'size:3'], 'service_type' => ['sometimes', 'in:prepaid,postpaid'], 'description' => ['nullable', 'string'], 'status' => ['sometimes', 'in:active,inactive']]);
        $plan = Plan::where('public_id', $publicId)->firstOrFail(); $plan->update(collect($data)->except(['price', 'currency'])->all());
        $version = $plan->versions()->latest('version')->firstOrFail(); $versionData = []; if (array_key_exists('price', $data)) $versionData['recurring_price_minor'] = (int) round($data['price'] * 100); if (array_key_exists('currency', $data)) $versionData['currency'] = strtoupper($data['currency']); if ($versionData) $version->update($versionData);
        return response()->json(['data' => $plan->fresh()->load('versions', 'billingCycle')]);
    }

    public function destroyPlan(Request $request, string $publicId): \Illuminate\Http\Response
    {
        $plan = Plan::where('public_id', $publicId)->firstOrFail();
        abort_if($plan->versions()->whereHas('subscriptions')->exists(), 422, 'This plan cannot be deleted because it is assigned to a subscriber. Change the plan status or update the subscriber plan instead.');
        DB::transaction(function () use ($plan): void {
            $plan->versions()->delete();
            $plan->delete();
        });
        return response()->noContent();
    }

    public function services(Request $request): JsonResponse
    {
        return response()->json(['data' => SubscriberService::with(['customer', 'billingAccount'])->latest()->paginate($request->integer('per_page', 20))]);
    }

    public function storeService(Request $request): JsonResponse
    {
        $data = $request->validate([
            'customer_id' => ['required', 'string'],
            'billing_account_id' => ['required', 'string'],
            'service_type' => ['nullable', 'string', 'max:50'],
        ]);
        $customer = Customer::where('public_id', $data['customer_id'])->firstOrFail();
        $account = BillingAccount::where('public_id', $data['billing_account_id'])->firstOrFail();
        abort_unless($account->customer_id === $customer->id, 422, 'Billing account does not belong to the customer.');
        $service = SubscriberService::create([
            'customer_id' => $customer->id, 'billing_account_id' => $account->id,
            'service_number' => NumberGenerator::next('subscriber_services', 'SVC-', 'subscriber_services', 'service_number'),
            'service_type' => $data['service_type'] ?? 'internet', 'status' => 'pending',
        ]);
        return response()->json(['data' => $service->load(['customer', 'billingAccount'])], 201);
    }

    public function subscriptions(Request $request): JsonResponse
    {
        return response()->json(['data' => Subscription::with(['service.customer', 'service.billingAccount', 'planVersion.plan'])->latest()->paginate($request->integer('per_page', 20))]);
    }

    public function storeSubscription(Request $request): JsonResponse
    {
        $data = $request->validate([
            'subscriber_id' => ['nullable', 'string'], 'subscriber_service_id' => ['nullable', 'string'], 'billing_account_id' => ['nullable', 'string'],
            'plan_version_id' => ['required', 'integer'], 'starts_on' => ['required', 'date'],
        ]);
        $settings = OrganizationBillingSetting::firstOrCreate([]);
        if (! empty($data['subscriber_id'])) {
            $customer = Customer::where('public_id', $data['subscriber_id'])->firstOrFail();
            $account = $customer->billingAccounts()->latest()->first();
            if (! $account) {
                $account = BillingAccount::create([
                    'customer_id' => $customer->id,
                    'account_number' => NumberGenerator::next('billing_accounts', 'BA-', 'billing_accounts', 'account_number'),
                    'currency' => config('app.currency', 'PHP'),
                    'status' => 'active',
                ]);
            }
            $service = $customer->subscriberServices()->whereIn('status', ['active', 'pending'])->latest()->first();
            if (! $service) {
                $service = SubscriberService::create([
                    'customer_id' => $customer->id,
                    'billing_account_id' => $account->id,
                    'service_number' => NumberGenerator::next('subscriber_services', 'SVC-', 'subscriber_services', 'service_number'),
                    'service_type' => 'internet',
                    'status' => 'pending',
                ]);
            }
        } else {
            abort_unless(! empty($data['subscriber_service_id']) && ! empty($data['billing_account_id']), 422, 'A subscriber is required.');
            $service = SubscriberService::where('public_id', $data['subscriber_service_id'])->firstOrFail();
            $account = BillingAccount::where('public_id', $data['billing_account_id'])->firstOrFail();
        }
        $version = PlanVersion::with('plan')->findOrFail($data['plan_version_id']);
        abort_if($version->plan->status !== 'active', 422, 'Inactive plans cannot be assigned to a subscriber. Activate the plan before creating a subscription.');
        abort_unless($service->billing_account_id === $account->id, 422, 'Service does not belong to the billing account.');
        $cycleStartDay = (int) ($settings->cycle_start_day ?: 20);
        $subscription = Subscription::create([
            'subscriber_service_id' => $service->id, 'billing_account_id' => $account->id,
            'plan_version_id' => $version->id, 'status' => 'active', 'starts_on' => $data['starts_on'], 'next_billing_date' => $this->calculateNextBillingDate($data['starts_on'], $cycleStartDay),
            'billing_day' => $cycleStartDay, 'price_snapshot_minor' => $version->recurring_price_minor, 'currency_snapshot' => $version->currency,
            'plan_name_snapshot' => $version->plan->name,
        ]);
        $this->createInitialBillingStatement($subscription->fresh('planVersion'), $settings, $data['starts_on']);
        return response()->json(['data' => $subscription->load(['service', 'planVersion.plan'])], 201);
    }

    public function updateSubscription(Request $request, int $id): JsonResponse
    {
        $data = $request->validate(['status' => ['sometimes', 'string', 'in:active,suspended,cancelled,ended'], 'ends_on' => ['nullable', 'date'], 'starts_on' => ['sometimes', 'date']]);
        $subscription = Subscription::findOrFail($id);
        if (isset($data['starts_on'])) {
            $settings = OrganizationBillingSetting::firstOrCreate([]);
            $cycleStartDay = (int) ($settings->cycle_start_day ?: 20);
            $data['next_billing_date'] = $this->calculateNextBillingDate($data['starts_on'], $cycleStartDay);
            $data['billing_day'] = $cycleStartDay;
        }
        $subscription->update($data);
        return response()->json(['data' => $subscription->fresh()->load(['service', 'planVersion.plan'])]);
    }

    public function destroySubscription(Request $request, int $id): \Illuminate\Http\Response
    {
        $subscription = Subscription::findOrFail($id);
        abort_if($subscription->invoiceItems()->exists(), 422, 'Subscription cannot be deleted while invoice items reference it.');
        $subscription->delete();
        return response()->noContent();
    }

    private function createInitialBillingStatement(Subscription $subscription, OrganizationBillingSetting $settings, string $issueDate): BillingStatement
    {
        $issue = \Illuminate\Support\Carbon::parse($issueDate);
        $cycleStart = $issue->copy()->day(min($settings->cycle_start_day, $issue->daysInMonth));
        $periodStart = $issue->lt($cycleStart) ? $cycleStart->copy()->subMonth() : $cycleStart;
        $periodEnd = $periodStart->copy()->addMonth()->subDay();
        $prorated = $subscription->starts_on->gt($periodStart) && $subscription->starts_on->lt($periodEnd)
            ? (int) round($subscription->price_snapshot_minor * $subscription->starts_on->diffInDays($periodEnd->copy()->addDay()) / $periodStart->daysInMonth)
            : 0;
        $amortized = (int) round(($subscription->planVersion?->setup_fee_minor ?? 0) / max(1, $settings->installation_amortization_months));
        $subtotal = $subscription->price_snapshot_minor + $prorated + $amortized;
        $tax = (int) round($subtotal * ($settings->vat_rate / 100));
        $total = $subtotal + $tax;
        $prefix = 'BS-'.now()->format('Ym').'-';
        return BillingStatement::create(['billing_account_id' => $subscription->billing_account_id, 'subscription_id' => $subscription->id, 'statement_number' => NumberGenerator::next('billing_statements:'.now()->format('Ym'), $prefix, 'billing_statements', 'statement_number'), 'status' => 'open', 'issue_date' => $issueDate, 'due_date' => $periodStart->copy()->addDays(7), 'billing_period_start' => $periodStart, 'billing_period_end' => $periodEnd, 'currency' => $subscription->currency_snapshot, 'subtotal_minor' => $subtotal, 'tax_minor' => $tax, 'total_minor' => $total, 'balance_due_minor' => $total]);
    }

    public function invoices(Request $request): JsonResponse
    {
        return response()->json(['data' => Invoice::with('billingAccount.customer')->latest()->paginate($request->integer('per_page', 20))]);
    }

    public function storeInvoice(Request $request): JsonResponse
    {
        $data = $request->validate([
            'billing_account_id' => ['required', 'string'], 'subscription_id' => ['required', 'integer'],
            'issue_date' => ['required', 'date'], 'due_date' => ['required', 'date', 'after_or_equal:issue_date'],
        ]);
        $account = BillingAccount::where('public_id', $data['billing_account_id'])->firstOrFail();
        $subscription = Subscription::findOrFail($data['subscription_id']);
        abort_unless($subscription->billing_account_id === $account->id, 422, 'Subscription does not belong to the billing account.');
        $invoice = DB::transaction(function () use ($account, $subscription, $data): Invoice {
            $total = $subscription->price_snapshot_minor;
            $invoice = Invoice::create([
                'billing_account_id' => $account->id,
                'invoice_number' => NumberGenerator::next('invoices:'.now()->format('Ym'), 'INV-'.now()->format('Ym').'-', 'invoices', 'invoice_number'),
                'status' => 'open', 'issue_date' => $data['issue_date'], 'due_date' => $data['due_date'],
                'billing_period_start' => $data['issue_date'], 'billing_period_end' => $data['due_date'],
                'currency' => $account->currency, 'subtotal_minor' => $total, 'total_minor' => $total, 'balance_due_minor' => $total,
            ]);
            $invoice->items()->create([
                'subscription_id' => $subscription->id, 'description' => $subscription->plan_name_snapshot,
                'quantity' => 1, 'unit_amount_minor' => $total, 'line_total_minor' => $total,
                'service_period_start' => $data['issue_date'], 'service_period_end' => $data['due_date'],
            ]);
            BalanceTransaction::create([
                'billing_account_id' => $account->id, 'invoice_id' => $invoice->id,
                'transaction_type' => 'invoice', 'direction' => 'debit', 'amount_minor' => $total, 'currency' => $account->currency, 'description' => 'Invoice '.$invoice->invoice_number,
            ]);
            return $invoice;
        });
        return response()->json(['data' => $invoice->load('items')], 201);
    }

    public function payments(Request $request): JsonResponse
    {
        return response()->json(['data' => Payment::with(['billingAccount.customer', 'allocations.invoice'])->latest()->paginate($request->integer('per_page', 20))]);
    }

    public function storePayment(Request $request): JsonResponse
    {
        $data = $request->validate([
            'billing_account_id' => ['required', 'string'], 'invoice_id' => ['nullable', 'string', 'required_without:statement_id'], 'statement_id' => ['nullable', 'string', 'required_without:invoice_id'],
            'amount_minor' => ['required', 'integer', 'min:1'], 'payment_method' => ['required', 'string', 'max:50'],
            'reference' => ['nullable', 'string', 'max:190'], 'idempotency_key' => ['nullable', 'string', 'max:190'],
        ]);
        $account = BillingAccount::where('public_id', $data['billing_account_id'])->firstOrFail();
        $payment = DB::transaction(function () use ($account, $data): Payment {
            $invoice = !empty($data['invoice_id']) ? Invoice::where('public_id', $data['invoice_id'])->lockForUpdate()->firstOrFail() : null;
            $statement = !empty($data['statement_id']) ? BillingStatement::where('public_id', $data['statement_id'])->lockForUpdate()->firstOrFail() : null;
            if ($statement) {
                abort_unless($statement->billing_account_id === $account->id, 422, 'Statement does not belong to the billing account.');
                abort_if($statement->invoice_id, 422, 'This billing statement has already generated an invoice.');
                $invoice = Invoice::create(['billing_account_id' => $account->id, 'invoice_number' => NumberGenerator::next('invoices:'.now()->format('Ym'), 'INV-'.now()->format('Ym').'-', 'invoices', 'invoice_number'), 'status' => 'open', 'issue_date' => $statement->issue_date, 'due_date' => $statement->due_date, 'billing_period_start' => $statement->billing_period_start, 'billing_period_end' => $statement->billing_period_end, 'currency' => $statement->currency, 'subtotal_minor' => $statement->subtotal_minor, 'tax_minor' => $statement->tax_minor, 'total_minor' => $statement->total_minor, 'balance_due_minor' => $statement->total_minor]);
                $statement->update(['invoice_id' => $invoice->id]);
                $invoice->items()->create(['subscription_id' => $statement->subscription_id, 'description' => 'Billing statement '.$statement->statement_number, 'quantity' => 1, 'unit_amount_minor' => $statement->subtotal_minor, 'line_total_minor' => $statement->subtotal_minor, 'tax_minor' => $statement->tax_minor, 'service_period_start' => $statement->billing_period_start, 'service_period_end' => $statement->billing_period_end]);
            }
            abort_unless($invoice->billing_account_id === $account->id, 422, 'Invoice does not belong to the billing account.');
            if (!empty($data['idempotency_key'])) {
                $existing = Payment::where('idempotency_key', $data['idempotency_key'])->first();
                if ($existing) return $existing;
            }
            $payment = Payment::create([
                'billing_account_id' => $account->id,
                'payment_number' => NumberGenerator::next('payments:'.now()->format('Ym'), 'PAY-'.now()->format('Ym').'-', 'payments', 'payment_number'),
                'amount_minor' => $data['amount_minor'], 'currency' => $account->currency, 'payment_method' => $data['payment_method'],
                'reference' => $data['reference'] ?? null, 'idempotency_key' => $data['idempotency_key'] ?? null, 'status' => 'posted', 'received_at' => now(),
            ]);
            $allocation = min($payment->amount_minor, $invoice->balance_due_minor);
            PaymentAllocation::create(['payment_id' => $payment->id, 'invoice_id' => $invoice->id, 'amount_minor' => $allocation]);
            $invoice->amount_paid_minor += $allocation;
            $invoice->balance_due_minor -= $allocation;
            $invoice->status = $invoice->balance_due_minor === 0 ? 'paid' : 'partially_paid';
            $invoice->save();
            if ($statement && $invoice->balance_due_minor === 0) $statement->update(['status' => 'paid', 'amount_paid_minor' => $invoice->amount_paid_minor, 'balance_due_minor' => 0, 'paid_at' => now()]);
            BalanceTransaction::create([
                'billing_account_id' => $account->id, 'invoice_id' => $invoice->id, 'payment_id' => $payment->id,
                'transaction_type' => 'payment', 'direction' => 'credit', 'amount_minor' => $allocation, 'currency' => $account->currency, 'description' => 'Payment '.$payment->payment_number,
            ]);
            if ($payment->amount_minor > $allocation) {
                $credit = $payment->amount_minor - $allocation;
                Credit::create(['billing_account_id' => $account->id, 'source_type' => 'payment', 'source_id' => $payment->id, 'amount_minor' => $credit, 'remaining_minor' => $credit, 'reason' => 'Payment overpayment']);
            }
            return $payment;
        });
        return response()->json(['data' => $payment->load('allocations.invoice')], 201);
    }
}
