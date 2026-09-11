<?php

use App\Http\Controllers\Api\V1\AuditLogController;
use App\Http\Controllers\Api\V1\AuthController;
use App\Http\Controllers\Api\V1\BillingController;
use App\Http\Controllers\Api\V1\CustomerController;
use App\Http\Controllers\Api\V1\PermissionController;
use App\Http\Controllers\Api\V1\RoleController;
use App\Http\Controllers\Api\V1\UserController;
use App\Http\Middleware\ResolveOrganization;
use Illuminate\Support\Facades\Route;

Route::prefix('v1/auth')->group(function (): void {
    Route::post('login', [AuthController::class, 'login']);
});

Route::prefix('v1')->middleware(['auth:sanctum', ResolveOrganization::class])->group(function (): void {
    Route::get('auth/me', [AuthController::class, 'me']);
    Route::post('auth/logout', [AuthController::class, 'logout']);
    Route::get('permissions', [PermissionController::class, 'index'])->middleware('permission:roles.view');
    Route::get('roles', [RoleController::class, 'index'])->middleware('permission:roles.view');
    Route::post('roles', [RoleController::class, 'store'])->middleware('permission:roles.create');
    Route::get('roles/{id}', [RoleController::class, 'show'])->middleware('permission:roles.view');
    Route::put('roles/{id}', [RoleController::class, 'update'])->middleware('permission:roles.update');
    Route::delete('roles/{id}', [RoleController::class, 'destroy'])->middleware('permission:roles.delete');
    Route::get('audit-logs', [AuditLogController::class, 'index']);
    Route::get('users', [UserController::class, 'index'])->middleware('permission:users.view');
    Route::post('users', [UserController::class, 'store'])->middleware('permission:users.create');
    Route::get('users/{publicId}', [UserController::class, 'show'])->middleware('permission:users.view');
    Route::put('users/{publicId}', [UserController::class, 'update'])->middleware('permission:users.update');
    Route::delete('users/{publicId}', [UserController::class, 'destroy'])->middleware('permission:users.delete');
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
