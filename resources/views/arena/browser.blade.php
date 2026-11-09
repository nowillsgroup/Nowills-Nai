<x-layout title="NAi" subtitle="NAi browser workspace" :fluid="true">
<div class="browser" id="arenaBrowser"
     data-home="{{ $portalUrl }}"
     data-probe="{{ route('arena.browser.probe', [], false) }}"
     data-status="{{ $probe['status'] }}">

    <div class="browser-chrome">
        <div class="browser-tabstrip">
            <span class="browser-tab active"><x-icon name="sparkles" :size="13" /><span>NAi</span><span class="browser-tab-host">{{ $portalHost }}</span></span>
            <span class="browser-shell-note"><x-icon name="shield" :size="12" />Sign-in happens on the framed site itself — NAi never sees your password.</span>
        </div>

        <div class="browser-bar">
            <div class="browser-nav">
                <button type="button" class="icon-btn" id="browserBack" title="Back" aria-label="Back" disabled><x-icon name="arrow-left" :size="16" /></button>
                <button type="button" class="icon-btn" id="browserForward" title="Forward" aria-label="Forward" disabled><x-icon name="arrow-right" :size="16" /></button>
                <button type="button" class="icon-btn" id="browserReload" title="Reload" aria-label="Reload"><x-icon name="refresh" :size="16" /></button>
                <button type="button" class="icon-btn" id="browserHome" title="NAi home" aria-label="NAi home"><x-icon name="dashboard" :size="16" /></button>
            </div>

            <form class="browser-address" id="browserAddressForm" autocomplete="off">
                <x-icon name="lock" :size="13" />
                <input type="text" id="browserAddress" value="{{ $portalUrl }}" spellcheck="false" aria-label="Browser address" placeholder="https://arena.ai">
                <button type="submit" class="browser-go">Go</button>
            </form>

            <div class="browser-actions">
                <a href="{{ $portalUrl }}" target="_blank" rel="noopener noreferrer" class="btn btn-sm" id="browserOpenTab" title="Open this page in a normal browser tab"><x-icon name="external" :size="14" /><span>Open in tab</span></a>
                <button type="button" class="icon-btn" id="browserFocus" title="Focus mode — hide NAi navigation" aria-label="Focus mode"><x-icon name="eye" :size="16" /></button>
                <button type="button" class="icon-btn" id="browserFullscreen" title="Fullscreen" aria-label="Fullscreen"><x-icon name="layers" :size="16" /></button>
            </div>
        </div>
    </div>

    <div class="browser-hint" id="browserHint" data-tone="{{ $probe['status'] === 'blocked' ? 'warn' : ($probe['status'] === 'allowed' ? 'ok' : 'info') }}">
        <x-icon name="alert" :size="14" />
        <span id="browserHintText">@if($probe['status'] === 'blocked'){{ $probe['reason'] }} Browsers enforce this, so NAi cannot override it — use “Open in tab” to sign in and work.@elseif($probe['status'] === 'allowed')Loading inside NAi. Sign in on the framed page itself — NAi never sees your credentials.@elseLoading inside NAi. If the panel stays blank, the site is refusing to be embedded or your browser is blocking third-party cookies — use “Open in tab” to sign in.@endif</span>
        <a href="{{ $portalUrl }}" target="_blank" rel="noopener noreferrer" class="browser-hint-link" id="browserHintLink">Open in tab</a>
        <button type="button" class="browser-hint-close" id="browserHintClose" aria-label="Dismiss notice"><x-icon name="x" :size="13" /></button>
    </div>

    <div class="browser-stage" id="browserStage">
        <iframe id="arenaFrame"
                class="browser-frame"
                @if($probe['status'] !== 'blocked') src="{{ $probe['final_url'] ?: $portalUrl }}" @endif
                title="NAi browser"
                referrerpolicy="strict-origin-when-cross-origin"
                allow="clipboard-write; fullscreen"
                sandbox="allow-same-origin allow-scripts allow-forms allow-popups allow-popups-to-escape-sandbox allow-downloads allow-modals"></iframe>
        <div class="browser-loading" id="browserLoading" @if($probe['status'] === 'blocked') hidden @endif><span class="browser-spinner"></span>Opening {{ $portalHost }}…</div>

        {{-- A refusal is a real answer, not a broken frame. Show why, and give a
             working way forward instead of the browser's broken-page icon. --}}
        <div class="browser-blocked" id="browserBlocked" @if($probe['status'] !== 'blocked') hidden @endif>
            <div class="browser-blocked-card">
                <span class="browser-blocked-icon"><x-icon name="shield" :size="26" /></span>
                <h2>{{ $portalHost }} won’t run inside a frame</h2>
                <p id="browserBlockedReason">{{ $probe['reason'] ?: 'This site refuses to be embedded in another site.' }}</p>
                <p class="browser-blocked-note">This is the site’s own security policy, enforced by your browser. NAi can’t switch it off, and won’t route your password through a proxy to get around it.</p>
                <div class="browser-blocked-actions">
                    <a href="{{ $portalUrl }}" target="_blank" rel="noopener noreferrer" class="btn btn-primary" id="browserBlockedOpen"><x-icon name="external" :size="15" /><span>Open {{ $portalHost }} in a tab</span></a>
                    <button type="button" class="btn" id="browserBlockedRetry"><x-icon name="refresh" :size="15" /><span>Check again</span></button>
                    <button type="button" class="btn" id="browserBlockedForce"><x-icon name="eye" :size="15" /><span>Try framing anyway</span></button>
                </div>
                <p class="browser-blocked-foot">Sign in there, then keep NAi open alongside it. Your workspace, engines and notifications stay right here.</p>
            </div>
        </div>
    </div>
</div>

<button type="button" class="browser-exit-focus" id="browserExitFocus"><x-icon name="x" :size="14" />Exit focus mode</button>

@push('styles')
<link rel="stylesheet" href="{{ \App\Support\AssetVersion::url('css/arena-browser.css') }}">
@endpush
@push('scripts')
<script src="{{ \App\Support\AssetVersion::url('js/arena-browser.js') }}"></script>
@endpush
</x-layout>
