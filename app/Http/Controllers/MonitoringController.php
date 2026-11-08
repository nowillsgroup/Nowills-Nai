<?php

namespace App\Http\Controllers;

use App\Services\ResourceMonitor;

class MonitoringController extends Controller
{
    public function run(ResourceMonitor $monitor)
    {
        $summary = $monitor->runAll();
        return back()->with('success', sprintf('Health checks complete: %d checked, %d healthy, %d degraded, %d offline.', $summary['checked'], $summary['healthy'], $summary['degraded'], $summary['offline']));
    }
}
