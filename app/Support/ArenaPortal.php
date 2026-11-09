<?php

namespace App\Support;

use App\Models\Setting;

/**
 * Resolves the start address for the NAi browser view.
 *
 * NAi frames an external site so a person can sign in and work without
 * leaving the NAi shell. NAi never sees, stores, forwards, or replays that
 * site's credentials: the frame talks to it directly from the user's browser.
 * If the site refuses to be framed, the view offers a one-click exit to a
 * normal browser tab. The default target remains https://arena.ai.
 */
class ArenaPortal
{
    public const DEFAULT = 'https://arena.ai';

    public static function url(): string
    {
        // Security headers are emitted on every response, including the health
        // check before the database exists, so a missing table must not break it.
        try {
            $stored = Setting::where('key', 'arena_portal_url')->value('value');
        } catch (\Throwable) {
            $stored = null;
        }
        $candidate = $stored ?: config('nai.arena.portal_url', self::DEFAULT);

        return self::sanitize($candidate) ?? self::DEFAULT;
    }

    /** Scheme + host + port of the configured address, for the CSP frame-src allowlist. */
    public static function origin(): string
    {
        $parts = parse_url(self::url());
        $scheme = strtolower((string) ($parts['scheme'] ?? 'https'));
        $host = strtolower((string) ($parts['host'] ?? 'arena.ai'));
        $port = isset($parts['port']) ? ':'.$parts['port'] : '';

        return $scheme.'://'.$host.$port;
    }

    public static function host(): string
    {
        return strtolower((string) (parse_url(self::url(), PHP_URL_HOST) ?: 'arena.ai'));
    }

    /**
     * Only absolute http(s) URLs are allowed as frame targets. Anything else
     * (javascript:, data:, relative paths, protocol-relative) is rejected.
     */
    public static function sanitize(?string $url): ?string
    {
        $url = trim((string) $url);
        if ($url === '') return null;
        if (! preg_match('#^https?://#i', $url)) return null;
        if (filter_var($url, FILTER_VALIDATE_URL) === false) return null;

        return $url;
    }
}
