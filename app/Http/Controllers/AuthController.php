<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Support\Audit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    public function show()
    {
        return view('auth.login');
    }

    public function login(Request $request)
    {
        $credentials = $request->validate(['email' => ['required', 'email'], 'password' => ['required', 'string']]);
        if (! Auth::attempt($credentials, $request->boolean('remember'))) {
            throw ValidationException::withMessages(['email' => 'The provided credentials do not match our records.']);
        }
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
