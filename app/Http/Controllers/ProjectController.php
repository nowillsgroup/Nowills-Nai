<?php

namespace App\Http\Controllers;

use App\Models\Project;
use App\Models\Task;
use App\Models\User;
use App\Support\Audit;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class ProjectController extends Controller
{
    public function index(Request $request)
    {
        $query = Project::with('owner')->withCount('tasks');
        if ($request->filled('status')) $query->where('status', $request->string('status'));
        if ($request->filled('q')) $query->where('name', 'like', '%'.$request->string('q').'%');
        $projects = $query->orderByRaw("CASE status WHEN 'active' THEN 0 WHEN 'planning' THEN 1 WHEN 'on_hold' THEN 2 ELSE 3 END")->paginate(12)->withQueryString();
        return view('projects.index', compact('projects'));
    }

    public function create()
    {
        return view('projects.form', ['project' => new Project(), 'mode' => 'create', 'owners' => User::orderBy('name')->pluck('name', 'id')]);
    }

    public function store(Request $request)
    {
        $data = $request->validate($this->rules());
        $project = Project::create($data);
        Audit::log('project.created', 'Created project “'.$project->name.'”', $project);
        return redirect()->route('projects.show', $project)->with('success', 'Project created.');
    }

    public function show(Project $project)
    {
        $project->load(['owner', 'tasks.assignee']);
        $columns = ['backlog' => 'Backlog', 'in_progress' => 'In progress', 'review' => 'In review', 'done' => 'Done'];
        $grouped = $project->tasks->groupBy('status');
        return view('projects.show', compact('project', 'columns', 'grouped') + ['owners' => User::orderBy('name')->pluck('name', 'id')]);
    }

    public function edit(Project $project)
    {
        return view('projects.form', ['project' => $project, 'mode' => 'edit', 'owners' => User::orderBy('name')->pluck('name', 'id')]);
    }

    public function update(Request $request, Project $project)
    {
        $data = $request->validate($this->rules($project->id));
        $project->update($data);
        Audit::log('project.updated', 'Updated project “'.$project->name.'”', $project);
        return redirect()->route('projects.show', $project)->with('success', 'Project updated.');
    }

    public function destroy(Project $project)
    {
        $name = $project->name; $project->delete();
        Audit::log('project.deleted', 'Deleted project “'.$name.'”');
        return redirect()->route('projects.index')->with('success', 'Project deleted.');
    }

    public function storeTask(Request $request, Project $project)
    {
        $data = $request->validate(['title' => 'required|string|max:200', 'description' => 'nullable|string|max:2000', 'status' => 'required|in:backlog,in_progress,review,done', 'priority' => 'required|in:low,medium,high,urgent', 'assignee_id' => 'nullable|exists:users,id', 'due_date' => 'nullable|date']);
        $task = $project->tasks()->create($data);
        Audit::log('task.created', 'Added task “'.$task->title.'” to '.$project->key, $task);
        return back()->with('success', 'Task added.');
    }

    public function updateTask(Request $request, Project $project, Task $task)
    {
        abort_unless($task->project_id === $project->id, 404);
        $data = $request->validate(['status' => 'required|in:backlog,in_progress,review,done']);
        $task->update($data);
        Audit::log('task.updated', 'Moved task “'.$task->title.'” to '.str_replace('_', ' ', $data['status']), $task);
        return back()->with('success', 'Task updated.');
    }

    public function destroyTask(Project $project, Task $task)
    {
        abort_unless($task->project_id === $project->id, 404);
        $title = $task->title; $task->delete();
        Audit::log('task.deleted', 'Deleted task “'.$title.'”');
        return back()->with('success', 'Task deleted.');
    }

    private function rules(?int $id = null): array
    {
        return ['name' => 'required|string|max:160', 'key' => ['required', 'string', 'max:20', 'regex:/^[A-Z0-9-]+$/', Rule::unique('projects', 'key')->ignore($id)], 'description' => 'nullable|string|max:3000', 'status' => 'required|in:planning,active,on_hold,completed', 'priority' => 'required|in:low,medium,high,urgent', 'owner_id' => 'nullable|exists:users,id', 'start_date' => 'nullable|date', 'due_date' => 'nullable|date|after_or_equal:start_date'];
    }
}
