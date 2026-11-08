<?php

namespace App\Services;

use App\Models\Application;
use App\Models\DatabaseConnection;
use App\Models\Incident;
use App\Models\Infrastructure;
use App\Models\Project;
use App\Models\Repository;
use App\Models\ResourceCheck;
use App\Support\Resources;
use App\Support\Subjects;

class WorkspaceContext
{
    /** Compact, read-only operational context sent to NAi with a request. */
    public function snapshot(): array
    {
        return [
            'platform' => 'NAi (Nowills AI)',
            'role' => 'orchestration-interface',
            'generated_at' => now()->toIso8601String(),
            'counts' => [
                'applications' => Application::count(),
                'repositories' => Repository::count(),
                'databases' => DatabaseConnection::count(),
                'infrastructure' => Infrastructure::count(),
                'projects' => Project::count(),
            ],
            'applications' => Application::query()->select('name', 'environment', 'status', 'health_score')->limit(25)->get()->toArray(),
            'projects' => Project::query()->select('name', 'key', 'status', 'priority')->limit(25)->get()->toArray(),
            'infrastructure' => Infrastructure::query()->select('name', 'provider', 'region', 'status')->limit(25)->get()->toArray(),
        ];
    }

    /**
     * Detailed context for the single record a conversation is scoped to.
     * Metadata only: NAi holds no credentials, and nothing here is generated.
     */
    public function forSubject(string $type, int $id): ?array
    {
        $model = Subjects::find($type, $id);
        if (! $model) return null;

        $fields = [
            'applications' => ['name', 'slug', 'description', 'url', 'environment', 'status', 'tech_stack', 'health_score', 'last_checked_at', 'notes'],
            'repositories' => ['name', 'description', 'url', 'provider', 'default_branch', 'language', 'visibility', 'status'],
            'databases' => ['name', 'engine', 'version', 'host', 'port', 'environment', 'status', 'size_mb', 'backup_status', 'last_backup_at', 'notes'],
            'infrastructure' => ['name', 'type', 'provider', 'region', 'hostname', 'ip_address', 'environment', 'status', 'specs', 'ssl_expires_at', 'notes'],
            'projects' => ['name', 'key', 'description', 'status', 'priority', 'start_date', 'due_date'],
        ][$type] ?? ['name'];

        $context = [
            'type' => $type,
            'label' => Subjects::label($type),
            'id' => $id,
            'name' => $model->name,
            'attributes' => collect($fields)->mapWithKeys(fn ($field) => [$field => $model->$field])->all(),
        ];

        if ($type === 'applications') {
            $context['linked'] = [
                'repository' => $model->repository?->only(['name', 'url', 'provider', 'default_branch', 'language']),
                'databases' => $model->databases()->get(['name', 'engine', 'status', 'backup_status'])->toArray(),
                'infrastructure' => $model->infrastructure()->get(['name', 'type', 'provider', 'status'])->toArray(),
            ];
        } elseif (in_array($type, ['repositories', 'databases', 'infrastructure'], true)) {
            $context['linked'] = ['application' => $model->application?->only(['name', 'environment', 'status'])];
        } elseif ($type === 'projects') {
            $context['tasks'] = $model->tasks()->limit(40)->get(['title', 'status', 'priority', 'due_date'])->toArray();
            $context['progress_percent'] = $model->progress();
        }

        if (in_array($type, Resources::MONITORED, true)) {
            $context['monitoring'] = [
                'recent_checks' => ResourceCheck::for($type, $id)->latest('checked_at')->limit(10)
                    ->get(['status', 'latency_ms', 'http_code', 'message', 'source', 'checked_at'])->toArray(),
                'open_incidents' => Incident::open()->forResource($type, $id)->latest('started_at')
                    ->get(['title', 'severity', 'trigger_status', 'detail', 'check_count', 'started_at', 'source'])->toArray(),
            ];
        }

        return $context;
    }
}
