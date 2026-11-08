<?php

namespace App\Services;

use App\Models\Application;
use App\Models\DatabaseConnection;
use App\Models\Infrastructure;
use App\Models\Project;
use App\Models\Repository;

class WorkspaceContext
{
    /** Compact, read-only operational context sent to Arena AI with a request. */
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
}
