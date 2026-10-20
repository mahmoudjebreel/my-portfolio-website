<?php

namespace Tests\Unit;

use App\Support\RateLimitAddress;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class RateLimitAddressTest extends TestCase
{
    /**
     * @return array<string, array{string}>
     */
    public static function ipv4Addresses(): array
    {
        return [
            'public' => ['203.0.113.10'],
            'private' => ['192.168.1.20'],
            'loopback' => ['127.0.0.1'],
            'zeros' => ['0.0.0.0'],
        ];
    }

    #[DataProvider('ipv4Addresses')]
    public function test_ipv4_addresses_are_unchanged(string $ip): void
    {
        $this->assertSame($ip, RateLimitAddress::for($ip));
    }

    public function test_ipv6_addresses_in_the_same_64_share_one_key(): void
    {
        $key = RateLimitAddress::for('2001:db8:1:2::1');

        $this->assertSame('2001:db8:1:2::/64', $key);
        foreach ([
            '2001:db8:1:2::ffff',
            '2001:db8:1:2:abcd:ef01:2345:6789',
            '2001:db8:1:2:ffff:ffff:ffff:ffff',
            '2001:0DB8:0001:0002:0000:0000:0000:0042', // expanded, upper case
        ] as $ip) {
            $this->assertSame($key, RateLimitAddress::for($ip), $ip);
        }
    }

    public function test_ipv6_addresses_in_different_64s_get_different_keys(): void
    {
        $this->assertSame('2001:db8:1:3::/64', RateLimitAddress::for('2001:db8:1:3::1'));
        $this->assertNotSame(RateLimitAddress::for('2001:db8:1:2::1'), RateLimitAddress::for('2001:db8:1:3::1'));
        // Differ only in the last bit of the 64-bit prefix.
        $this->assertNotSame(
            RateLimitAddress::for('2001:db8:0:0:ffff:ffff:ffff:ffff'),
            RateLimitAddress::for('2001:db8:0:1::'),
        );
        $this->assertSame('::/64', RateLimitAddress::for('::1'));
    }

    public function test_ipv4_mapped_ipv6_addresses_use_the_ipv4_address(): void
    {
        // Otherwise every IPv4 client of a dual-stack socket would share "::/64".
        $this->assertSame('203.0.113.10', RateLimitAddress::for('::ffff:203.0.113.10'));
        $this->assertSame('198.51.100.20', RateLimitAddress::for('::ffff:c633:6414'));
        $this->assertNotSame(RateLimitAddress::for('::ffff:203.0.113.10'), RateLimitAddress::for('::ffff:203.0.113.11'));
    }

    public function test_anything_else_is_returned_unchanged(): void
    {
        $this->assertSame('', RateLimitAddress::for(null));
        $this->assertSame('', RateLimitAddress::for(''));
        $this->assertSame('not-an-ip', RateLimitAddress::for('not-an-ip'));
        $this->assertSame('fe80::1%eth0', RateLimitAddress::for('fe80::1%eth0'));
        $this->assertSame('2001:db8::/64', RateLimitAddress::for('2001:db8::/64')); // not an address
    }
}
