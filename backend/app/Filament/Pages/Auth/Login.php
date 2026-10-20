<?php

namespace App\Filament\Pages\Auth;

use App\Support\AdminLoginThrottle;
use Filament\Auth\Http\Responses\Contracts\LoginResponse;
use Filament\Auth\Pages\Login as BaseLogin;
use Filament\Notifications\Notification;
use Filament\Schemas\Schema;
use Illuminate\Auth\Events\Lockout;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\ValidationException;
use SensitiveParameter;

/**
 * Administration sign-in (Filament). Keeps every A1 protection on top of Filament's own
 * login (Timebox of auth.timebox_duration, the same message for every failure, panel
 * access checked before signing in, session regenerated, Filament's 5 per minute per IP):
 *
 * - only accounts with is_admin = true can sign in (part of the credentials query, and
 *   User::canAccessPanel());
 * - no "remember me": the session ends with the normal session lifetime;
 * - failed attempts limited per email + client address (5 per minute), and all attempts per
 *   client address (20 per minute), with IPv6 clients grouped by /64 (App\Support\AdminLoginThrottle).
 */
class Login extends BaseLogin
{
    public function form(Schema $schema): Schema
    {
        return $schema->components([
            $this->getEmailFormComponent(),
            $this->getPasswordFormComponent(),
        ]);
    }

    public function authenticate(): ?LoginResponse
    {
        $email = is_string($this->data['email'] ?? null) ? $this->data['email'] : '';
        $ip = request()->ip();
        $emailKey = AdminLoginThrottle::emailKey($email, $ip);
        $ipKey = AdminLoginThrottle::addressKey($ip);

        foreach ([[$emailKey, AdminLoginThrottle::MAX_ATTEMPTS_PER_EMAIL], [$ipKey, AdminLoginThrottle::MAX_ATTEMPTS_PER_ADDRESS]] as [$key, $max]) {
            if (RateLimiter::tooManyAttempts($key, $max)) {
                event(new Lockout(request()));
                $this->sendThrottledNotification(RateLimiter::availableIn($key));

                return null;
            }
        }

        RateLimiter::hit($ipKey, AdminLoginThrottle::DECAY_SECONDS);

        try {
            $response = parent::authenticate();
        } catch (ValidationException $exception) {
            RateLimiter::hit($emailKey, AdminLoginThrottle::DECAY_SECONDS);

            throw $exception;
        }

        if ($response !== null) {
            RateLimiter::clear($emailKey);
        }

        return $response;
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function getCredentialsFromFormData(#[SensitiveParameter] array $data): array
    {
        return [
            'email' => $data['email'],
            'password' => $data['password'],
            // A regular account is treated exactly like an unknown email.
            'is_admin' => true,
        ];
    }

    private function sendThrottledNotification(int $seconds): void
    {
        Notification::make()
            ->title(__('filament-panels::auth/pages/login.notifications.throttled.title'))
            ->body(__('filament-panels::auth/pages/login.notifications.throttled.body', [
                'seconds' => $seconds,
                'minutes' => (int) ceil($seconds / 60),
            ]))
            ->danger()
            ->send();
    }
}
