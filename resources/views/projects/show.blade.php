<x-layout :title="$project->name" :subtitle="'Project '.$project->key">
<div class="page-head"><div><h2>{{ $project->name }} <span class="project-key" style="vertical-align:middle">{{ $project->key }}</span></h2><p>{{ $project->description }}</p></div><div class="head-actions">@can('write')<a href="{{ route('projects.edit',$project) }}" class="btn"><x-icon name="edit" :size="16" />Edit</a><form method="POST" action="{{ route('projects.destroy',$project) }}" data-confirm="Delete this project and all tasks?">@csrf @method('DELETE')<button class="btn btn-danger"><x-icon name="trash" :size="16" />Delete</button></form>@endcan</div></div>
<div class="card" style="margin-bottom:18px"><div class="flex items-center justify-between gap-12" style="flex-wrap:wrap"><div class="flex gap-8 items-center"><x-badge :status="$project->status" /><x-badge :status="$project->priority" /><span class="faint" style="font-size:12px">Owner: {{ $project->owner?->name ?? 'Unassigned' }}</span>@if($project->due_date)<span class="faint" style="font-size:12px">Due {{ $project->due_date->format('M j, Y') }}</span>@endif</div><div style="min-width:220px;flex:1;max-width:360px"><div class="flex justify-between" style="font-size:11px;color:var(--faint);margin-bottom:6px"><span>{{ $project->progress() }}% complete</span><span>{{ $project->tasks->where('status','done')->count() }}/{{ $project->tasks->count() }}</span></div><div class="progress"><div class="progress-bar" style="width:{{ $project->progress() }}%"></div></div></div></div></div>
@can('write')
<details class="card" style="margin-bottom:18px" @if($errors->any()) open @endif><summary style="cursor:pointer;font-weight:600;font-size:13px"><x-icon name="plus" :size="15" /> Add task</summary>
<form method="POST" action="{{ route('projects.tasks.store',$project) }}" class="mt-16">@csrf<div class="form-grid">
<div class="field full"><label>Title</label><input class="input" name="title" required placeholder="Task title"></div>
<div class="field"><label>Status</label><select name="status">@foreach($columns as $val=>$label)<option value="{{ $val }}">{{ $label }}</option>@endforeach</select></div>
<div class="field"><label>Priority</label><select name="priority">@foreach(['low'=>'Low','medium'=>'Medium','high'=>'High','urgent'=>'Urgent'] as $val=>$label)<option value="{{ $val }}" @selected($val==='medium')>{{ $label }}</option>@endforeach</select></div>
<div class="field"><label>Assignee</label><select name="assignee_id"><option value="">Unassigned</option>@foreach($owners as $id=>$name)<option value="{{ $id }}">{{ $name }}</option>@endforeach</select></div>
<div class="field"><label>Due date</label><input class="input" type="date" name="due_date"></div>
<div class="field full"><label>Description</label><textarea name="description"></textarea></div>
</div><div class="form-actions"><button class="btn btn-primary">Add task</button></div></form></details>
@endcan
<div class="kanban">
@foreach($columns as $status=>$label)
<div class="kanban-col"><div class="kanban-head"><span>{{ $label }}</span><span class="kanban-count">{{ ($grouped[$status] ?? collect())->count() }}</span></div>
@foreach(($grouped[$status] ?? collect()) as $task)
<div class="task-card"><h4>{{ $task->title }}</h4>@if($task->description)<p class="faint" style="font-size:11.5px;margin:0">{{ \Illuminate\Support\Str::limit($task->description,90) }}</p>@endif<div class="task-meta"><x-badge :status="$task->priority" />@if($task->due_date)<span class="faint" style="font-size:11px">{{ $task->due_date->format('M j') }}</span>@endif</div><div class="flex items-center justify-between gap-8 mt-16"><span class="faint" style="font-size:11px">{{ $task->assignee?->name ?? 'Unassigned' }}</span>@can('write')<div class="flex gap-8"><form method="POST" action="{{ route('projects.tasks.update',['project'=>$project,'task'=>$task]) }}" data-autosubmit>@csrf @method('PATCH')<select name="status">@foreach($columns as $val=>$lab)<option value="{{ $val }}" @selected($task->status===$val)>{{ $lab }}</option>@endforeach</select></form><form method="POST" action="{{ route('projects.tasks.destroy',['project'=>$project,'task'=>$task]) }}" data-confirm="Delete task?">@csrf @method('DELETE')<button class="icon-btn" style="width:28px;height:28px"><x-icon name="trash" :size="13" /></button></form></div>@endcan</div></div>
@endforeach
</div>
@endforeach
</div>
</x-layout>
