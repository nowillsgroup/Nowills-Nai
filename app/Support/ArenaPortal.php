<?php

namespace App\Support;

use App\Models\Setting;

/**
 * Resolves the public Arena AI website that the NAi launcher opens.
 *
 * NAi is the branded interface; this is the human-facing Arena AI site the
 * user signs into directly in a new tab. NAi never handles Arena AI
 * credentials — the launcher is a plain, safe outbound link.
 */
class ArenaPortal
{
    public const DEFAULT = 'https://arena.ai';

    public static function url(): string
    {
        $stored = Setting::where('key', 'arena_portal_url')->value('value');
        $candidate = $stored ?: config('nai.arena.portal_url', self::DEFAULT);

        return self::sanitize($candidate) ?? self::DEFAULT;
    }

    /**
     * Only absolute http(s) URLs are allowed as launcher targets. Anything
     * else (javascript:, data:, relative paths, protocol-relative) is rejected.
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
