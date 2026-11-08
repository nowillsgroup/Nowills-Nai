<x-layout :title="$mode==='create'?'New project':'Edit project'" subtitle="Projects">
<div class="page-head"><div><h2>{{ $mode==='create'?'New project':'Edit project' }}</h2><p>Define the initiative, ownership, and delivery window.</p></div><div class="head-actions"><a href="{{ route('projects.index') }}" class="btn">Cancel</a></div></div>
<div class="card form-card"><form method="POST" action="{{ $mode==='create'?route('projects.store'):route('projects.update',$project) }}">@csrf @if($mode==='edit')@method('PUT')@endif
<div class="form-grid">
<div class="field full"><label>Name <span class="required">*</span></label><input class="input" name="name" value="{{ old('name',$project->name) }}" required placeholder="Customer portal redesign"></div>
<div class="field"><label>Project key <span class="required">*</span></label><input class="input mono" name="key" value="{{ old('key',$project->key) }}" required placeholder="PORTAL" pattern="[A-Z0-9-]+"><div class="help">Uppercase letters, numbers, and hyphens.</div></div>
<div class="field"><label>Owner</label><select name="owner_id"><option value="">Unassigned</option>@foreach($owners as $id=>$name)<option value="{{ $id }}" @selected(old('owner_id',$project->owner_id)==$id)>{{ $name }}</option>@endforeach</select></div>
<div class="field"><label>Status</label><select name="status">@foreach(['planning'=>'Planning','active'=>'Active','on_hold'=>'On hold','completed'=>'Completed'] as $val=>$label)<option value="{{ $val }}" @selected(old('status',$project->status ?? 'planning')===$val)>{{ $label }}</option>@endforeach</select></div>
<div class="field"><label>Priority</label><select name="priority">@foreach(['low'=>'Low','medium'=>'Medium','high'=>'High','urgent'=>'Urgent'] as $val=>$label)<option value="{{ $val }}" @selected(old('priority',$project->priority ?? 'medium')===$val)>{{ $label }}</option>@endforeach</select></div>
<div class="field"><label>Start date</label><input class="input" type="date" name="start_date" value="{{ old('start_date', $project->start_date?->format('Y-m-d')) }}"></div>
<div class="field"><label>Due date</label><input class="input" type="date" name="due_date" value="{{ old('due_date', $project->due_date?->format('Y-m-d')) }}"></div>
<div class="field full"><label>Description</label><textarea name="description" placeholder="Scope, goals, and delivery notes">{{ old('description',$project->description) }}</textarea></div>
</div>
<div class="form-actions"><a href="{{ route('projects.index') }}" class="btn">Cancel</a><button class="btn btn-primary"><x-icon name="check" :size="16" />{{ $mode==='create'?'Create project':'Save changes' }}</button></div>
</form></div>
</x-layout>
