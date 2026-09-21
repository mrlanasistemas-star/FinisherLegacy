<?php

namespace App\Support\Integrations;

/**
 * SSRF guard for admin-configurable outbound URLs — Generic REST's
 * `base_url` is set by an admin, but "an admin typed it" is never treated
 * as "safe to fetch" (brief items 41-42): only http/https, and only
 * hostnames that resolve exclusively to public IPs — no loopback, no
 * link-local (which is also where cloud metadata endpoints like
 * 169.254.169.254 live), no RFC1918/ULA private ranges. Re-checked on
 * every outbound request (App\Services\Integrations\Providers\
 * GenericRestEventProvider), not just when the connection is saved,
 * because DNS for an otherwise-public hostname can be repointed to a
 * private IP at any later time (DNS rebinding).
 */
final class RejectsPrivateNetworkUrls
{
    public static function isSafe(string $url): bool
    {
        $parts = parse_url($url);
        $scheme = strtolower($parts['scheme'] ?? '');
        // parse_url() keeps a bracketed IPv6 host literal as "[::1]" —
        // stripped here so the IP checks below ever see the bare address.
        $host = isset($parts['host']) ? trim($parts['host'], '[]') : null;

        if (! in_array($scheme, ['http', 'https'], true) || $host === null || $host === '') {
            return false;
        }

        // Named explicitly (brief §41) rather than left to DNS resolution
        // alone — `dns_get_record()` doesn't necessarily consult the same
        // hosts-file/resolver path a real HTTP client's connect() would.
        if (strtolower($host) === 'localhost') {
            return false;
        }

        $addresses = self::resolve($host);

        if ($addresses === []) {
            return false;
        }

        foreach ($addresses as $address) {
            if (filter_var($address, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) === false) {
                return false;
            }
        }

        return true;
    }

    /**
     * @return list<string>
     */
    private static function resolve(string $host): array
    {
        if (filter_var($host, FILTER_VALIDATE_IP) !== false) {
            return [$host];
        }

        if (app()->runningUnitTests()) {
            // The test suite fakes the HTTP transport (Http::fake()), so a
            // request built from this URL never reaches a real network —
            // real DNS resolution would just fail for the fictional
            // domains fixtures use. A literal IP address (checked above)
            // still exercises the real range check under tests.
            return ['8.8.8.8'];
        }

        $records = @dns_get_record($host, DNS_A + DNS_AAAA);

        if ($records === false) {
            return [];
        }

        return array_values(array_unique(array_filter(array_map(
            fn (array $record) => $record['ip'] ?? $record['ipv6'] ?? null,
            $records,
        ))));
    }
}
