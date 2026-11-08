<x-layout title="Projects" subtitle="Development projects and delivery tracking">
<div class="page-head"><div><h2>Projects</h2><p>Coordinate development initiatives, track delivery, and keep technical work organized.</p></div><div class="head-actions">@can('write')<a href="{{ route('projects.create') }}" class="btn btn-primary"><x-icon name="plus" :size="16" />New project</a>@endcan</div></div>
<form method="GET" class="toolbar"><div class="search-field"><input class="input" name="q" value="{{ request('q') }}" placeholder="Search projects…"></div><select name="status" onchange="this.form.submit()"><option value="">All statuses</option>@foreach(['planning'=>'Planning','active'=>'Active','on_hold'=>'On hold','completed'=>'Completed'] as $val=>$label)<option value="{{ $val }}" @selected(request('status')===$val)>{{ $label }}</option>@endforeach</select><button class="btn"><x-icon name="filter" :size="16" />Filter</button></form>
<div class="grid grid-3">
@forelse($projects as $project)
<a href="{{ route('projects.show',$project) }}" class="card project-card">
<div class="flex items-center justify-between"><span class="project-key">{{ $project->key }}</span><x-badge :status="$project->status" /></div>
<h3>{{ $project->name }}</h3><p>{{ $project->description ?? 'No description provided.' }}</p>
<div><div class="flex justify-between" style="font-size:11px;color:var(--faint);margin-bottom:6px"><span>{{ $project->progress() }}% complete</span><span>{{ $project->tasks_count }} tasks</span></div><div class="progress"><div class="progress-bar" style="width:{{ $project->progress() }}%"></div></div></div>
<div class="project-meta"><span>{{ $project->owner?->name ?? 'Unassigned' }}</span><span class="flex gap-8 items-center"><x-badge :status="$project->priority" /> @if($project->due_date)<span>{{ $project->due_date->format('M j') }}</span>@endif</span></div>
</a>
@empty
<div class="card empty" style="grid-column:1/-1"><x-icon name="folder" :size="30" /><h3>No projects yet</h3><p>Create a project to organize tasks and delivery.</p>@can('write')<a href="{{ route('projects.create') }}" class="btn btn-primary">Create project</a>@endcan</div>
@endforelse
</div>
<x-pagination :paginator="$projects" />
</x-layout>
