<?php
/**
 * Client IP resolver for WP Puller.
 *
 * Determines the real client IP for rate limiting, honouring trusted reverse
 * proxies and CDNs (Cloudflare, Sucuri, Akamai, a site's own nginx/HAProxy,
 * etc.). Forwarding headers are ONLY trusted when the connecting IP
 * (REMOTE_ADDR) falls inside a trusted proxy range — otherwise any client
 * could spoof, say, X-Forwarded-For to evade the rate limiter or poison logs.
 *
 * @package WP_Puller
 * @since 1.0.8
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * WP_Puller_Client_IP Class.
 */
class WP_Puller_Client_IP {

    /**
     * Transient key for the cached Cloudflare ranges.
     *
     * @var string
     */
    const CF_CACHE_KEY = 'wp_puller_cloudflare_ips';

    /**
     * Resolve the real client IP.
     *
     * @return string Client IP, or '' if it cannot be determined.
     */
    public static function get() {
        $remote = isset( $_SERVER['REMOTE_ADDR'] )
            ? trim( (string) wp_unslash( $_SERVER['REMOTE_ADDR'] ) )
            : '';

        if ( '' === $remote || ! self::is_valid_ip( $remote ) ) {
            return '';
        }

        // Only consult forwarding headers when the connection itself comes
        // from a trusted proxy/CDN. This is what prevents header spoofing.
        if ( self::ip_in_ranges( $remote, self::trusted_ranges() ) ) {
            foreach ( self::trusted_headers() as $header ) {
                $key = 'HTTP_' . strtoupper( str_replace( '-', '_', $header ) );

                if ( empty( $_SERVER[ $key ] ) ) {
                    continue;
                }

                // X-Forwarded-For is a list "client, proxy1, proxy2"; the
                // left-most entry is the original client.
                $value = (string) wp_unslash( $_SERVER[ $key ] );
                $first = trim( explode( ',', $value )[0] );

                if ( self::is_valid_ip( $first ) ) {
                    return $first;
                }
            }
        }

        return $remote;
    }

    /**
     * Forwarding headers to consult, in priority order.
     *
     * Covers Cloudflare/Sucuri (CF-Connecting-IP), Akamai & Cloudflare
     * Enterprise (True-Client-IP), and the generic standard (X-Forwarded-For).
     * Extend via the `wp_puller_client_ip_headers` filter for other vendors.
     *
     * @return string[]
     */
    private static function trusted_headers() {
        return apply_filters(
            'wp_puller_client_ip_headers',
            array(
                'CF-Connecting-IP',
                'True-Client-IP',
                'X-Forwarded-For',
            )
        );
    }

    /**
     * CIDR ranges whose forwarding headers we trust.
     *
     * Defaults to loopback + RFC1918 private ranges (a site fronted by its own
     * reverse proxy) plus the live Cloudflare ranges. Add Sucuri, Akamai,
     * StackPath, custom load balancers, etc. via `wp_puller_trusted_proxies`.
     *
     * @return string[]
     */
    private static function trusted_ranges() {
        $ranges = array_merge(
            array(
                '127.0.0.0/8',
                '::1/128',
                '10.0.0.0/8',
                '172.16.0.0/12',
                '192.168.0.0/16',
                'fc00::/7',
            ),
            self::cloudflare_ranges()
        );

        return apply_filters( 'wp_puller_trusted_proxies', $ranges );
    }

    /**
     * Cloudflare IP ranges, refreshed weekly and cached.
     *
     * @return string[]
     */
    private static function cloudflare_ranges() {
        $cached = get_transient( self::CF_CACHE_KEY );

        if ( is_array( $cached ) && ! empty( $cached ) ) {
            return $cached;
        }

        $ranges = self::fetch_cloudflare_ranges();

        if ( empty( $ranges ) ) {
            $ranges = self::cloudflare_fallback();
        }

        set_transient( self::CF_CACHE_KEY, $ranges, WEEK_IN_SECONDS );

        return $ranges;
    }

