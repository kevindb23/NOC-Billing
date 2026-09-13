<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Route;

class ApiDocumentationController extends Controller
{
    public function index(): JsonResponse
    {
        return response()->json(['data' => [
            'title' => 'ISP Billing API',
            'base_url' => url('/api/v1'),
            'authentication' => 'Bearer <token>',
            'modules' => [
                'dashboard' => 'Dashboard', 'subscribers' => 'Subscribers', 'billing' => 'Billing', 'network' => 'Network', 'routers' => 'Routers', 'payment_gateway' => 'Payment Gateway', 'paymongo' => 'Paymongo', 'gcash' => 'GCash', 'users' => 'Users', 'roles' => 'Roles', 'branding' => 'Branding', 'api_tokens' => 'API Tokens', 'email' => 'Email', 'notifications' => 'Notifications', 'audit_logs' => 'Audit logs',
            ],
            'endpoints' => $this->endpoints(),
        ]]);
    }

    /**
     * Return the public API routes without Laravel's implicit HEAD variants or
     * the application-level api/v1 prefix.
     *
     * @return list<string>
     */
    private function endpoints(): array
    {
        $endpoints = [];

        foreach (Route::getRoutes() as $route) {
            $uri = $route->uri();

            if (! str_starts_with($uri, 'api/v1/')) {
                continue;
            }

            $path = '/'.substr($uri, strlen('api/v1/'));

            foreach (array_diff($route->methods(), ['HEAD']) as $method) {
                $endpoints[] = $method.' '.$path;
            }
        }

        sort($endpoints);

        return $endpoints;
    }
}
