<?php

namespace App\Http\Controllers;

use App\Models\Activity;
use App\Models\Application;
use App\Models\DatabaseConnection;
use App\Models\Infrastructure;
use App\Models\Project;
use App\Models\Repository;
use App\Services\ArenaGateway;

class DashboardController extends Controller
{
    public function __invoke(ArenaGateway $arena)
    {
        $apps = Application::with('owner')->latest()->limit(6)->get();
        $projects = Project::with('owner')->withCount('tasks')->whereIn('status', ['active', 'planning'])->orderBy('due_date')->limit(4)->get();
        $infra = Infrastructure::orderByRaw("CASE status WHEN 'offline' THEN 0 WHEN 'degraded' THEN 1 WHEN 'maintenance' THEN 2 ELSE 3 END")->limit(6)->get();
        $totalResources = Application::count() + Repository::count() + DatabaseConnection::count() + Infrastructure::count();
        $healthy = Application::where('status', 'healthy')->count() + DatabaseConnection::where('status', 'online')->count() + Infrastructure::where('status', 'online')->count();
        $monitored = Application::count() + DatabaseConnection::count() + Infrastructure::count();
        $attention = Application::whereIn('status', ['degraded', 'offline'])->count() + DatabaseConnection::whereIn('status', ['degraded', 'offline'])->count() + Infrastructure::whereIn('status', ['degraded', 'offline'])->count() + DatabaseConnection::where('backup_status', 'failed')->count();

        return view('dashboard.index', [
            'stats' => [
                'applications' => Application::count(), 'repositories' => Repository::count(),
                'databases' => DatabaseConnection::count(), 'infrastructure' => Infrastructure::count(),
                'projects' => Project::whereIn('status', ['active', 'planning'])->count(),
                'total' => $totalResources,
            ],
            'healthPercent' => $monitored ? (int) round($healthy / $monitored * 100) : 0,
            'attention' => $attention,
            'apps' => $apps, 'projects' => $projects, 'infra' => $infra,
            'activities' => Activity::with('user')->latest('created_at')->limit(7)->get(),
            'arenaStatus' => $arena->status(), 'arenaConfigured' => $arena->isConfigured(),
            'resourceMix' => [
                ['label' => 'Applications', 'value' => Application::count(), 'color' => '#38bdf8'],
                ['label' => 'Repositories', 'value' => Repository::count(), 'color' => '#818cf8'],
                ['label' => 'Databases', 'value' => DatabaseConnection::count(), 'color' => '#c084fc'],
                ['label' => 'Infrastructure', 'value' => Infrastructure::count(), 'color' => '#f472b6'],
            ],
        ]);
    }
}
