@props(['title' => 'Dashboard', 'subtitle' => null])
@php($engineStatus = $arenaStatus ?? app(\App\Services\ArenaGateway::class)->status())
@php($navIncidents = \App\Models\Incident::open()->count())
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ $title }} · NAi</title>
    <link rel="icon" type="image/png" href="{{ asset('images/favicon.png') }}">
    <link rel="stylesheet" href="{{ asset('css/nai.css') }}">
</head>
<body>
<div class="app-shell">
    <aside class="sidebar" id="sidebar">
        <div class="sidebar-brand">
            <a href="{{ route('dashboard') }}" class="brand-link"><img src="{{ asset('images/nai-logo.png') }}" alt="NAi" class="brand-logo"></a>
            <div class="brand-sub">Nowills AI</div>
        </div>
        <div class="workspace-switcher">
            <div class="workspace-avatar">NG</div>
            <div class="workspace-meta"><strong>{{ \App\Models\Setting::where('key','workspace_name')->value('value') ?? config('nai.workspace') }}</strong><span>Enterprise workspace</span></div>
            <x-icon name="chevron-down" :size="16" />
        </div>
        <nav class="sidebar-nav">
            <div class="nav-label">Overview</div>
            <a href="{{ route('dashboard') }}" class="nav-item {{ request()->routeIs('dashboard') ? 'active' : '' }}"><x-icon name="dashboard" :size="18" /><span>Dashboard</span></a>
            <a href="{{ route('arena.index') }}" class="nav-item {{ request()->routeIs('arena.*') ? 'active' : '' }}"><x-icon name="sparkles" :size="18" /><span>Arena AI</span><span class="nav-pill">Engine</span></a>
            <div class="nav-label">Manage</div>
            <a href="{{ route('inventory.index','applications') }}" class="nav-item {{ request()->is('inventory/applications*') ? 'active' : '' }}"><x-icon name="apps" :size="18" /><span>Applications</span></a>
            <a href="{{ route('inventory.index','repositories') }}" class="nav-item {{ request()->is('inventory/repositories*') ? 'active' : '' }}"><x-icon name="git" :size="18" /><span>Repositories</span></a>
            <a href="{{ route('inventory.index','databases') }}" class="nav-item {{ request()->is('inventory/databases*') ? 'active' : '' }}"><x-icon name="database" :size="18" /><span>Databases</span></a>
            <a href="{{ route('inventory.index','infrastructure') }}" class="nav-item {{ request()->is('inventory/infrastructure*') ? 'active' : '' }}"><x-icon name="server" :size="18" /><span>Infrastructure</span></a>
            <a href="{{ route('projects.index') }}" class="nav-item {{ request()->routeIs('projects.*') ? 'active' : '' }}"><x-icon name="folder" :size="18" /><span>Projects</span></a>
            <a href="{{ route('monitoring.index') }}" class="nav-item {{ request()->routeIs('monitoring.*') ? 'active' : '' }}"><x-icon name="gauge" :size="18" /><span>Monitoring</span>@if($navIncidents)<span class="nav-pill alert">{{ $navIncidents }}</span>@endif</a>
            <div class="nav-label">System</div>
            <a href="{{ route('activity.index') }}" class="nav-item {{ request()->routeIs('activity.*') ? 'active' : '' }}"><x-icon name="activity" :size="18" /><span>Activity</span></a>
            @can('admin')
            <a href="{{ route('users.index') }}" class="nav-item {{ request()->routeIs('users.*') ? 'active' : '' }}"><x-icon name="users" :size="18" /><span>Team</span></a>
            <a href="{{ route('settings.edit') }}" class="nav-item {{ request()->routeIs('settings.*') ? 'active' : '' }}"><x-icon name="settings" :size="18" /><span>Settings</span></a>
            @endcan
        </nav>
        <div class="sidebar-engine">
            <div class="engine-icon"><x-icon name="cpu" :size="18" /></div>
            <div><strong>Arena AI engine</strong><span>{{ $engineStatus }}</span></div>
            <span class="status-light {{ $engineStatus === 'connected' ? 'ok' : '' }}"></span>
        </div>
        <div class="sidebar-footer">NAi is an orchestration interface.<br>AI development is powered by Arena AI.</div>
    </aside>
    <div class="sidebar-overlay" id="sidebarOverlay"></div>
    <div class="main-wrap">
        <header class="topbar">
            <button class="icon-btn mobile-menu" id="menuToggle" aria-label="Open navigation"><x-icon name="menu" /></button>
            <div class="topbar-title"><h1>{{ $title }}</h1>@if($subtitle)<span>{{ $subtitle }}</span>@endif</div>
            <div class="topbar-actions">
                <button class="search-trigger" id="searchTrigger"><x-icon name="search" :size="16" /><span>Search workspace…</span><kbd>⌘K</kbd></button>
                @if(config('nai.demo_mode'))<span class="demo-chip">Demo</span>@endif
                <div class="dropdown">
                    <button class="user-menu" id="userMenuBtn"><span class="avatar">{{ auth()->user()->initials() }}</span><span class="user-meta"><strong>{{ auth()->user()->name }}</strong><span>{{ ucfirst(auth()->user()->role) }}</span></span><x-icon name="chevron-down" :size="16" /></button>
                    <div class="dropdown-menu" id="userMenu">
                        <div class="dropdown-header">{{ auth()->user()->email }}</div>
                        @can('admin')<a href="{{ route('settings.edit') }}"><x-icon name="settings" :size="16" />Settings</a>@endcan
                        <form method="POST" action="{{ route('logout') }}">@csrf<button type="submit"><x-icon name="logout" :size="16" />Sign out</button></form>
                    </div>
                </div>
            </div>
        </header>
        <main class="content">
            @if(session('success'))<div class="flash flash-success"><x-icon name="check" :size="18" />{{ session('success') }}</div>@endif
            @if(session('error'))<div class="flash flash-error"><x-icon name="alert" :size="18" />{{ session('error') }}</div>@endif
            @if($errors->any())<div class="flash flash-error"><x-icon name="alert" :size="18" /><div>@foreach($errors->all() as $error)<div>{{ $error }}</div>@endforeach</div></div>@endif
            {{ $slot }}
        </main>
    </div>
</div>
<div class="search-modal" id="searchModal" hidden>
    <div class="search-panel">
        <div class="search-input-row"><x-icon name="search" /><input type="text" id="globalSearchInput" placeholder="Search applications, repositories, databases, projects…" autocomplete="off"><button class="icon-btn" id="searchClose"><x-icon name="x" :size="18" /></button></div>
        <div class="search-results" id="searchResults"><div class="search-empty">Type at least two characters to search the workspace.</div></div>
    </div>
</div>
<script src="{{ asset('js/nai.js') }}"></script>
@stack('scripts')
</body>
</html>
