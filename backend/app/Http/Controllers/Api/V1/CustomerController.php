<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreCustomerRequest;
use App\Models\BillingAccount;
use App\Models\Customer;
use App\Models\SubscriberService;
use App\Services\NumberGenerator;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class CustomerController extends Controller
{
    public function store(StoreCustomerRequest $request): JsonResponse
    {
        $customer = Customer::create([
            ...$request->validated(),
            'customer_number' => $this->nextCustomerNumber(),
        ]);

        return response()->json(['data' => $customer->fresh(), 'correlation_id' => $request->header('X-Request-Id')], 201);
    }

    public function index(Request $request): JsonResponse
    {
        $query = Customer::with(['subscriberServices.subscriptions']);
        if ($search = $request->string('search')->toString()) {
            $query->where(function ($builder) use ($search) {
                $builder->where('legal_name', 'like', "%{$search}%")
                    ->orWhere('customer_number', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%");
            });
        }

        return response()->json(['data' => $query->latest()->paginate($request->integer('per_page', 20))]);
    }

    public function show(Request $request, string $publicId): JsonResponse
    {
        return response()->json(['data' => $this->customer($request, $publicId)->load(['billingAccounts', 'subscriberServices'])]);
    }

    public function update(Request $request, string $publicId): JsonResponse
    {
        $customer = $this->customer($request, $publicId);
        $values = $request->validate([
            'customer_type' => ['sometimes', 'string', 'in:residential,business,corporate'],
            'legal_name' => ['sometimes', 'string', 'max:190'],
            'first_name' => ['nullable', 'string', 'max:100'], 'last_name' => ['nullable', 'string', 'max:100'],
            'email' => ['nullable', 'email', 'max:190'], 'phone' => ['nullable', 'string', 'max:40'],
            'portal_username' => ['sometimes', 'string', 'max:120'], 'portal_password' => ['nullable', 'string', 'max:255'],
            'ppp_username' => ['sometimes', 'string', 'max:120'], 'ppp_password' => ['nullable', 'string', 'max:255'],
            'status' => ['sometimes', 'string', 'in:active,inactive'], 'notes' => ['nullable', 'string'],
        ]);
        foreach (['portal_password', 'ppp_password'] as $key) if (array_key_exists($key, $values) && blank($values[$key])) unset($values[$key]);
        $customer->update($values);
        return response()->json(['data' => $customer->fresh()]);
    }

    public function destroy(Request $request, string $publicId): \Illuminate\Http\Response
    {
        $customer = $this->customer($request, $publicId);
        if ($request->boolean('permanent')) {
            $accountIds = $customer->billingAccounts()->pluck('id');
            $serviceIds = $customer->subscriberServices()->pluck('id');
            $hasBillingHistory = $accountIds->isNotEmpty() && (
                (Schema::hasTable('billing_statements')
                    && DB::table('billing_statements')->whereIn('billing_account_id', $accountIds)
                        ->where(function ($query): void {
                            $query->where('balance_due_minor', '>', 0)
                                ->orWhereIn('status', ['pending', 'open', 'overdue']);
                        })->exists())
                || (Schema::hasTable('invoices')
                    && DB::table('invoices')->whereIn('billing_account_id', $accountIds)
                        ->where(function ($query): void {
                            $query->where('balance_due_minor', '>', 0)
                                ->orWhereIn('status', ['pending', 'open', 'overdue', 'draft']);
                        })->exists())
            );
            $hasServiceHistory = $serviceIds->isNotEmpty() && DB::table('subscriptions')->whereIn('subscriber_service_id', $serviceIds)->exists();
            abort_if($hasBillingHistory || $hasServiceHistory, 422, 'This subscriber cannot be permanently deleted while billing records or subscriptions reference it.');
            DB::transaction(function () use ($customer, $accountIds, $serviceIds): void {
                SubscriberService::whereIn('id', $serviceIds)->delete();
                BillingAccount::whereIn('id', $accountIds)->delete();
                $customer->forceDelete();
            });
        } else {
            $customer->delete();
        }
        return response()->noContent();
    }

    private function customer(Request $request, string $publicId): Customer
    {
        return Customer::where('public_id', $publicId)->firstOrFail();
    }

    private function nextCustomerNumber(): string
    {
        return NumberGenerator::next('customers', 'CUS-', 'customers', 'customer_number');
    }
}
