<?php

namespace App\Support;

use Illuminate\Support\Facades\Http;

/**
 * Determines whether an external site is willing to be displayed in a frame.
 *
 * A site that sends `X-Frame-Options: DENY/SAMEORIGIN` or a CSP
 * `frame-ancestors` list that excludes this workspace CANNOT be embedded.
 * That refusal is enforced by the visitor's browser, not by NAi, and there is
 * no client-side workaround. The only way to defeat it would be to proxy the
 * site through NAi and strip the headers -- which would put NAi in the path of
 * the user's credentials. NAi deliberately does not do that. Instead it reports
 * the refusal honestly and offers a real browser window.
 *
 * Results are cached so page renders never wait on the network for long.
 */
class FrameProbe
{
    /**
     * @return array{status:string,reason:string,final_url:string}
     */
    public static function check(string $url, bool $refresh = false): array
    {
        $url = ArenaPortal::sanitize($url) ?? ArenaPortal::DEFAULT;
        $naiHost = strtolower((string) parse_url((string) config('app.url'), PHP_URL_HOST));
        $key = 'nai.frame-probe.'.md5($url.'|'.$naiHost);

        if ($refresh) {
            cache()->forget($key);
        }

        $result = cache()->remember($key, 600, fn () => self::probe($url, $naiHost));

        // Normalise: callers (including the Blade view) rely on all three keys.
        return [
            'status' => (string) ($result['status'] ?? 'unknown'),
            'reason' => (string) ($result['reason'] ?? ''),
            'final_url' => (string) ($result['final_url'] ?? '') ?: $url,
        ];
    }

    /**
     * @return array{status:string,reason:string,final_url:string}
     */
    private static function probe(string $url, string $naiHost): array
    {
        $current = $url;

        try {
            // Follow redirects by hand so every hop can be re-validated. Guzzle's
            // automatic following would let a public host bounce the probe into
            // the private network.
            for ($hop = 0; $hop < 4; $hop++) {
                if (! self::isSafeTarget($current) || ! self::resolvesToPublicAddress($current)) {
                    return self::result('unknown', '', $current);
                }

                $usedGet = false;
                $response = Http::timeout(4)
                    ->withoutRedirecting()
                    ->withHeaders(['User-Agent' => 'Mozilla/5.0 (compatible; NAi-Workspace/1.0)'])
                    ->head($current);

                // Some servers reject or under-report on HEAD.
                if (in_array($response->status(), [400, 403, 405, 501], true)) {
                    $response = Http::timeout(4)
                        ->withoutRedirecting()
                        ->withHeaders(['User-Agent' => 'Mozilla/5.0 (compatible; NAi-Workspace/1.0)'])
                        ->get($current);
                    $usedGet = true;
                }

                if ($response->redirect()) {
                    $location = trim((string) $response->header('Location'));
                    if ($location === '') break;
                    $next = self::absolute($location, $current);
                    if ($next === null) break;
                    $current = $next;

                    continue;
                }

                $verdict = self::headers($response->headers(), $naiHost);

                // Absence of a blocking header on HEAD is not proof: plenty of
                // apps only add XFO/CSP to real GET responses. Confirm with a GET
                // before promising the user an embeddable page.
                if ($verdict['status'] === 'allowed' && ! $usedGet) {
                    $get = Http::timeout(4)
                        ->withoutRedirecting()
                        ->withHeaders(['User-Agent' => 'Mozilla/5.0 (compatible; NAi-Workspace/1.0)'])
                        ->get($current);
                    if ($get->successful()) {
                        $verdict = self::headers($get->headers(), $naiHost);
                    }
                }

                return array_merge($verdict, ['final_url' => $current]);
            }

            return self::result('unknown', '', $current);
        } catch (\Throwable) {
            // Offline, DNS failure, TLS blocked, timeout: do not guess.
            return self::result('unknown', '', $current);
        }
    }

    /**
     * @param  array<string,array<int,string>>  $headers
     * @return array{status:string,reason:string}
     */
    private static function headers(array $headers, string $naiHost): array
    {
        $lower = [];
        foreach ($headers as $name => $values) {
            $lower[strtolower((string) $name)] = array_map('strval', (array) $values);
        }

        $xfo = strtolower(trim($lower['x-frame-options'][0] ?? ''));
        if ($xfo !== '') {
            if (str_starts_with($xfo, 'deny')) {
                return self::result('blocked', 'This site sent X-Frame-Options: DENY, so browsers will not display it inside another site.');
            }
            if (str_starts_with($xfo, 'sameorigin')) {
                return self::result('blocked', 'This site sent X-Frame-Options: SAMEORIGIN, so it only permits framing from its own origin.');
            }
            if (str_starts_with($xfo, 'allow-from')) {
                $allowed = strtolower(trim(substr($xfo, strlen('allow-from'))));
                $allowedHost = strtolower((string) parse_url($allowed, PHP_URL_HOST));
                if ($allowedHost !== '' && $allowedHost === $naiHost) {
                    return self::result('allowed', '');
                }

                return self::result('blocked', 'This site only permits framing from '.$allowed.'.');
            }
        }

        foreach ($lower['content-security-policy'] ?? [] as $csp) {
            if (! preg_match('/frame-ancestors\s+([^;]+)/i', $csp, $m)) {
                continue;
            }
            $sources = preg_split('/\s+/', trim(strtolower($m[1]))) ?: [];
            if (in_array("'none'", $sources, true)) {
                return self::result('blocked', "This site sent CSP frame-ancestors 'none', so it refuses all embedding.");
            }
            foreach ($sources as $source) {
                if ($source === '*') return self::result('allowed', '');
                if ($source === "'self'") continue;
                $host = strtolower((string) (parse_url($source, PHP_URL_HOST) ?: $source));
                $host = preg_replace('/^\*\./', '', $host) ?? $host;
                if ($naiHost !== '' && ($host === $naiHost || str_ends_with($naiHost, '.'.$host))) {
                    return self::result('allowed', '');
                }
            }

            return self::result('blocked', 'This site sent a CSP frame-ancestors list that does not include this workspace.');
        }

        return self::result('allowed', '');
    }

