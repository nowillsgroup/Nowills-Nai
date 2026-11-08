<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="description" content="Sign in to NAi — the Nowills Group command center for applications, databases, repositories, infrastructure, and development projects, orchestrated through NAi.">
    <meta name="theme-color" content="#05070f">
    <title>Sign in · NAi</title>
    <link rel="icon" type="image/png" href="{{ asset('images/favicon.png') }}">
    <link rel="preload" href="{{ asset('fonts/Inter-Regular.woff2') }}" as="font" type="font/woff2" crossorigin>
    <link rel="preload" href="{{ asset('fonts/Inter-SemiBold.woff2') }}" as="font" type="font/woff2" crossorigin>
    <link rel="stylesheet" href="{{ asset('css/nai.css') }}">
</head>
<body>
<div class="login-shell">
    <canvas class="login-canvas" id="loginMesh" aria-hidden="true"></canvas>
    <div class="login-aurora" aria-hidden="true"><i></i><i></i><i></i></div>
    <div class="login-grid-overlay" aria-hidden="true"></div>

    <section class="login-story">
        <div class="login-brand">
            <img src="{{ asset('images/nai-logo.png') }}" alt="NAi — Nowills AI">
            <div class="login-brand-tag">Nowills Group<br>Enterprise workspace</div>
        </div>

        <span class="login-eyebrow"><span class="pulse"></span>AI orchestration layer</span>

        <h1>Your entire technical estate, <span class="gradient-text">in one command center.</span></h1>
        <p class="lede">NAi unifies applications, repositories, databases, hosting, and delivery projects — then lets you direct development work with full operational context.</p>

        <div class="login-pillars">
            <div class="login-pillar">
                <span class="login-pillar-icon"><x-icon name="layers" :size="18" /></span>
                <div><strong>One inventory, continuously monitored</strong><span>Every application, database, and server with live health, uptime history, and incidents.</span></div>
            </div>
            <div class="login-pillar">
                <span class="login-pillar-icon"><x-icon name="sparkles" :size="18" /></span>
                <div><strong>Scoped AI orchestration</strong><span>Open NAi on any record with its metrics attached, and turn replies into tracked tasks.</span></div>
            </div>
            <div class="login-pillar">
                <span class="login-pillar-icon"><x-icon name="shield" :size="18" /></span>
                <div><strong>Governed by design</strong><span>Role-based access, encrypted credentials, and an audit trail on every change.</span></div>
            </div>
        </div>
    </section>

    <section class="login-panel">
        <div class="login-card">
            <div class="login-card-brand">
                <img src="{{ asset('images/nai-logo.png') }}" alt="NAi — Nowills AI">
                <div class="login-brand-tag">Nowills Group</div>
            </div>

            <span class="login-secure"><x-icon name="lock" :size="12" />Secure workspace sign-in</span>
            <h2>Welcome to NAi</h2>
            <p class="login-sub">Sign in to the <strong>{{ $workspace }}</strong> workspace.</p>

            @if($errors->any())
            <div class="login-alert" id="loginError" role="alert" aria-live="assertive">
                <x-icon name="alert" :size="17" />
                <div>{{ $errors->first() }}</div>
            </div>
            @endif

            <form method="POST" action="{{ route('login.attempt') }}" id="loginForm">
                @csrf
                <div class="login-field">
                    <label for="email">Work email</label>
                    <div class="login-input-wrap">
                        <input id="email" class="input" type="email" name="email" value="{{ old('email') }}" required autofocus autocomplete="username" inputmode="email" spellcheck="false" autocapitalize="off" placeholder="you@nowills.group" @if($errors->has('email')) aria-invalid="true" aria-describedby="loginError" @endif>
                        <x-icon name="mail" :size="17" />
                    </div>
                </div>

                <div class="login-field">
                    <label for="password">Password</label>
                    <div class="login-input-wrap">
                        <input id="password" class="input" type="password" name="password" required autocomplete="current-password" placeholder="Enter your password" @if($errors->has('password')) aria-invalid="true" @endif>
                        <x-icon name="lock" :size="17" />
                        <button type="button" class="pw-toggle" id="pwToggle" aria-label="Show password" aria-pressed="false"><x-icon name="eye" :size="17" /></button>
                    </div>
                    <span class="caps-hint" id="capsHint" hidden><x-icon name="alert" :size="13" />Caps Lock is on</span>
                </div>

                <div class="login-row">
                    <label class="login-check"><input type="checkbox" name="remember" value="1" @checked(old('remember'))>Keep me signed in</label>
                    <button type="button" class="login-help" id="accessHelp" aria-expanded="false" aria-controls="accessHelpNote">Need access?</button>
                </div>
                <p class="login-help-note" id="accessHelpNote" hidden>Password resets are handled by your workspace administrator to keep the audit trail intact. Ask an admin to reset your credentials from <strong>Team</strong>, then sign in again.</p>

                <button class="login-submit" type="submit" id="loginSubmit">
                    <x-icon name="arrow-right" :size="17" /><span>Sign in to workspace</span>
                </button>
            </form>

            @if($demoMode)
            <div class="login-divider">or explore</div>
            <form method="POST" action="{{ route('login.demo') }}">@csrf
                <button class="login-demo" type="submit"><x-icon name="eye" :size="16" />Open the demo workspace</button>
            </form>
            <p class="login-demo-note">Read-write access to labelled sample data. No credentials needed.</p>
            @endif

            <div class="login-trust">
                <span><x-icon name="lock" :size="12" />Encrypted session</span>
                <span><x-icon name="shield" :size="12" />Rate-limited</span>
                <span><x-icon name="activity" :size="12" />Audited access</span>
            </div>

            <p class="login-foot">NAi is an intelligent interface and orchestration layer.<br>AI development is powered by <strong>Nowills Group</strong>.</p>
        </div>
    </section>
</div>
<script src="{{ asset('js/login.js') }}" defer></script>
</body>
</html>
