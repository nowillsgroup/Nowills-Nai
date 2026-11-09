<?php

namespace App\Http\Middleware;

use App\Models\User;
use App\Support\Audit;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class PreviewDemoEntry
{
    /**
     * Allow the local Arena preview panel to land directly on a demo page.
     * This is intentionally opt-in and never active in production.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $previewEnabled = app()->environment('local')
            && config('nai.demo_mode')
            && config('nai.preview_auto_demo');

        if (! $request->user()) {
            if ($previewEnabled) {
                $user = User::where('email', 'demo@nowills.group')->first()
                    ?? User::where('role', 'admin')->first();

                if ($user) {
                    Auth::login($user, true);
                    $request->session()->regenerate();
                    Audit::log('auth.demo', 'Opened the demo workspace');
                } else {
                    return redirect()->guest(route('login'));
                }
            } else {
                return redirect()->guest(route('login'));
            }
        }

        if ($previewEnabled && $request->routeIs('dashboard')) {
            $landing = config('nai.preview_landing');
            if ($landing === 'arena') return redirect()->route('arena.index');
            if ($landing === 'browser') return redirect()->route('arena.browser');
        }

        return $next($request);
    }
}
