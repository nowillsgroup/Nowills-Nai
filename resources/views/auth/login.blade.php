<!DOCTYPE html>
<html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><title>Sign in · NAi</title><link rel="icon" href="{{ asset('images/favicon.png') }}"><link rel="stylesheet" href="{{ asset('css/nai.css') }}"></head>
<body><div class="auth-body">
<div class="auth-visual"><img src="{{ asset('images/nai-logo.png') }}" alt="NAi"><h1>One command center for your <span class="gradient-text">entire technical estate.</span></h1><p>NAi brings applications, repositories, databases, infrastructure, and development projects into a single branded workspace — powered by Arena AI.</p><div class="auth-features"><div class="auth-feature"><x-icon name="layers" :size="18" />Centralized inventory and monitoring</div><div class="auth-feature"><x-icon name="sparkles" :size="18" />Arena AI orchestration, not a replacement model</div><div class="auth-feature"><x-icon name="shield" :size="18" />Role-based access and audit trail</div></div></div>
<div class="auth-panel"><div class="auth-card"><h2>Welcome to NAi</h2><p class="sub">Sign in to the Nowills Group workspace.</p>
@if($errors->any())<div class="flash flash-error"><x-icon name="alert" :size="18" /><div>{{ $errors->first() }}</div></div>@endif
<form method="POST" action="{{ route('login.attempt') }}">@csrf
<div class="field"><label>Email address</label><input class="input" type="email" name="email" value="{{ old('email') }}" required autofocus placeholder="you@nowills.group"></div>
<div class="field"><label>Password</label><input class="input" type="password" name="password" required placeholder="••••••••••"></div>
<div class="auth-row"><label class="checkbox"><input type="checkbox" name="remember" value="1"> Remember me</label></div>
<button class="btn btn-primary btn-block" type="submit"><x-icon name="lock" :size="16" />Sign in</button>
</form>
@if(config('nai.demo_mode'))<div class="auth-divider">or explore</div><form method="POST" action="{{ route('login.demo') }}">@csrf<button class="btn btn-block"><x-icon name="eye" :size="16" />Open demo workspace</button></form>@endif
<div class="auth-note">NAi is an intelligent interface and orchestration layer.<br>AI development capabilities are provided by Arena AI.</div>
</div></div>
</div></body></html>
