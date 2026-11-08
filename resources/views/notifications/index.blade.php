<x-layout title="Notifications" subtitle="Alerts for your workspace">
<div class="page-head"><div><h2>Notifications</h2><p>Incidents, task assignments, and completions addressed to you.</p></div><div class="head-actions">@if($unreadCount)<form method="POST" action="{{ route('notifications.readAll') }}">@csrf<button class="btn"><x-icon name="check" :size="16" />Mark all read ({{ $unreadCount }})</button></form>@endif</div></div>
<div class="toolbar notif-toolbar"><div class="seg">@if(request()->routeIs('notifications.index'))<a href="{{ route('notifications.index') }}" class="seg-item {{ $filter==='all'?'active':'' }}">All</a><a href="{{ route('notifications.index',['filter'=>'unread']) }}" class="seg-item {{ $filter==='unread'?'active':'' }}">Unread @if($unreadCount)<span class="seg-count">{{ $unreadCount }}</span>@endif</a>@endif</div></div>
<div class="card"><div class="notif-list">
@forelse($notifications as $notification)
<div class="notif-item {{ $notification->isUnread() ? 'unread' : '' }}">
    <span class="notif-icon tone-{{ $notification->tone() }}"><x-icon :name="$notification->icon()" :size="16" /></span>
    <div class="notif-body">
        <p>{{ $notification->title }} @if($notification->isUnread())<span class="notif-dot" title="Unread"></span>@endif</p>
        @if($notification->body)<span>{{ $notification->body }}</span>@endif
        <span class="notif-time">{{ $notification->created_at->diffForHumans() }}</span>
    </div>
    <div class="notif-actions">
        @if($notification->safeUrl())<form method="POST" action="{{ route('notifications.read', $notification) }}">@csrf<input type="hidden" name="goto" value="1"><button class="btn btn-sm">Open</button></form>@endif
        @if($notification->isUnread())<form method="POST" action="{{ route('notifications.read', $notification) }}">@csrf<button class="btn btn-sm" title="Mark as read"><x-icon name="check" :size="14" /></button></form>@endif
    </div>
</div>
@empty
<div class="empty"><x-icon name="bell" :size="30" /><h3>No notifications</h3><p>{{ $filter==='unread' ? 'You are all caught up.' : 'Incident alerts and task assignments will appear here.' }}</p></div>
@endforelse
</div></div>
<x-pagination :paginator="$notifications" />
</x-layout>