    /**
     * Fetch the current Cloudflare ranges from cloudflare.com.
     *
     * @return string[] Empty array on any failure (caller falls back).
     */
    private static function fetch_cloudflare_ranges() {
        $out = array();

        foreach ( array( 'https://www.cloudflare.com/ips-v4', 'https://www.cloudflare.com/ips-v6' ) as $url ) {
            $resp = wp_safe_remote_get( $url, array( 'timeout' => 5 ) );

            if ( is_wp_error( $resp ) || 200 !== wp_remote_retrieve_response_code( $resp ) ) {
                return array();
            }

            $body = trim( wp_remote_retrieve_body( $resp ) );

            foreach ( preg_split( '/\s+/', $body ) as $cidr ) {
                $cidr = trim( $cidr );
                if ( '' !== $cidr && false !== strpos( $cidr, '/' ) ) {
                    $out[] = $cidr;
                }
            }
        }

        return $out;
    }

    /**
     * Hard-coded fallback Cloudflare ranges (https://www.cloudflare.com/ips/).
     *
     * @return string[]
     */
    private static function cloudflare_fallback() {
        return array(
            '173.245.48.0/20',
            '103.21.244.0/22',
            '103.22.200.0/22',
            '103.31.4.0/22',
            '141.101.64.0/18',
            '108.162.192.0/18',
            '190.93.240.0/20',
            '188.114.96.0/20',
            '197.234.240.0/22',
            '198.41.128.0/17',
            '162.158.0.0/15',
            '104.16.0.0/13',
            '104.24.0.0/14',
            '172.64.0.0/13',
            '131.0.72.0/22',
            '2400:cb00::/32',
            '2606:4700::/32',
            '2803:f800::/32',
            '2405:b500::/32',
            '2405:8100::/32',
            '2a06:98c0::/29',
            '2c0f:f248::/32',
        );
    }

    /**
     * Validate an IPv4/IPv6 address.
     *
     * @param string $ip IP address.
     * @return bool
     */
    public static function is_valid_ip( $ip ) {
        return false !== filter_var( $ip, FILTER_VALIDATE_IP );
    }

    /**
     * Is $ip inside any of the given CIDR ranges?
     *
     * @param string   $ip     IP address.
     * @param string[] $ranges CIDR ranges.
     * @return bool
     */
    public static function ip_in_ranges( $ip, $ranges ) {
        foreach ( (array) $ranges as $range ) {
            if ( self::ip_in_cidr( $ip, $range ) ) {
                return true;
            }
        }

        return false;
    }

    /**
     * CIDR membership test supporting both IPv4 and IPv6.
     *
     * @param string $ip   IP address.
     * @param string $cidr CIDR range or bare IP.
     * @return bool
     */
    public static function ip_in_cidr( $ip, $cidr ) {
        if ( false === strpos( $cidr, '/' ) ) {
            return $ip === $cidr;
        }

        list( $subnet, $bits ) = explode( '/', $cidr, 2 );
        $bits = (int) $bits;

        $ip_bin     = @inet_pton( $ip );
        $subnet_bin = @inet_pton( $subnet );

        // Both must parse and belong to the same family (4 vs 16 bytes).
        if ( false === $ip_bin || false === $subnet_bin || strlen( $ip_bin ) !== strlen( $subnet_bin ) ) {
            return false;
        }

        $whole = intdiv( $bits, 8 );
        $rem   = $bits % 8;

        if ( $whole > 0 && 0 !== substr_compare( $ip_bin, $subnet_bin, 0, $whole ) ) {
            return false;
        }

        if ( $rem > 0 ) {
            $mask = chr( ( 0xff << ( 8 - $rem ) ) & 0xff );
            if ( ( ord( $ip_bin[ $whole ] ) & ord( $mask ) ) !== ( ord( $subnet_bin[ $whole ] ) & ord( $mask ) ) ) {
                return false;
            }
        }

        return true;
    }
}
