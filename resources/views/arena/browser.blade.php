<x-layout title="Arena AI" subtitle="Arena AI inside your NAi workspace" :fluid="true">
<div class="browser" id="arenaBrowser"
     data-home="{{ $portalUrl }}"
     data-probe="{{ route('arena.browser.probe', [], false) }}">

    <div class="browser-chrome">
        <div class="browser-tabstrip">
            <span class="browser-tab active"><x-icon name="sparkles" :size="13" /><span>Arena AI</span><span class="browser-tab-host">{{ $portalHost }}</span></span>
            <span class="browser-shell-note"><x-icon name="shield" :size="12" />Sign-in happens on Arena AI itself — NAi never sees your password.</span>
        </div>

        <div class="browser-bar">
            <div class="browser-nav">
                <button type="button" class="icon-btn" id="browserBack" title="Back" aria-label="Back" disabled><x-icon name="arrow-left" :size="16" /></button>
                <button type="button" class="icon-btn" id="browserForward" title="Forward" aria-label="Forward" disabled><x-icon name="arrow-right" :size="16" /></button>
                <button type="button" class="icon-btn" id="browserReload" title="Reload" aria-label="Reload"><x-icon name="refresh" :size="16" /></button>
                <button type="button" class="icon-btn" id="browserHome" title="Arena AI home" aria-label="Arena AI home"><x-icon name="dashboard" :size="16" /></button>
            </div>

            <form class="browser-address" id="browserAddressForm" autocomplete="off">
                <x-icon name="lock" :size="13" />
                <input type="text" id="browserAddress" value="{{ $portalUrl }}" spellcheck="false" aria-label="Arena AI address" placeholder="https://arena.ai">
                <button type="submit" class="browser-go">Go</button>
            </form>

            <div class="browser-actions">
                <a href="{{ $portalUrl }}" target="_blank" rel="noopener noreferrer" class="btn btn-sm" id="browserOpenTab" title="Open Arena AI in a normal browser tab"><x-icon name="external" :size="14" /><span>Open in tab</span></a>
                <button type="button" class="icon-btn" id="browserFocus" title="Focus mode — hide NAi navigation" aria-label="Focus mode"><x-icon name="eye" :size="16" /></button>
                <button type="button" class="icon-btn" id="browserFullscreen" title="Fullscreen" aria-label="Fullscreen"><x-icon name="layers" :size="16" /></button>
            </div>
        </div>
    </div>

    <div class="browser-hint" id="browserHint" data-tone="info">
        <x-icon name="alert" :size="14" />
        <span id="browserHintText">Loading Arena AI inside NAi. If the panel stays blank, Arena AI is refusing to be embedded or your browser is blocking third-party cookies — use “Open in tab” to sign in.</span>
        <a href="{{ $portalUrl }}" target="_blank" rel="noopener noreferrer" class="browser-hint-link" id="browserHintLink">Open in tab</a>
        <button type="button" class="browser-hint-close" id="browserHintClose" aria-label="Dismiss notice"><x-icon name="x" :size="13" /></button>
    </div>

    <div class="browser-stage" id="browserStage">
        <iframe id="arenaFrame"
                class="browser-frame"
                src="{{ $portalUrl }}"
                title="Arena AI"
                referrerpolicy="strict-origin-when-cross-origin"
                allow="clipboard-write; fullscreen"
                sandbox="allow-same-origin allow-scripts allow-forms allow-popups allow-popups-to-escape-sandbox allow-downloads allow-modals"></iframe>
        <div class="browser-loading" id="browserLoading"><span class="browser-spinner"></span>Opening {{ $portalHost }}…</div>
    </div>
</div>

<button type="button" class="browser-exit-focus" id="browserExitFocus"><x-icon name="x" :size="14" />Exit focus mode</button>

@push('scripts')
<script src="{{ asset('js/arena-browser.js') }}"></script>
@endpush
</x-layout>
