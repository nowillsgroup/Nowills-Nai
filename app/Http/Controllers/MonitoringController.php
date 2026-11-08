<?php

namespace App\Http\Controllers;

use App\Models\Incident;
use App\Services\MonitoringStats;
use App\Services\ResourceMonitor;
use App\Support\Audit;
use App\Support\Resources;
use Illuminate\Http\Request;

class MonitoringController extends Controller
{
    public function index(Request $request, MonitoringStats $stats)
    {
        $type = in_array($request->query('type'), Resources::MONITORED, true) ? $request->query('type') : null;
        $state = in_array($request->query('state'), ['attention', 'healthy', 'unchecked'], true) ? $request->query('state') : null;

        return view('monitoring.index', [
            'rows' => $stats->board($type, $state),
            'summary' => $stats->summary(),
            'openIncidents' => Incident::open()->orderByRaw("CASE severity WHEN 'critical' THEN 0 WHEN 'major' THEN 1 ELSE 2 END")->orderByDesc('started_at')->get(),
            'resolvedIncidents' => Incident::with('resolver')->where('status', 'resolved')->latest('resolved_at')->paginate(10)->withQueryString(),
            'type' => $type, 'state' => $state,
        ]);
    }

    public function run(ResourceMonitor $monitor)
    {
        $summary = $monitor->runAll();

        return back()->with('success', sprintf(
            'Health checks complete: %d checked, %d healthy, %d degraded, %d offline, %d skipped. %d incident(s) opened, %d resolved.',
            $summary['checked'], $summary['healthy'], $summary['degraded'], $summary['offline'], $summary['skipped'], $summary['incidents_opened'], $summary['incidents_resolved']
        ));
    }

    public function resolve(Request $request, Incident $incident)
    {
        if (! $incident->isOpen()) return back()->with('error', 'That incident is already resolved.');

        $data = $request->validate(['note' => 'nullable|string|max:1000']);
        $incident->update([
            'status' => 'resolved', 'resolved_at' => now(), 'resolution' => 'manual',
            'resolved_by' => $request->user()->id, 'resolution_note' => $data['note'] ?? null,
        ]);
        Audit::log('monitoring.incident_resolved', sprintf('Incident manually resolved by %s: %s', $request->user()->name, $incident->title));

        return back()->with('success', 'Incident marked as resolved. The next check will reopen it if the resource is still unhealthy.');
    }
}
