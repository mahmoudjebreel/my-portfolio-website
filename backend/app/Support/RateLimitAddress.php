<?php

namespace App\Support;

/**
 * The client address used in rate-limit keys.
 *
 * - IPv4: unchanged.
 * - IPv6: its /64 network, e.g. "2001:db8:1:2::/64". A single subscriber or host
 *   usually controls a whole /64 and can rotate through its addresses freely, so
 *   limiting per full IPv6 address would not limit anything.
 * - IPv4-mapped IPv6 (::ffff:a.b.c.d, seen on dual-stack sockets): the IPv4
 *   address, so those clients keep their own key instead of sharing "::/64".
 * - Anything else (null, empty, unparsable, zone ids): returned as it was, so the
 *   key is exactly what it was before this normalization existed.
 */
final class RateLimitAddress
{
    public static function for(?string $ip): string
    {
        if ($ip === null || filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6) === false) {
            return (string) $ip;
        }

        $packed = inet_pton($ip);
        if ($packed === false || strlen($packed) !== 16) {
            return $ip;
        }

        if (str_starts_with($packed, str_repeat("\0", 10)."\xff\xff")) {
            return (string) inet_ntop(substr($packed, 12));
        }

        return inet_ntop(substr($packed, 0, 8).str_repeat("\0", 8)).'/64';
    }
}
