<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\SendTestEmailRequest;
use App\Http\Requests\UpdateEmailSettingsRequest;
use App\Mail\SmtpTestMail;
use App\Models\OrganizationEmailSetting;
use App\Services\AuditLogger;
use App\Services\OrganizationEmailService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Mail;

class EmailController extends Controller
{
    public function __construct(private OrganizationEmailService $email, private AuditLogger $auditLogger) {}

    public function show(Request $request): JsonResponse
    {
        return response()->json(['data' => ['settings' => $this->email->resolved($request->user())]]);
    }

    public function update(UpdateEmailSettingsRequest $request): JsonResponse
    {
        $user = $request->user();
        $settings = OrganizationEmailSetting::firstOrNew(['user_id' => $user->id]);
        $old = $this->email->resolved($user);
        $values = $request->validated();
        if (blank($values['password'] ?? null) && $settings->exists) unset($values['password']);
        if (array_key_exists('password', $values) && filled($values['password'])) $values['password'] = Crypt::encryptString($values['password']);
        $settings->fill($values)->save();
        $new = $this->email->resolved($user);
        $this->auditLogger->record($request, 'email.settings.updated', $settings, $old, $new);

        return response()->json(['data' => ['settings' => $new]]);
    }

    public function test(SendTestEmailRequest $request): JsonResponse
    {
        $user = $request->user();
        $settings = $user->emailSettings()->first();
        abort_unless($settings, 422, 'Save SMTP settings before sending a test email.');
        config([
            'mail.mailers.smtp.host' => $settings->host,
            'mail.mailers.smtp.port' => $settings->port,
            'mail.mailers.smtp.encryption' => $settings->encryption === 'none' ? null : $settings->encryption,
            'mail.mailers.smtp.username' => $settings->username,
            'mail.mailers.smtp.password' => $this->email->password($user),
            'mail.from.address' => $settings->from_email,
            'mail.from.name' => $settings->from_name,
        ]);
        Mail::to($request->validated('recipient'))->send(new SmtpTestMail(config('app.name')));
        $this->auditLogger->record($request, 'email.test_sent', $settings, [], ['recipient' => $request->validated('recipient')]);

        return response()->json(['data' => ['message' => 'Test email sent.']]);
    }
}
