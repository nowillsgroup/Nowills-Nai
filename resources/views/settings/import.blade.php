<x-layout title="Import workspace" subtitle="Validate, review, then apply">
<div class="import-breadcrumb"><a href="{{ route('settings.edit') }}">Settings</a><x-icon name="chevron-down" :size="13" /><span>Workspace import</span></div>
<div class="page-head"><div><h2>Bring your workspace back.</h2><p>Restore missing inventory and projects from a NAi snapshot. Review every change before anything is written.</p></div><a class="btn btn-sm" href="{{ route('settings.export') }}"><x-icon name="download" :size="15" />Export current workspace</a></div>
<ol class="import-steps" aria-label="Import progress">
    <li class="{{ $plan ? 'complete' : 'current' }}" @if(!$plan) aria-current="step" @endif><span class="import-step-number">@if($plan)<x-icon name="check" :size="15" />@else 01 @endif</span><div><strong>Upload snapshot</strong><span>Versioned NAi JSON · up to 5 MB</span></div></li>
    <li class="{{ $plan ? 'current' : '' }}" @if($plan) aria-current="step" @endif><span class="import-step-number">02</span><div><strong>Review changes</strong><span>Validation, identities, and conflicts</span></div></li>
    <li><span class="import-step-number">03</span><div><strong>Apply safely</strong><span>Add missing records only</span></div></li>
</ol>
@if(!$plan)
<div class="import-layout">
    <section class="card import-upload-card" aria-labelledby="upload-heading">
        <div class="card-head"><h3 id="upload-heading">Choose a workspace snapshot</h3><span class="badge badge-slate">JSON · v1</span></div>
        <p class="muted import-copy">Use a JSON backup exported from NAi. We inspect the file and prepare a change plan; uploading alone never changes your workspace.</p>
        <form method="POST" action="{{ route('settings.import.preview') }}" enctype="multipart/form-data" id="importUploadForm">
            @csrf
            <label class="import-dropzone" id="importDropzone" for="snapshot">
                <span class="import-upload-icon"><x-icon name="upload" :size="28" /></span>
                <strong>Drop your snapshot here</strong>
                <span>or <span class="import-browse">browse files</span> on your device</span>
                <small id="importFileHint">.json only · maximum 5 MB</small>
                <input type="file" name="snapshot" id="snapshot" accept=".json,application/json" required aria-describedby="importFileHint importFileStatus">
            </label>
            <div class="import-file-status" id="importFileStatus" role="status" aria-live="polite" hidden><x-icon name="file" :size="18" /><span></span></div>
            <div class="import-upload-actions"><span><x-icon name="lock" :size="14" />Private, encrypted review</span><button class="btn btn-primary" type="submit" id="importPreviewButton"><x-icon name="shield" :size="16" /><span>Validate &amp; preview</span><x-icon name="arrow-right" :size="16" /></button></div>
        </form>
        <div class="import-cli"><x-icon name="terminal" :size="16" /><div><strong>Prefer the command line?</strong><code>php artisan nai:import backup.json --dry-run</code></div></div>
    </section>
    <aside class="card import-safety" aria-labelledby="safety-heading">
        <span class="import-safety-icon"><x-icon name="shield" :size="24" /></span>
        <h3 id="safety-heading">A merge, not a takeover.</h3><p class="muted import-copy">Your current workspace stays in control.</p>
        <ul class="import-checklist">
            <li><x-icon name="check" :size="16" /><div><strong>No overwrites. No deletions.</strong><span>Matching records stay untouched. Different metadata blocks the import for review.</span></div></li>
            <li><x-icon name="check" :size="16" /><div><strong>Relationships travel safely.</strong><span>Source IDs are remapped to this workspace, never inserted as destination IDs.</span></div></li>
            <li><x-icon name="check" :size="16" /><div><strong>No borrowed identities or secrets.</strong><span>Users, credentials, ownership, Arena settings, and AI conversations are excluded.</span></div></li>
            <li><x-icon name="check" :size="16" /><div><strong>Health must be earned.</strong><span>Imported resources start Unknown. Monitoring history is never restored as live health.</span></div></li>
        </ul>
        <a class="link import-doc-link" href="{{ route('settings.edit') }}">Manage workspace backups<x-icon name="arrow-right" :size="14" /></a>
    </aside>
