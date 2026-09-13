<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;

class ApiDocumentationController extends Controller
{
    public function index(): JsonResponse
    {
        return response()->json(['data' => [
            'title' => 'ISP Billing API',
            'base_url' => url('/api/v1'),
            'authentication' => 'Bearer <token>',
            'modules' => [
                'dashboard' => 'Dashboard', 'subscribers' => 'Subscribers', 'billing' => 'Billing', 'network' => 'Network', 'routers' => 'Routers', 'payment_gateway' => 'Payment Gateway', 'paymongo' => 'Paymongo', 'users' => 'Users', 'roles' => 'Roles', 'branding' => 'Branding', 'api_tokens' => 'API Tokens', 'email' => 'Email', 'notifications' => 'Notifications', 'audit_logs' => 'Audit logs',
            ],
            'endpoints' => [
                'GET /auth/me', 'GET|POST|PUT|DELETE /users', 'GET|POST|PUT|DELETE /roles', 'GET|PUT /branding', 'GET|PUT|POST /email', 'GET|PUT|POST /notifications', 'GET|PUT|POST /paymongo', 'POST /paymongo/test-payment', 'GET|POST|DELETE /api-tokens', 'GET /audit-logs', 'GET|POST|PUT|DELETE /customers', 'GET|POST /billing-accounts', 'GET /billing-settings', 'PUT /billing-settings', 'GET /plans', 'GET|POST /subscriber-services', 'GET|POST|PUT|DELETE /subscriptions', 'GET|POST /billing-statements', 'GET|POST /invoices', 'GET|POST /payments', 'GET /routers', 'POST /routers', 'GET /routers/{publicId}', 'PUT /routers/{publicId}', 'DELETE /routers/{publicId}', 'POST /routers/{publicId}/connection-test', 'GET /routers/{publicId}/system-info',
            ],
        ]]);
    }
}
