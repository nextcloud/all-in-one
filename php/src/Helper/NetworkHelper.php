<?php
declare(strict_types=1);

namespace AIO\Helper;

class NetworkHelper {
    /**
     * Resolve a hostname to its IP address, trying IPv4 first and falling back
     * to IPv6 (AAAA record) when no A record is found.  Returns the hostname
     * unchanged when neither record resolves successfully.
     */
    public static function resolveHostname(string $hostname): string {
        $ipv4 = gethostbyname($hostname);
        if ($ipv4 !== $hostname) {
            return $ipv4;
        }
        $records = dns_get_record($hostname, DNS_AAAA);
        if (is_array($records) && isset($records[0]['ipv6']) && $records[0]['ipv6'] !== '') {
            return $records[0]['ipv6'];
        }
        return $hostname;
    }
}
