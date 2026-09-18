<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Facades\Crypt;

class OrganizationEmailService
{
    public function resolved(User $user): array
    {
        $settings = $user->emailSettings()->first();

        return [
            'provider' => $settings?->provider ?? 'gmail',
            'host' => $settings?->host ?? 'smtp.gmail.com',
            'port' => $settings?->port ?? 587,
            'encryption' => $settings?->encryption ?? 'tls',
            'username' => $settings?->username ?? '',
            'password_configured' => filled($settings?->password),
            'from_name' => $settings?->from_name ?? config('app.name'),
            'from_email' => $settings?->from_email ?? '',
        ];
    }

    public function password(User $user): ?string
    {
        $password = $user->emailSettings()->first()?->password;
        return filled($password) ? Crypt::decryptString($password) : null;
    }
}
