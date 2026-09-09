<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreCustomerRequest;
use App\Models\Customer;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CustomerController extends Controller
{
    public function store(StoreCustomerRequest $request): JsonResponse
    {
        $organization = $request->attributes->get('organization');
        $customer = Customer::create([
            ...$request->validated(),
            'organization_id' => $organization->id,
            'customer_number' => $this->nextCustomerNumber($organization->id),
        ]);

        return response()->json(['data' => $customer->fresh(), 'correlation_id' => $request->header('X-Request-Id')], 201);
    }

    public function index(Request $request): JsonResponse
    {
        $organization = $request->attributes->get('organization');
        $query = Customer::query()->where('organization_id', $organization->id);
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
        $customer->update($request->validate([
            'customer_type' => ['sometimes', 'string', 'in:residential,business'],
            'legal_name' => ['sometimes', 'string', 'max:190'],
            'first_name' => ['nullable', 'string', 'max:100'], 'last_name' => ['nullable', 'string', 'max:100'],
            'email' => ['nullable', 'email', 'max:190'], 'phone' => ['nullable', 'string', 'max:40'],
            'status' => ['sometimes', 'string', 'in:active,inactive'], 'notes' => ['nullable', 'string'],
        ]));
        return response()->json(['data' => $customer->fresh()]);
    }

    public function destroy(Request $request, string $publicId): \Illuminate\Http\Response
    {
        $this->customer($request, $publicId)->delete();
        return response()->noContent();
    }

    private function customer(Request $request, string $publicId): Customer
    {
        return Customer::where('organization_id', $request->attributes->get('organization')->id)->where('public_id', $publicId)->firstOrFail();
    }

    private function nextCustomerNumber(int $organizationId): string
    {
        $next = Customer::withTrashed()->where('organization_id', $organizationId)->count() + 1;
        return 'CUS-'.str_pad((string) $next, 6, '0', STR_PAD_LEFT);
    }
}
