<x-layout title="Settings" subtitle="Workspace and Arena AI configuration">
<div class="page-head"><div><h2>Settings</h2><p>Configure the Nowills Group workspace and the upstream Arena AI engine connection.</p></div></div>
<div class="settings-grid">
<form method="POST" action="{{ route('settings.update') }}" class="grid" style="gap:18px">@csrf @method('PUT')
<div class="card"><div class="card-head"><h3>Workspace</h3></div><div class="field"><label>Workspace name</label><input class="input" name="workspace_name" value="{{ old('workspace_name',$workspace) }}" required></div></div>
<div class="card"><div class="card-head"><h3>Arena AI connection</h3><x-badge :status="$arenaStatus" /></div>
<div class="notice"><x-icon name="cpu" :size="16" /><span>NAi is an interface and orchestration layer. Enter the approved Arena AI API endpoint or bridge for your Arena deployment. NAi does not run a model.</span></div>
<div class="field"><label>Arena API base URL</label><input class="input" type="url" name="base_url" value="{{ old('base_url',$settings['base_url']) }}" placeholder="https://arena.your-domain.com/api"><div class="help">The upstream Arena AI service that receives prompts and returns responses.</div></div>
<div class="field"><label>API key / bearer token</label><input class="input" type="password" name="api_key" placeholder="{{ $settings['api_key'] ? '•••••••••••••••• (leave blank to keep)' : 'Enter Arena API token' }}" autocomplete="new-password"><div class="help">Stored encrypted at rest. Never exposed to the browser.</div></div>
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
<div class="card"><div class="card-head"><h3>Connection test</h3></div><p class="muted" style="font-size:12.5px">Verify that NAi can reach the configured Arena AI health endpoint.</p><form method="POST" action="{{ route('arena.test') }}">@csrf<button class="btn btn-block"><x-icon name="refresh" :size="16" />Test Arena connection</button></form>@if($connection->last_checked_at)<div class="faint" style="font-size:11.5px;margin-top:12px">Last checked {{ $connection->last_checked_at->diffForHumans() }}</div>@endif</div>
<div class="card"><div class="card-head"><h3>Architecture</h3></div><dl class="mb-0"><div class="kv"><dt>Interface</dt><dd>NAi · Laravel</dd></div><div class="kv"><dt>Database</dt><dd>MySQL (production target)<br><span class="faint">Current runtime: {{ \DB::connection()->getDriverName() }}</span></dd></div><div class="kv"><dt>AI engine</dt><dd>Arena AI (upstream)</dd></div><div class="kv"><dt>Local model</dt><dd>None</dd></div><div class="kv"><dt>Node / Docker</dt><dd>Not required</dd></div></dl></div>
<div class="card"><div class="card-head"><h3>Workspace backup</h3></div><p class="muted" style="font-size:12.5px">Export operational inventory, projects, and connection metadata as JSON. Passwords and Arena API keys are excluded.</p><a href="{{ route('settings.export') }}" class="btn btn-block"><x-icon name="download" :size="16" />Export workspace JSON</a></div>
<div class="card"><div class="card-head"><h3>Security notes</h3></div><ul class="muted" style="font-size:12.5px;padding-left:18px;margin:0;line-height:1.8"><li>API keys are encrypted with Laravel APP_KEY.</li><li>Inventory forms store metadata, not credentials.</li><li>Role-based access: admin, operator, viewer.</li><li>All changes are written to the activity log.</li></ul></div>
</div>
</div>
</x-layout>
