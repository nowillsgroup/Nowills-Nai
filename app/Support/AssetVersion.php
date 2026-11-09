<?php

namespace App\Support;

/**
 * Cache-busting for first-party CSS/JS.
 *
 * plain asset('css/nai.css') emits a bare, unversioned URL. On shared hosting
 * (cPanel/LiteSpeed, Cloudflare, or just a browser cache) that URL is cached
 * hard, so uploading a new release keeps serving the OLD stylesheet against
 * NEW markup. When the new markup needs rules the old file does not have, the
 * page renders unstyled -- which is exactly how the browser view collapsed to
 * a 300x150 iframe with a white address bar.
 *
 * Appending a short digest of the file's mtime+size means the URL changes the
 * moment the file changes, so caches are bypassed on deploy with no manual
 * purge. The digest is read from disk on every call (never memoised in a static)
 * so a long-running worker -- Octane, a queue worker -- cannot keep serving a
 * pre-deploy version. stat is cheap and PHP caches it per request. A missing
 * file simply yields the unversioned URL; this never throws.
 */
class AssetVersion
{
    public static function url(string $path): string
    {
        $path = ltrim($path, '/');
        $url = asset($path);

        try {
            $file = public_path($path);
            if (is_file($file)) {
                $stamp = @filemtime($file).':'.@filesize($file);
                $version = substr(hash('xxh128', $stamp), 0, 10);
                $url .= (str_contains($url, '?') ? '&' : '?').'v='.$version;
            }
        } catch (\Throwable) {
            // Never let asset versioning break a page render.
        }

        return $url;
    }
}
