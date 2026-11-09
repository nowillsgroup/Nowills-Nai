<x-layout title="Settings" subtitle="Workspace and NAi configuration">
<div class="page-head"><div><h2>Settings</h2><p>Configure the Nowills Group workspace and the upstream NAi engine connection.</p></div></div>
<div class="settings-grid">
<form method="POST" action="{{ route('settings.update') }}" class="grid" style="gap:18px">@csrf @method('PUT')
<div class="card"><div class="card-head"><h3>Workspace</h3></div><div class="field"><label>Workspace name</label><input class="input" name="workspace_name" value="{{ old('workspace_name',$workspace) }}" required></div></div>
<div class="card"><div class="card-head"><h3>NAi browser</h3></div>
<div class="notice"><x-icon name="globe" :size="16" /><span>The “NAi · Browser” button opens this address in a full-height browser frame inside NAi, keeping NAi branding and side navigation around it. Sign-in happens on the framed site itself; NAi never sees or stores those credentials.</span></div>
<div class="field"><label>Browser start address</label><input class="input" type="url" name="arena_portal_url" value="{{ old('arena_portal_url', \App\Models\Setting::where('key','arena_portal_url')->value('value') ?? config('nai.arena.portal_url')) }}" placeholder="https://arena.ai"><div class="help">Absolute http(s) URL. If the site refuses to be framed, the browser view offers “Open in tab” instead.</div></div>
</div>
<div class="card"><div class="card-head"><h3>NAi connection</h3><x-badge :status="$arenaStatus" /></div>
<div class="notice"><x-icon name="cpu" :size="16" /><span>NAi is an interface and orchestration layer. Enter the approved NAi API endpoint or bridge for your NAi deployment. NAi does not run a model.</span></div>
<div class="field"><label>NAi API base URL</label><input class="input" type="url" name="base_url" value="{{ old('base_url',$settings['base_url']) }}" placeholder="https://nai.your-domain.com/api"><div class="help">The upstream NAi service that receives prompts and returns responses.</div></div>
<div class="field"><label>API key / bearer token</label><input class="input" type="password" name="api_key" placeholder="{{ $settings['api_key'] ? '•••••••••••••••• (leave blank to keep)' : 'Enter NAi API token' }}" autocomplete="new-password"><div class="help">Stored encrypted at rest. Never exposed to the browser.</div></div>
<div class="field"><label class="checkbox"><input type="checkbox" name="clear_api_key" value="1"> Clear stored API key</label></div>
<div class="form-grid">
<div class="field"><label>Workspace reference</label><input class="input" name="workspace_ref" value="{{ old('workspace_ref',$settings['workspace_ref']) }}" placeholder="nowills-group"></div>
<div class="field"><label>Request timeout (env)</label><input class="input" value="{{ config('nai.arena.timeout') }}s" disabled><div class="help">Set ARENA_TIMEOUT in .env.</div></div>
<div class="field"><label>Chat path</label><input class="input mono" name="chat_path" value="{{ old('chat_path',$settings['chat_path']) }}" required></div>
<div class="field"><label>Health path</label><input class="input mono" name="health_path" value="{{ old('health_path',$settings['health_path']) }}" required></div>
</div>
@if($connection->last_error)<div class="notice warn"><x-icon name="alert" :size="16" /><span>Last error: {{ $connection->last_error }}</span></div>@endif
<div class="form-actions"><button class="btn btn-primary"><x-icon name="check" :size="16" />Save settings</button></div>
</div>
</form>
<div class="grid" style="gap:18px">
<div class="card"><div class="card-head"><h3>Connection test</h3></div><p class="muted" style="font-size:12.5px">Verify that NAi can reach the configured NAi health endpoint.</p><form method="POST" action="{{ route('arena.test') }}">@csrf<button class="btn btn-block"><x-icon name="refresh" :size="16" />Test NAi connection</button></form>@if($connection->last_checked_at)<div class="faint" style="font-size:11.5px;margin-top:12px">Last checked {{ $connection->last_checked_at->diffForHumans() }}</div>@endif</div>
<div class="card"><div class="card-head"><h3>Architecture</h3></div><dl class="mb-0"><div class="kv"><dt>Interface</dt><dd>NAi · Laravel</dd></div><div class="kv"><dt>Database</dt><dd>MySQL (production target)<br><span class="faint">Current runtime: {{ \DB::connection()->getDriverName() }}</span></dd></div><div class="kv"><dt>AI engine</dt><dd>NAi (upstream)</dd></div><div class="kv"><dt>Local model</dt><dd>None</dd></div><div class="kv"><dt>Node / Docker</dt><dd>Not required</dd></div></dl></div>
<div class="card"><div class="card-head"><h3>Workspace backup</h3><x-badge status="active" label="Version 1" /></div><p class="muted" style="font-size:12.5px">Export inventory, projects, and connection metadata as JSON. Passwords and NAi API keys are excluded.</p><a href="{{ route('settings.export') }}" class="btn btn-block"><x-icon name="download" :size="16" />Export workspace JSON</a><a href="{{ route('settings.import') }}" class="btn btn-block" style="margin-top:10px"><x-icon name="upload" :size="16" />Review a workspace import</a><div class="help" style="margin-top:10px">Preview first. Imports only add missing records; they never overwrite or delete.</div></div>
<div class="card"><div class="card-head"><h3>Security notes</h3></div><ul class="muted" style="font-size:12.5px;padding-left:18px;margin:0;line-height:1.8"><li>API keys are encrypted with Laravel APP_KEY.</li><li>Inventory forms store metadata, not credentials.</li><li>Role-based access: admin, operator, viewer.</li><li>All changes are written to the activity log.</li></ul></div>
</div>
</div>
</x-layout>
