<?php

namespace App\Support;

use Illuminate\Support\Str;

/**
 * Sign-in limits of the administration (Filament login, App\Filament\Pages\Auth\Login), on top
 * of Filament's own 5 attempts per minute per address:
 *
 * - failed attempts per email + client address: 5 per minute;
 * - all attempts per client address: 20 per minute.
 *
 * IPv6 clients are grouped by /64 (RateLimitAddress); IPv4 keys use the full address. The keys
 * are hashes, so neither the email nor the address is stored in the cache. The email key is
 * the same as the A1 key, so limits carry over unchanged.
 */
final class AdminLoginThrottle
{
    public const MAX_ATTEMPTS_PER_EMAIL = 5;

    public const MAX_ATTEMPTS_PER_ADDRESS = 20;

    public const DECAY_SECONDS = 60;

    public static function emailKey(string $email, ?string $ip): string
    {
        return 'admin-login:'.hash('sha256', Str::lower(trim($email)).'|'.RateLimitAddress::for($ip));
    }

    public static function addressKey(?string $ip): string
    {
        return 'admin-login-ip:'.hash('sha256', RateLimitAddress::for($ip));
    }
}
