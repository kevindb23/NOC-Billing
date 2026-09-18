<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\UpdatePaymongoSettingsRequest;
use App\Http\Requests\CreatePaymongoTestPaymentRequest;
use App\Models\OrganizationPaymongoSetting;
use App\Services\AuditLogger;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class PaymongoController extends Controller
{
    public function __construct(private AuditLogger $auditLogger) {}

    public function show(Request $request): JsonResponse
    {
        return response()->json(['data' => ['settings' => $this->present($this->settings($request))]]);
    }

    public function update(UpdatePaymongoSettingsRequest $request): JsonResponse
    {
        $settings = $this->settings($request);
        $old = $this->present($settings);
        $values = $request->validated();
        foreach (['secret_key', 'webhook_secret'] as $field) {
            if (blank($values[$field] ?? null)) {
                unset($values[$field]);
            } else {
                $values[$field] = Crypt::encryptString($values[$field]);
            }
        }
        $settings->fill($values)->save();
        $this->auditLogger->record($request, 'paymongo.settings.updated', $settings, $old, $this->present($settings));

        return response()->json(['data' => ['settings' => $this->present($settings)]]);
    }

    public function test(Request $request): JsonResponse
    {
        $settings = $this->settings($request);
        abort_unless($settings->exists && filled($settings->public_key) && filled($settings->secret_key), 422, 'Save Paymongo credentials before testing the connection.');
        $this->auditLogger->record($request, 'paymongo.tested', $settings, [], ['environment' => $settings->environment]);

        return response()->json(['data' => ['message' => 'Paymongo configuration is valid.']]);
    }

    public function testPayment(CreatePaymongoTestPaymentRequest $request): JsonResponse
    {
        $settings = $this->settings($request);
        abort_unless($settings->exists && $settings->environment === 'test' && filled($settings->secret_key), 422, 'Save Paymongo test credentials before creating a test payment.');
        $secret = Crypt::decryptString($settings->secret_key);
        $data = $request->validated();
        $response = Http::withBasicAuth($secret, '')
            ->acceptJson()
            ->post('https://api.paymongo.com/v1/checkout_sessions', [
                'data' => ['attributes' => [
                    'line_items' => [[
                        'currency' => $data['currency'], 'amount' => $data['amount'], 'name' => $data['description'], 'quantity' => 1,
                    ]],
                    'payment_method_types' => ['card', 'gcash', 'grab_pay'],
                    'description' => $data['description'],
                    'send_email_receipt' => false,
                    'show_description' => true,
                    'show_line_items' => true,
                    'success_url' => rtrim(config('app.frontend_url'), '/').'/?paymongo_payment=success',
                    'cancel_url' => rtrim(config('app.frontend_url'), '/').'/?paymongo_payment=cancelled',
                ]],
            ]);
        if ($response->failed()) {
            $providerMessage = data_get($response->json(), 'errors.0.detail')
                ?? data_get($response->json(), 'errors.0.code')
                ?? 'Paymongo could not create the test payment.';
            Log::warning('Paymongo test payment failed', ['status' => $response->status(), 'message' => $providerMessage]);
            abort(422, $providerMessage);
        }
        $payment = $response->json('data');
        $this->auditLogger->record($request, 'paymongo.test_payment_created', null, [], ['checkout_session_id' => $payment['id'] ?? null, 'amount' => $data['amount'], 'currency' => $data['currency']]);

        return response()->json(['data' => ['id' => $payment['id'] ?? null, 'status' => data_get($payment, 'attributes.status'), 'checkout_url' => data_get($payment, 'attributes.checkout_url'), 'message' => 'Test checkout session created.']]);
    }

    private function settings(Request $request): OrganizationPaymongoSetting
    {
        return OrganizationPaymongoSetting::firstOrNew([
            'user_id' => $request->user()->id,
        ]);
    }

    private function present(OrganizationPaymongoSetting $settings): array
    {
        return [
            'enabled' => (bool) ($settings->enabled ?? false),
            'environment' => $settings->environment ?? 'test',
            'public_key' => $settings->public_key ?? '',
            'secret_key_configured' => filled($settings->secret_key),
            'webhook_secret_configured' => filled($settings->webhook_secret),
        ];
    }
}