    /**
     * Cheap, deterministic URL-shape check: public http(s) on 80/443, no
     * localhost/.local/.internal names, and no literal private/reserved IP.
     * Hostname resolution is checked separately by resolvesToPublicAddress()
     * right before each request, so this method never touches the network.
     */
    public static function isSafeTarget(string $url): bool
    {
        if (ArenaPortal::sanitize($url) === null) return false;

        $parts = parse_url($url);
        $host = strtolower((string) ($parts['host'] ?? ''));
        if ($host === '') return false;

        $scheme = strtolower((string) ($parts['scheme'] ?? ''));
        if (! in_array($scheme, ['http', 'https'], true)) return false;

        $port = (int) ($parts['port'] ?? ($scheme === 'https' ? 443 : 80));
        if (! in_array($port, [80, 443], true)) return false;

        $host = trim($host, '[]');
        if ($host === 'localhost' || str_ends_with($host, '.localhost')) return false;
        if (str_ends_with($host, '.local') || str_ends_with($host, '.internal')) return false;

        // A literal IP is decided here; a hostname is resolved at request time.
        if (filter_var($host, FILTER_VALIDATE_IP)) {
            return self::isPublicIp($host);
        }

        // Reject names that can only ever mean "this machine / this network".
        if (! str_contains($host, '.') || str_ends_with($host, '.')) return false;

        return true;
    }

    /**
     * Resolve the host and refuse anything landing in private/reserved space.
     * Runs immediately before each outbound request (and on every redirect hop)
     * so a public name cannot be pointed at an internal address between the
     * shape check and the connection.
     */
    private static function resolvesToPublicAddress(string $url): bool
    {
        $host = trim(strtolower((string) parse_url($url, PHP_URL_HOST)), '[]');
        if ($host === '') return false;

        $ips = filter_var($host, FILTER_VALIDATE_IP) ? [$host] : self::resolve($host);
        if ($ips === []) return false;

        foreach ($ips as $ip) {
            if (! self::isPublicIp($ip)) return false;
        }

        return true;
    }

    private static function isPublicIp(string $ip): bool
    {
        return filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) !== false;
    }

    /**
     * Override hostname resolution. Used by the test suite so verdicts never
     * depend on a runner's DNS, and available to callers that need a custom
     * resolver. Pass null to restore native resolution.
     *
     * @param  (callable(string):array<int,string>)|null  $resolver
     */
    public static function resolveUsing(?callable $resolver): void
    {
        self::$resolver = $resolver;
    }

    /** @var (callable(string):array<int,string>)|null */
    private static $resolver = null;

    /** @return array<int,string> */
    private static function resolve(string $host): array
    {
        if (self::$resolver !== null) {
            return array_values(array_unique((array) (self::$resolver)($host)));
        }

        $ips = [];

        try {
            $v4 = gethostbynamel($host);
            if (is_array($v4)) $ips = array_merge($ips, $v4);

            $records = @dns_get_record($host, DNS_AAAA);
            if (is_array($records)) {
                foreach ($records as $r) {
                    if (! empty($r['ipv6'])) $ips[] = (string) $r['ipv6'];
                }
            }
        } catch (\Throwable) {
            return [];
        }

        return array_values(array_unique($ips));
    }

    private static function absolute(string $location, string $base): ?string
    {
        if (preg_match('#^https?://#i', $location)) return $location;

        $p = parse_url($base);
        if (! is_array($p) || empty($p['host'])) return null;
        $origin = ($p['scheme'] ?? 'https').'://'.$p['host'].(isset($p['port']) ? ':'.$p['port'] : '');

        if (str_starts_with($location, '//')) return ($p['scheme'] ?? 'https').':'.$location;
        if (str_starts_with($location, '/')) return $origin.$location;

        $dir = rtrim(dirname($p['path'] ?? '/'), '/');

        return $origin.$dir.'/'.$location;
    }

    /** @return array{status:string,reason:string,final_url:string} */
    private static function result(string $status, string $reason, string $finalUrl = ''): array
    {
        return ['status' => $status, 'reason' => $reason, 'final_url' => $finalUrl];
    }
}
