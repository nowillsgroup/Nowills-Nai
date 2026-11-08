<?php

namespace App\Services;

use App\Models\Application;
use App\Models\ArenaConnection;
use App\Models\DatabaseConnection;
use App\Models\Incident;
use App\Models\Infrastructure;
use App\Models\Project;
use App\Models\Repository;
use App\Models\Setting;

class WorkspaceExporter
{
    /** Operational backup. Deliberately excludes passwords and Arena API keys. */
    public function snapshot(): array
    {
        $arena = ArenaConnection::first();
        return [
            'exported_at' => now()->toIso8601String(),
            'platform' => 'NAi',
            'workspace' => Setting::where('key', 'workspace_name')->value('value') ?? config('nai.workspace'),
            'arena_connection' => $arena ? [
                'base_url' => $arena->base_url,
                'workspace_ref' => $arena->workspace_ref,
                'chat_path' => $arena->chat_path,
                'health_path' => $arena->health_path,
                'status' => $arena->status,
            ] : null,
            'applications' => Application::with('owner:id,name')->get()->makeHidden(['owner_id'])->toArray(),
            'repositories' => Repository::all()->toArray(),
            'databases' => DatabaseConnection::all()->toArray(),
            'infrastructure' => Infrastructure::all()->toArray(),
            'projects' => Project::with(['owner:id,name', 'tasks'])->get()->toArray(),
            'monitoring' => [
                'retention_days' => (int) config('nai.monitoring.retention_days', 30),
                'open_incidents' => Incident::open()->orderByDesc('started_at')->get()->makeHidden(['resolved_by', 'resolution_note'])->toArray(),
                'recent_resolved_incidents' => Incident::where('status', 'resolved')->latest('resolved_at')->limit(50)->get()->makeHidden(['resolved_by', 'resolution_note'])->toArray(),
            ],
        ];
    }
}
