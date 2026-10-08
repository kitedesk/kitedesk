<?php

namespace App\Domain\Support;

use Closure;

/**
 * Guards outbound connections chosen by admins (webhook URLs, IMAP hosts) against reaching
 * internal services: loopback, private networks, link-local and cloud metadata addresses,
 * CGNAT, multicast and other reserved ranges, over IPv4 and IPv6.
 *
 * Self-hosted installs that need intranet targets can opt out with
 * `kitedesk.webhooks.allow_private_targets`.
 */
final class PublicNetwork
{
    /**
     * Ranges that never belong to a public service.
     */
    private const array BLOCKED_RANGES = [
        '0.0.0.0/8', '10.0.0.0/8', '100.64.0.0/10', '127.0.0.0/8', '169.254.0.0/16', '172.16.0.0/12',
        '192.0.0.0/24', '192.0.2.0/24', '192.88.99.0/24', '192.168.0.0/16', '198.18.0.0/15',
        '198.51.100.0/24', '203.0.113.0/24', '224.0.0.0/4', '240.0.0.0/4',
        '::/128', '::1/128', '::ffff:0:0/96', '64:ff9b::/96', '64:ff9b:1::/48', '100::/64', '2001::/23',
        '2001:db8::/32', '2002::/16', 'fc00::/7', 'fe80::/10', 'fec0::/10', 'ff00::/8',
    ];

    /**
     * @var (Closure(string): list<string>)|null
     */
    private static ?Closure $resolver = null;

    public static function allowsPrivateTargets(): bool
    {
        return (bool) config('kitedesk.webhooks.allow_private_targets', false);
    }

    /**
     * Whether connecting to this URL is allowed.
     */
    public static function allowsUrl(string $url): bool
    {
        $host = parse_url($url, PHP_URL_HOST);

        return is_string($host) && $host !== '' && self::allowsHost($host);
    }

    /**
     * Whether connecting to this host is allowed: every address it resolves to must be public.
     */
    public static function allowsHost(string $host): bool
    {
        return self::allowsPrivateTargets() || self::publicAddressesOf($host) !== null;
    }

    /**
     * The host's addresses when all of them are public, else null (unresolvable or internal).
     *
     * @return non-empty-list<string>|null
     */
    public static function publicAddressesOf(string $host): ?array
    {
        $host = trim($host, '[] ');
        $addresses = filter_var($host, FILTER_VALIDATE_IP) !== false ? [$host] : self::resolve($host);

        if ($addresses === []) {
            return null;
        }

        foreach ($addresses as $address) {
            if (! self::isPublicAddress($address)) {
                return null;
            }
        }

        return $addresses;
    }

    /**
     * cURL options that pin the URL's host to an address checked to be public, or null when the
     * target is internal or doesn't resolve. Pinning stops DNS rebinding between check and connect.
     *
     * @return array<int, mixed>|null
     */
    public static function pinnedCurlOptions(string $url): ?array
    {
        if (self::allowsPrivateTargets()) {
            return [];
        }

        $host = parse_url($url, PHP_URL_HOST);
        $addresses = is_string($host) ? self::publicAddressesOf($host) : null;

        if (! is_string($host) || $addresses === null) {
            return null;
        }

        if (filter_var(trim($host, '[]'), FILTER_VALIDATE_IP) !== false) {
            return [];
        }

        $port = parse_url($url, PHP_URL_PORT) ?: (parse_url($url, PHP_URL_SCHEME) === 'http' ? 80 : 443);
        $address = str_contains($addresses[0], ':') ? "[{$addresses[0]}]" : $addresses[0];

        return [CURLOPT_RESOLVE => ["{$host}:{$port}:{$address}"]];
    }

    public static function isPublicAddress(string $address): bool
    {
        if (filter_var($address, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) === false) {
            return false;
        }

        foreach (self::BLOCKED_RANGES as $range) {
            if (self::inRange($address, $range)) {
                return false;
            }
        }

        return true;
    }

    /**
     * Replace DNS resolution (tests); null restores the real resolver.
     *
     * @param  (Closure(string): list<string>)|null  $resolver
     */
    public static function resolveUsing(?Closure $resolver): void
    {
        self::$resolver = $resolver;
    }

    /**
     * @return list<string>
     */
    private static function resolve(string $host): array
    {
        if (self::$resolver !== null) {
            return (self::$resolver)($host);
        }

        $ipv4 = gethostbynamel($host) ?: [];
        $ipv6 = array_column(@dns_get_record($host, DNS_AAAA) ?: [], 'ipv6');

        return array_values(array_unique([...$ipv4, ...array_map('strval', $ipv6)]));
    }

    private static function inRange(string $address, string $range): bool
    {
        [$subnet, $bits] = explode('/', $range);
        $packedAddress = inet_pton($address);
        $packedSubnet = inet_pton($subnet);

        if ($packedAddress === false || $packedSubnet === false || strlen($packedAddress) !== strlen($packedSubnet)) {
            return false;
        }

        $bits = (int) $bits;
        $wholeBytes = intdiv($bits, 8);

        if (substr($packedAddress, 0, $wholeBytes) !== substr($packedSubnet, 0, $wholeBytes)) {
            return false;
        }

        if ($bits % 8 === 0) {
            return true;
        }

        $mask = 0xFF << (8 - $bits % 8) & 0xFF;

        return (ord($packedAddress[$wholeBytes]) & $mask) === (ord($packedSubnet[$wholeBytes]) & $mask);
    }
}
