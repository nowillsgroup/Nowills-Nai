<?php

namespace App\Http\Controllers;

use App\Support\ArenaPortal;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

/**
 * The Arena AI browser: the real Arena AI site framed inside the NAi shell.
 *
 * NAi keeps its own branding and side navigation around the frame. The frame
 * talks to Arena AI directly from the browser, so Arena AI credentials are
 * never seen, stored, proxied, or replayed by NAi.
 */
class ArenaBrowserController extends Controller
{
    public function index()
    {
        return view('arena.browser', [
            'portalUrl' => ArenaPortal::url(),
            'portalHost' => ArenaPortal::host(),
        ]);
    }

    /**
     * Ask Arena AI whether it is willing to be framed. Some sites send
     * X-Frame-Options / CSP frame-ancestors that make a browser refuse the
     * embed; when that happens the view offers a normal browser tab instead.
     * The answer is cached and never blocks the page from rendering.
     */
    public function probe(): JsonResponse
    {
        $url = ArenaPortal::url();
        $naiHost = strtolower((string) parse_url((string) config('app.url'), PHP_URL_HOST));

        $result = Cache::remember('nai.arena.frame-probe.'.md5($url.'|'.$naiHost), 600, function () use ($url, $naiHost): array {
            try {
                $response = Http::timeout(4)->withHeaders(['User-Agent' => 'NAi-Workspace/1.0'])->head($url);
                if (in_array($response->status(), [405, 501], true)) {
                    $response = Http::timeout(4)->withHeaders(['User-Agent' => 'NAi-Workspace/1.0'])->get($url);
                }

                $xfo = strtolower(trim((string) $response->header('X-Frame-Options')));
                if ($xfo !== '') {
                    if (str_starts_with($xfo, 'deny')) {
                        return ['status' => 'blocked', 'reason' => 'Arena AI sent X-Frame-Options: DENY.'];
                    }
                    if (str_starts_with($xfo, 'sameorigin')) {
                        return ['status' => 'blocked', 'reason' => 'Arena AI sent X-Frame-Options: SAMEORIGIN.'];
                    }
                    if (str_starts_with($xfo, 'allow-from')) {
                        $allowed = strtolower(trim(substr($xfo, strlen('allow-from'))));
                        $allowedHost = strtolower((string) parse_url($allowed, PHP_URL_HOST));

                        return $allowedHost !== '' && $allowedHost === $naiHost
                            ? ['status' => 'allowed', 'reason' => '']
                            : ['status' => 'blocked', 'reason' => 'Arena AI only allows framing from '.$allowed.'.'];
                    }
                }

                foreach ((array) $response->header('Content-Security-Policy') as $csp) {
                    if (! preg_match('/frame-ancestors\s+([^;]+)/i', (string) $csp, $m)) continue;
                    $sources = preg_split('/\s+/', trim(strtolower($m[1]))) ?: [];
                    if (in_array("'none'", $sources, true)) {
                        return ['status' => 'blocked', 'reason' => "Arena AI sent CSP frame-ancestors 'none'."];
                    }
                    foreach ($sources as $source) {
                        if ($source === '*') return ['status' => 'allowed', 'reason' => ''];
                        if ($source === "'self'") continue;
                        $host = strtolower((string) (parse_url($source, PHP_URL_HOST) ?: $source));
                        $host = preg_replace('/^\*\./', '', $host) ?? $host;
                        if ($naiHost !== '' && ($host === $naiHost || str_ends_with($naiHost, '.'.$host))) {
                            return ['status' => 'allowed', 'reason' => ''];
                        }
                    }

                    return ['status' => 'blocked', 'reason' => 'Arena AI sent a CSP frame-ancestors list that does not include this workspace.'];
                }

                return ['status' => 'allowed', 'reason' => ''];
            } catch (\Throwable) {
                return ['status' => 'unknown', 'reason' => ''];
            }
        });

        return response()->json($result + ['url' => $url]);
    }
}
