@props(['title' => 'Dashboard', 'subtitle' => null, 'fluid' => false])
@php($engineStatus = $arenaStatus ?? app(\App\Services\ArenaGateway::class)->status())
@php($navIncidents = \App\Models\Incident::open()->count())
@php($navUnread = \App\Models\Notification::forUser(auth()->id())->unread()->count())
@php($navNotifications = \App\Models\Notification::forUser(auth()->id())->latest()->limit(6)->get())
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ $title === 'NAi' ? 'NAi' : $title.' · NAi' }}</title>
    <link rel="icon" type="image/png" href="{{ asset('images/favicon.png') }}">
    <link rel="stylesheet" href="{{ \App\Support\AssetVersion::url('css/nai.css') }}">
    @stack('styles')
</head>
<body class="{{ $fluid ? 'fluid-page' : '' }}">
<div class="app-shell">
    <aside class="sidebar" id="sidebar">
        <div class="sidebar-brand">
            <a href="{{ route('dashboard.home') }}" class="brand-link"><img src="{{ asset('images/nai-logo.png') }}" alt="NAi" class="brand-logo"></a>
            <div class="brand-sub">Nowills AI</div>
        </div>
        <div class="workspace-switcher">
            <div class="workspace-avatar">NG</div>
            <div class="workspace-meta"><strong>{{ \App\Models\Setting::where('key','workspace_name')->value('value') ?? config('nai.workspace') }}</strong><span>Enterprise workspace</span></div>
            <x-icon name="chevron-down" :size="16" />
        </div>
        <nav class="sidebar-nav">
            <div class="nav-label">Overview</div>
            <a href="{{ route('dashboard.home') }}" class="nav-item {{ request()->routeIs('dashboard*') ? 'active' : '' }}"><x-icon name="dashboard" :size="18" /><span>Dashboard</span></a>
            <a href="{{ route('arena.index') }}" class="nav-item {{ request()->routeIs('arena.*') && !request()->routeIs('arena.browser*') ? 'active' : '' }}"><x-icon name="sparkles" :size="18" /><span>NAi</span><span class="nav-pill">Engine</span></a>
            <a href="{{ route('arena.browser') }}" class="nav-item nav-arena {{ request()->routeIs('arena.browser*') ? 'active' : '' }}"><x-icon name="globe" :size="18" /><span>NAi</span><span class="nav-pill">Browser</span></a>
            <a href="{{ route('workforce.index') }}" class="nav-item {{ request()->routeIs('workforce.*') ? 'active' : '' }}"><x-icon name="sparkles" :size="18" /><span>AI Workforce</span></a>
            <div class="nav-label">Manage</div>
            <a href="{{ route('inventory.index','applications') }}" class="nav-item {{ request()->is('inventory/applications*') ? 'active' : '' }}"><x-icon name="apps" :size="18" /><span>Applications</span></a>
            <a href="{{ route('inventory.index','repositories') }}" class="nav-item {{ request()->is('inventory/repositories*') ? 'active' : '' }}"><x-icon name="git" :size="18" /><span>Repositories</span></a>
            <a href="{{ route('inventory.index','databases') }}" class="nav-item {{ request()->is('inventory/databases*') ? 'active' : '' }}"><x-icon name="database" :size="18" /><span>Databases</span></a>
            <a href="{{ route('inventory.index','infrastructure') }}" class="nav-item {{ request()->is('inventory/infrastructure*') ? 'active' : '' }}"><x-icon name="server" :size="18" /><span>Infrastructure</span></a>
            <a href="{{ route('projects.index') }}" class="nav-item {{ request()->routeIs('projects.*') ? 'active' : '' }}"><x-icon name="folder" :size="18" /><span>Projects</span></a>
            <a href="{{ route('monitoring.index') }}" class="nav-item {{ request()->routeIs('monitoring.*') ? 'active' : '' }}"><x-icon name="gauge" :size="18" /><span>Monitoring</span>@if($navIncidents)<span class="nav-pill alert">{{ $navIncidents }}</span>@endif</a>
            <div class="nav-label">System</div>
            <a href="{{ route('notifications.index') }}" class="nav-item {{ request()->routeIs('notifications.*') ? 'active' : '' }}"><x-icon name="bell" :size="18" /><span>Notifications</span>@if($navUnread)<span class="nav-pill alert">{{ $navUnread }}</span>@endif</a>
            <a href="{{ route('activity.index') }}" class="nav-item {{ request()->routeIs('activity.*') ? 'active' : '' }}"><x-icon name="activity" :size="18" /><span>Activity</span></a>
            @can('admin')
            <a href="{{ route('users.index') }}" class="nav-item {{ request()->routeIs('users.*') ? 'active' : '' }}"><x-icon name="users" :size="18" /><span>Team</span></a>
            <a href="{{ route('settings.edit') }}" class="nav-item {{ request()->routeIs('settings.*') ? 'active' : '' }}"><x-icon name="settings" :size="18" /><span>Settings</span></a>
            @endcan
        </nav>
        <div class="sidebar-engine">
            <div class="engine-icon"><x-icon name="cpu" :size="18" /></div>
            <div><strong>NAi engine</strong><span>{{ $engineStatus }}</span></div>
            <span class="status-light {{ $engineStatus === 'connected' ? 'ok' : '' }}"></span>
        </div>
        <div class="sidebar-footer">NAi is an orchestration interface.<br>AI answers come from your configured provider.</div>
    </aside>
    <div class="sidebar-overlay" id="sidebarOverlay"></div>
    <div class="main-wrap">
        <header class="topbar">
            <button class="icon-btn mobile-menu" id="menuToggle" aria-label="Open navigation"><x-icon name="menu" /></button>
            <div class="topbar-title"><h1>{{ $title }}</h1>@if($subtitle)<span>{{ $subtitle }}</span>@endif</div>
            <div class="topbar-actions">
                <button class="search-trigger" id="searchTrigger"><x-icon name="search" :size="16" /><span>Search workspace…</span><kbd>⌘K</kbd></button>
                <a href="{{ route('arena.index') }}" class="arena-launch" title="Open the NAi coding assistant"><x-icon name="sparkles" :size="15" /><span>NAi</span><x-icon name="arrow-right" :size="13" /></a>
                @if(config('nai.demo_mode'))<span class="demo-chip">Demo</span>@endif
                <div class="dropdown">
                    <button class="icon-btn bell-btn" id="bellBtn" aria-label="Notifications ({{ $navUnread }} unread)"><x-icon name="bell" :size="17" />@if($navUnread)<span class="bell-count">{{ min($navUnread, 99) }}</span>@endif</button>
                    <div class="dropdown-menu bell-menu" id="bellMenu">
                        <div class="dropdown-header bell-head"><span>Notifications</span>@if($navUnread)<form method="POST" action="{{ route('notifications.readAll') }}">@csrf<button type="submit" class="bell-mark">Mark all read</button></form>@endif</div>
                        @forelse($navNotifications as $notification)
                        <form method="POST" action="{{ route('notifications.read', $notification) }}">@csrf<input type="hidden" name="goto" value="1"><button type="submit" class="bell-item {{ $notification->isUnread() ? 'unread' : '' }}"><span class="notif-icon tone-{{ $notification->tone() }}"><x-icon :name="$notification->icon()" :size="14" /></span><span class="bell-item-body"><strong>{{ $notification->title }}</strong><span>{{ $notification->created_at->diffForHumans() }}</span></span>@if($notification->isUnread())<span class="notif-dot"></span>@endif</button></form>
                        @empty
                        <div class="bell-empty">No notifications yet.</div>
                        @endforelse
                        <a href="{{ route('notifications.index') }}" class="bell-all">View all notifications <x-icon name="arrow-right" :size="13" /></a>
                    </div>
                </div>
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
        <main class="{{ $fluid ? 'content content-fluid' : 'content' }}">
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
<script src="{{ \App\Support\AssetVersion::url('js/nai.js') }}"></script>
@stack('scripts')
</body>
</html>
