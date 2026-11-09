<?php

namespace App\Http\Controllers;

use App\Support\ArenaPortal;
use App\Support\FrameProbe;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * The NAi browser: an external site framed inside the NAi shell.
 *
 * NAi keeps its own branding and side navigation around the frame. The frame
 * talks to the framed site directly from the browser, so its credentials
 * are never seen, stored, proxied, or replayed by NAi.
 */
class ArenaBrowserController extends Controller
{
    public function index(Request $request)
    {
        $url = ArenaPortal::url();

        // Probe server-side so the very first paint already knows whether the
        // frame can work. Previously this ran only from JS after the iframe had
        // started loading, so a refusal showed the browser's broken-page icon
        // for a moment (or forever, if the probe request itself failed).
        $probe = FrameProbe::check($url, $request->boolean('recheck'));

        return view('arena.browser', [
            'portalUrl' => $url,
            'portalHost' => ArenaPortal::host(),
            'probe' => $probe,
        ]);
    }

    /**
     * Ask whether a site is willing to be framed. Accepts an optional ?url= so
     * the address bar can check a destination before loading it; the target is
     * validated against private/internal networks inside FrameProbe.
     */
    public function probe(Request $request): JsonResponse
    {
        $data = $request->validate([
            'url' => ['nullable', 'string', 'max:500'],
        ]);

        $supplied = trim((string) ($data['url'] ?? ''));

        // Only fall back to the configured start address when nothing was asked
        // for. An explicitly supplied but invalid address must be refused, not
        // silently swapped for the default -- that would probe a host the caller
        // never named and report its reachability back as if it were the answer.
        $url = $supplied === ''
            ? ArenaPortal::url()
            : (ArenaPortal::sanitize($supplied) ?? $supplied);

        // An address the user typed is never probed if it points at something
        // internal or is not a public http(s) URL; report it as unknown rather
        // than leaking reachability.
        if (! FrameProbe::isSafeTarget($url)) {
            return response()->json([
                'status' => 'unknown',
                'reason' => '',
                'url' => $supplied === '' ? ArenaPortal::url() : $supplied,
                'final_url' => '',
            ]);
        }

        $result = FrameProbe::check($url, $request->boolean('recheck'));

        return response()->json($result + ['url' => $url]);
    }
}