</div>
@else
<div class="import-review" id="importReview">
    <section class="card import-source" aria-label="Snapshot details">
        <span class="import-source-icon"><x-icon name="file" :size="24" /></span>
        <div class="import-source-main"><strong>{{ $plan['meta']['filename'] }}</strong><span>{{ $plan['meta']['workspace'] }}<span class="import-dot">·</span>{{ number_format($plan['meta']['bytes'] / 1024, 1) }} KB<span class="import-dot">·</span>Format v1</span></div>
        <div class="import-source-date"><span>Exported</span><strong>{{ $plan['meta']['exported_at'] ?? 'Not provided' }}</strong></div>
        <span class="badge {{ $plan['can_apply'] ? 'badge-green' : ($plan['totals']['conflicts'] || $plan['error_count'] ? 'badge-red' : 'badge-slate') }}">{{ $plan['can_apply'] ? 'Ready to review' : ($plan['totals']['conflicts'] || $plan['error_count'] ? 'Import blocked' : 'Already up to date') }}</span>
    </section>
    <div class="import-totals">
        <div class="card import-total import-total-new"><span><x-icon name="plus" :size="17" />Missing records</span><strong>{{ number_format($plan['totals']['new']) }}</strong><small>Will be created after confirmation</small></div>
        <div class="card import-total"><span><x-icon name="check" :size="17" />Already present</span><strong>{{ number_format($plan['totals']['existing']) }}</strong><small>Identical records remain untouched</small></div>
        <div class="card import-total {{ $plan['totals']['conflicts'] ? 'import-total-conflict' : '' }}"><span><x-icon name="alert" :size="17" />Conflicts</span><strong>{{ number_format($plan['totals']['conflicts']) }}</strong><small>{{ $plan['totals']['conflicts'] ? 'Must be resolved before applying' : 'No conflicting metadata detected' }}</small></div>
    </div>
    @if($plan['errors'])
    <section class="card import-errors" aria-labelledby="validation-heading"><div class="card-head"><h3 id="validation-heading">Validation needs attention</h3><span class="badge badge-red">{{ $plan['error_count'] }} {{ Str::plural('issue', $plan['error_count']) }}</span></div><ul>@foreach($plan['errors'] as $error)<li><strong>{{ $error['field'] }}</strong><span>{{ $error['message'] }}</span></li>@endforeach</ul>@if($plan['error_count'] > count($plan['errors']))<p class="muted">{{ $plan['error_count'] - count($plan['errors']) }} additional issues omitted. Fix the file and upload again.</p>@endif</section>
    @endif
    @if($plan['warnings'])
    <details class="import-policy"><summary><x-icon name="shield" :size="16" />Import boundaries &amp; safety rules<span>{{ count($plan['warnings']) }}</span></summary><ul>@foreach($plan['warnings'] as $warning)<li>{{ $warning }}</li>@endforeach</ul></details>
    @endif
    @if($plan['sections'])
    <section class="card import-plan" aria-labelledby="plan-heading">
        <div class="card-head"><div><h3 id="plan-heading">Your change plan</h3><p class="muted">Every supported record is checked against the current workspace.</p></div><span class="import-filter-count" id="importFilterCount" role="status" aria-live="polite">{{ array_sum($plan['totals']) }} records</span></div>
        <div class="import-plan-controls" hidden><div class="import-filters" role="group" aria-label="Filter change plan"><button type="button" class="active" data-import-filter="all" aria-pressed="true">All changes</button><button type="button" data-import-filter="new" aria-pressed="false">New <span>{{ $plan['totals']['new'] }}</span></button><button type="button" data-import-filter="existing" aria-pressed="false">Existing <span>{{ $plan['totals']['existing'] }}</span></button><button type="button" data-import-filter="conflict" aria-pressed="false">Conflicts <span>{{ $plan['totals']['conflicts'] }}</span></button></div><label class="import-plan-search"><x-icon name="search" :size="15" /><span class="sr-only">Search reviewed records</span><input type="search" id="importPlanSearch" placeholder="Find a record…" autocomplete="off"></label></div>
        @foreach($plan['sections'] as $type => $section)
        <details class="import-section" data-import-section @if($loop->first || $section['conflicts']) open @endif>
            <summary><span class="import-section-title"><x-icon name="{{ match($type) { 'applications' => 'apps', 'repositories' => 'git', 'databases' => 'database', 'infrastructure' => 'server', 'projects' => 'folder', default => 'check' } }}" :size="17" />{{ $section['label'] }}<span class="import-section-count">{{ count($section['rows']) }}</span></span><span class="import-section-counts">@if($section['new'])<span class="text-green">+{{ $section['new'] }} new</span>@endif @if($section['existing'])<span>{{ $section['existing'] }} kept</span>@endif @if($section['conflicts'])<span class="text-red">{{ $section['conflicts'] }} conflicts</span>@endif<x-icon name="chevron-down" :size="15" /></span></summary>
            <div class="import-table-scroll"><table class="import-table"><thead><tr><th scope="col">Record</th><th scope="col">Decision</th><th scope="col">Reason</th></tr></thead><tbody>@foreach($section['rows'] as $row)<tr data-import-row data-action="{{ $row['action'] }}" data-search="{{ strtolower($row['name'].' '.$row['identity_label'].' '.$row['reason']) }}"><td><strong>{{ $row['name'] }}</strong><span>{{ $row['identity_label'] }}</span></td><td><span class="badge {{ $row['action'] === 'new' ? 'badge-green' : ($row['action'] === 'conflict' ? 'badge-red' : 'badge-slate') }}">{{ $row['action'] === 'new' ? 'Create' : ($row['action'] === 'conflict' ? 'Conflict' : 'Keep existing') }}</span></td><td>{{ $row['reason'] }}</td></tr>@endforeach</tbody></table></div>
        </details>
        @endforeach
        <div class="import-filter-empty" id="importFilterEmpty" hidden><x-icon name="search" :size="22" /><strong>No matching records</strong><span>Try another filter or search term.</span></div>
    </section>
    @endif
    <section class="card import-confirm" aria-labelledby="confirm-heading">
        <div class="import-confirm-copy"><h3 id="confirm-heading">{{ $plan['can_apply'] ? 'Ready when you are.' : 'Nothing will be written.' }}</h3><p class="muted">{{ $plan['can_apply'] ? 'NAi checks the workspace again inside a single transaction. If anything changed, the import stops and you can review a fresh plan.' : 'Resolve the issues above, or upload a different snapshot. Existing records are never changed by this workflow.' }}</p><span class="import-expiry"><x-icon name="clock" :size="14" />This private review expires 15 minutes after upload and can be applied once.</span></div>
        <div class="import-confirm-actions">
            <form method="POST" action="{{ route('settings.import.apply') }}" id="importApplyForm">@csrf<input type="hidden" name="review" value="{{ $token }}"><label class="checkbox import-confirmation"><input type="checkbox" name="confirmation" value="1" required @disabled(!$plan['can_apply'])><span>I reviewed this plan and approve creating <strong>{{ $plan['totals']['new'] }} missing {{ Str::plural('record', $plan['totals']['new']) }}</strong>.</span></label><button class="btn btn-primary btn-block" type="submit" id="importApplyButton" @disabled(!$plan['can_apply'])><x-icon name="check" :size="16" /><span>Apply reviewed import</span></button></form>
            <form method="POST" action="{{ route('settings.import.cancel') }}">@csrf<input type="hidden" name="review" value="{{ $token }}"><button class="btn btn-ghost btn-block" type="submit">Cancel review</button></form>
        </div>
    </section>
</div>
@endif
@push('scripts')<script src="{{ asset('js/import.js') }}"></script>@endpush
</x-layout>
