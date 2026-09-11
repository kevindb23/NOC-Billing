<?php

use App\Http\Controllers\Api\V1\CustomerController;
use App\Http\Controllers\Api\V1\BillingController;
use App\Http\Controllers\Api\V1\AuthController;
use App\Http\Controllers\Api\V1\AuditLogController;
use App\Http\Middleware\ResolveOrganization;
use Illuminate\Support\Facades\Route;

Route::prefix('v1/auth')->group(function (): void {
    Route::post('login', [AuthController::class, 'login']);
});

Route::prefix('v1')->middleware(['auth:sanctum', ResolveOrganization::class])->group(function (): void {
    Route::get('auth/me', [AuthController::class, 'me']);
    Route::post('auth/logout', [AuthController::class, 'logout']);
    Route::get('audit-logs', [AuditLogController::class, 'index']);
    Route::get('customers', [CustomerController::class, 'index']);
    Route::post('customers', [CustomerController::class, 'store']);
    Route::get('customers/{publicId}', [CustomerController::class, 'show']);
    Route::put('customers/{publicId}', [CustomerController::class, 'update']);
    Route::delete('customers/{publicId}', [CustomerController::class, 'destroy']);
    Route::get('billing-accounts', [BillingController::class, 'accounts']);
    Route::post('billing-accounts', [BillingController::class, 'storeAccount']);
    Route::get('plans', [BillingController::class, 'plans']);
    Route::get('subscriber-services', [BillingController::class, 'services']);
    Route::post('subscriber-services', [BillingController::class, 'storeService']);
    Route::get('subscriptions', [BillingController::class, 'subscriptions']);
    Route::post('subscriptions', [BillingController::class, 'storeSubscription']);
    Route::get('invoices', [BillingController::class, 'invoices']);
    Route::post('invoices', [BillingController::class, 'storeInvoice']);
    Route::get('payments', [BillingController::class, 'payments']);
    Route::post('payments', [BillingController::class, 'storePayment']);
});
