<?php

namespace App\Http\Controllers;

use App\Models\Setting;
use App\Models\User;
use App\Support\Audit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    public function show()
    {
        return view('auth.login', [
            'workspace' => Setting::where('key', 'workspace_name')->value('value') ?? config('nai.workspace'),
            'demoMode' => (bool) config('nai.demo_mode'),
        ]);
    }

    public function login(Request $request)
    {
        $credentials = $request->validate(['email' => ['required', 'email'], 'password' => ['required', 'string']]);

        // Per-account + per-IP limiter with an actionable message, behind the route-level throttle.
        $key = 'nai-login:'.Str::lower($credentials['email']).'|'.$request->ip();
        if (RateLimiter::tooManyAttempts($key, 5)) {
            $seconds = RateLimiter::availableIn($key);
            throw ValidationException::withMessages([
                'email' => 'Too many sign-in attempts for this account. Try again in '.$seconds.' second'.($seconds === 1 ? '' : 's').'.',
            ]);
        }

        if (! Auth::attempt($credentials, $request->boolean('remember'))) {
            RateLimiter::hit($key, 60);
            Audit::log('auth.failed', 'Failed sign-in attempt for '.$credentials['email']);
            // Deliberately identical for unknown accounts and wrong passwords.
            throw ValidationException::withMessages(['email' => 'The provided credentials do not match our records.']);
        }
        RateLimiter::clear($key);
        $request->session()->regenerate();
        Audit::log('auth.login', 'Signed in to NAi');
        return redirect()->intended(route('dashboard'));
    }

    public function demo(Request $request)
    {
        abort_unless(config('nai.demo_mode'), 404);
        $user = User::where('email', 'demo@nowills.group')->first() ?? User::where('role', 'admin')->first();
        abort_unless($user, 404);
        Auth::login($user, true);
        $request->session()->regenerate();
        Audit::log('auth.demo', 'Opened the demo workspace');
        return redirect()->route('dashboard');
    }

    public function logout(Request $request)
    {
        Audit::log('auth.logout', 'Signed out of NAi');
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();
        return redirect()->route('login');
    }
}
