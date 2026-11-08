<?php

namespace App\Http\Controllers;

use App\Models\Activity;
use App\Models\ResourceCheck;
use App\Services\WorkspaceExporter;
use App\Support\Inventory;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ExportController extends Controller
{
    public function inventory(string $type): StreamedResponse
    {
        $cfg = Inventory::type($type);
        $fields = array_keys($cfg['fields']);
        $filename = 'nai-'.$type.'-'.now()->format('Ymd-His').'.csv';

        return response()->streamDownload(function () use ($cfg, $fields) {
            $handle = fopen('php://output', 'w');
            fputcsv($handle, array_map(fn ($f) => $cfg['fields'][$f]['label'], $fields));
            $query = $cfg['model']::query();
            if (in_array('owner_id', array_keys($cfg['fields']))) $query->with('owner');
            if (in_array('application_id', array_keys($cfg['fields']))) $query->with('application');
            $query->orderBy('id')->chunk(200, function ($items) use ($handle, $fields, $cfg) {
                foreach ($items as $item) {
                    $row = [];
                    foreach ($fields as $field) {
                        $value = $item->$field;
                        if ($field === 'tech_stack' && is_array($value)) $value = implode(', ', $value);
                        if (($cfg['fields'][$field]['type'] ?? '') === 'owner') $value = $item->owner?->name;
                        if (($cfg['fields'][$field]['type'] ?? '') === 'application') $value = $item->application?->name;
                        if ($value instanceof \DateTimeInterface) $value = $value->format('Y-m-d H:i');
                        $row[] = $this->csvSafe($value ?? '');
                    }
                    fputcsv($handle, $row);
                }
            });
            fclose($handle);
        }, $filename, ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    public function activity(): StreamedResponse
    {
        $filename = 'nai-activity-'.now()->format('Ymd-His').'.csv';
        return response()->streamDownload(function () {
            $handle = fopen('php://output', 'w');
            fputcsv($handle, ['Timestamp', 'User', 'Action', 'Description']);
            Activity::with('user')->orderBy('id')->chunk(200, function ($items) use ($handle) {
                foreach ($items as $item) fputcsv($handle, array_map([$this, 'csvSafe'], [$item->created_at?->toIso8601String(), $item->user?->email ?? 'system', $item->action, $item->description]));
            });
            fclose($handle);
        }, $filename, ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    public function checks(): StreamedResponse
    {
        $filename = 'nai-monitoring-'.now()->format('Ymd-His').'.csv';
        return response()->streamDownload(function () {
            $handle = fopen('php://output', 'w');
            fputcsv($handle, ['Checked at', 'Resource type', 'Resource', 'Target', 'Status', 'HTTP code', 'Latency (ms)', 'Source', 'Message']);
            ResourceCheck::orderBy('id')->chunk(500, function ($items) use ($handle) {
                foreach ($items as $item) {
                    fputcsv($handle, array_map([$this, 'csvSafe'], [
                        $item->checked_at?->toIso8601String(), $item->resource_type, $item->resource_name, $item->target,
                        $item->status, $item->http_code, $item->latency_ms, $item->source, $item->message,
                    ]));
                }
            });
            fclose($handle);
        }, $filename, ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    private function csvSafe(mixed $value): string
    {
        $value = (string) $value;
        return preg_match('/^[=+\-@\t\r]/', $value) ? "'".$value : $value;
    }

    public function workspace(WorkspaceExporter $exporter)
    {
        $filename = 'nai-workspace-'.now()->format('Ymd-His').'.json';
        return response()->streamDownload(function () use ($exporter) {
            echo json_encode($exporter->snapshot(), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        }, $filename, ['Content-Type' => 'application/json']);
    }
}
