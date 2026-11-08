<?php

namespace App\Services;

use App\Models\Application;
use App\Models\DatabaseConnection;
use App\Models\Infrastructure;
use App\Models\Project;
use App\Models\Repository;
use App\Models\Task;
use App\Support\Audit;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use RuntimeException;

/**
 * Additive, portable workspace imports. Source IDs are file-local references,
 * never destination IDs. Validation and planning are separate from writes.
 */
class WorkspaceImporter
{
    public const VERSION = 1;
    public const MAX_BYTES = 5 * 1024 * 1024;
    public const SECTIONS = [
        'applications' => 'Applications', 'repositories' => 'Repositories',
        'databases' => 'Databases', 'infrastructure' => 'Infrastructure',
        'projects' => 'Projects', 'tasks' => 'Tasks',
    ];
    private const LIMITS = ['applications' => 500, 'repositories' => 1000, 'databases' => 500, 'infrastructure' => 500, 'projects' => 500, 'tasks' => 5000];
    private const MODELS = [
        'applications' => Application::class, 'repositories' => Repository::class,
        'databases' => DatabaseConnection::class, 'infrastructure' => Infrastructure::class,
        'projects' => Project::class, 'tasks' => Task::class,
    ];

    private array $errors = [];
    private array $warnings = [];
    private int $errorCount = 0;

    public function preview(string $json, string $filename = 'workspace.json'): array
    {
        $this->errors = $this->warnings = [];
        $this->errorCount = 0;
        $filename = preg_replace('/[\x00-\x1F]/', '', $filename);
        if (strlen($json) > self::MAX_BYTES) {
            $this->error('file', 'The snapshot exceeds the 5 MB upload limit.');
            return $this->emptyPlan($filename);
        }
        try {
            $source = json_decode($json, true, 32, JSON_THROW_ON_ERROR);
        } catch (\JsonException) {
            $this->error('file', 'The file is not valid JSON, or nests data more than 32 levels deep.');
            return $this->emptyPlan($filename);
        }
        if (!is_array($source) || array_is_list($source)) {
            $this->error('file', 'The snapshot must be a JSON object.');
            return $this->emptyPlan($filename);
        }
        if (($source['format_version'] ?? null) !== self::VERSION || ($source['platform'] ?? null) !== 'NAi') {
            $this->error('format_version', 'Use a version 1 NAi workspace export. Older or third-party formats are not accepted.');
            return $this->emptyPlan($filename);
        }
        $meta = Validator::make($source, ['workspace' => 'required|string|max:120', 'exported_at' => 'required|date']);
        if ($meta->fails()) $this->error('snapshot', 'Snapshot metadata must include a workspace name and a valid export date.');
        $payload = ['format_version' => self::VERSION, 'sections' => []];
        $sourceApps = [];
        foreach (array_keys(self::SECTIONS) as $type) {
            if ($type === 'tasks') continue;
            $rows = $source[$type] ?? null;
            if (!is_array($rows) || !array_is_list($rows)) {
                $this->error($type, 'This section must be an array of records.');
                $payload['sections'][$type] = [];
                continue;
            }
            if (count($rows) > self::LIMITS[$type]) $this->error($type, 'This section exceeds the '.self::LIMITS[$type].' record import limit.');
            $normalized = [];
            $keys = [];
            $ids = [];
            foreach (array_slice($rows, 0, self::LIMITS[$type]) as $i => $row) {
                $label = self::SECTIONS[$type].' row '.($i + 1);
                if (!is_array($row) || array_is_list($row)) { $this->error($label, 'Expected a record object.'); continue; }
                if ($type === 'applications') {
                    if (!isset($row['id']) || !is_int($row['id']) || $row['id'] < 1) $this->error($label, 'A positive source ID is required for application links.');
                    elseif (isset($ids[$row['id']])) $this->error($label, 'Source application IDs must be unique within the file.');
                    else $ids[$row['id']] = true;
                }
                $data = $this->normalize($type, $row, $label);
                if ($data === null) continue;
                $key = $this->key($type, $data);
                if (isset($keys[$key])) $this->error($label, 'Duplicate record identity in this section. Each record must have a distinct import identity.');
                $keys[$key] = true;
                $item = ['key' => $key, 'data' => $data];
                if ($type === 'applications') {
                    $item['source_id'] = $row['id'] ?? null;
                    if (is_int($item['source_id'])) $sourceApps[$item['source_id']] = $key;
                }
                if (in_array($type, ['repositories', 'databases', 'infrastructure'], true)) {
                    $ref = $row['application_id'] ?? null;
                    if ($ref !== null && !is_int($ref)) $this->error($label, 'Application links must use a positive integer source ID.');
                    $item['application_source_id'] = $ref;
                }
                if ($type === 'projects') {
                    $tasks = $row['tasks'] ?? null;
                    if (!is_array($tasks) || !array_is_list($tasks)) { $this->error($label, 'Project tasks must be an array.'); $tasks = []; }
                    if (count($tasks) > 100) $this->error($label, 'A project may contain at most 100 imported tasks.');
                    $item['tasks'] = [];
                    $taskKeys = [];
                    foreach (array_slice($tasks, 0, 100) as $j => $task) {
                        $taskLabel = $label.', task '.($j + 1);
                        if (!is_array($task) || array_is_list($task)) { $this->error($taskLabel, 'Expected a task object.'); continue; }
                        $taskData = $this->normalize('tasks', $task, $taskLabel);
                        if ($taskData === null) continue;
                        $taskKey = $this->key('tasks', $taskData);
                        if (isset($taskKeys[$taskKey])) $this->error($taskLabel, 'Exact duplicate task title and description in this project.');
                        $taskKeys[$taskKey] = true;
                        $item['tasks'][] = ['key' => $taskKey, 'data' => $taskData];
                    }
                }
                $normalized[] = $item;
            }
            $payload['sections'][$type] = $normalized;
        }
        $totalTasks = array_sum(array_map(fn ($p) => count($p['tasks']), $payload['sections']['projects'] ?? []));
        if ($totalTasks > self::LIMITS['tasks']) $this->error('tasks', 'The snapshot exceeds the 5,000 task import limit.');
        foreach ($payload['sections'] as $type => $items) {
            foreach ($items as $item) {
                if (array_key_exists('application_source_id', $item) && $item['application_source_id'] !== null) {
                    $item['application_key'] = $sourceApps[$item['application_source_id']] ?? null;
                    if ($item['application_key'] === null) $this->error($type, 'An application link points to an invalid or missing source record.');
                }
            }
            // References are resolved again during planning; keep only portable keys.
            $payload['sections'][$type] = array_map(function ($item) use ($sourceApps) {
                if (array_key_exists('application_source_id', $item)) {
                    $item['application_key'] = $sourceApps[$item['application_source_id']] ?? null;
                    unset($item['application_source_id']);
                }
                return $item;
            }, $items);
        }
        $this->warnings[] = 'Additive merge only: missing records are created; existing records are never overwritten or deleted.';
        $this->warnings[] = 'Owners, assignees, workspace settings, Arena configuration, conversations, and monitoring history are not imported.';
        $this->warnings[] = 'Imported applications, databases, and infrastructure start as Unknown. Run checks to obtain live health; backup posture starts as Not configured.';
        if (array_diff(array_keys($source), ['format_version', 'platform', 'workspace', 'exported_at', ...array_keys(self::SECTIONS)])) {
            $this->warnings[] = 'Additional snapshot sections were ignored. Only the six supported inventory/project sections can be imported.';
        }
        $plan = $this->plan($payload, [
            'workspace' => is_string($source['workspace'] ?? null) ? mb_substr($source['workspace'], 0, 120) : 'Unknown workspace',
            'exported_at' => is_string($source['exported_at'] ?? null) ? mb_substr($source['exported_at'], 0, 40) : null,
            'filename' => mb_substr(basename(str_replace('\\', '/', $filename)), 0, 180),
            'bytes' => strlen($json),
        ], $this->warnings);
        if ($this->errors) { $plan['can_apply'] = false; }
        return $plan;
    }

    public function apply(array $original): array
    {
        if (($original['payload']['format_version'] ?? null) !== self::VERSION || !($original['can_apply'] ?? false) || ($original['errors'] ?? [])) {
            throw new RuntimeException('This review is not approved for import. Validate a snapshot and resolve its errors first.');
        }
        $this->errors = [];
        $this->errorCount = 0;
        try {
            $lock = Cache::lock('nai:workspace-import', 300);
            return $lock->block(5, function () use ($original) {
                $fresh = $this->plan($original['payload'], $original['meta'], $original['warnings'] ?? []);
                if ($this->hash($fresh) !== $this->hash($original)) {
                    throw new RuntimeException('The workspace changed after this preview. Upload the snapshot again to review the current change plan.');
                }
                return DB::transaction(function () use ($fresh) {
                    $maps = [];
                    $counts = array_fill_keys(array_keys(self::SECTIONS), 0);
                    $createdIds = array_fill_keys(array_keys(self::SECTIONS), []);
                    foreach (['applications', 'repositories', 'databases', 'infrastructure', 'projects'] as $type) {
                        $model = self::MODELS[$type];
                        foreach ($fresh['payload']['sections'][$type] as $item) {
                            if ($item['action'] === 'existing') { $maps[$type][$item['key']] = $item['target_id']; continue; }
                            if ($item['action'] !== 'new') throw new RuntimeException('The change plan contains an unresolved conflict.');
                            $data = $item['data'];
                            if (array_key_exists('application_key', $item)) {
                                if ($item['application_key'] !== null && !isset($maps['applications'][$item['application_key']])) throw new RuntimeException('An application link could not be resolved.');
                                $data['application_id'] = $item['application_key'] ? $maps['applications'][$item['application_key']] : null;
                            }
                            $record = $model::create($data);
                            $maps[$type][$item['key']] = $record->id;
                            $counts[$type]++;
                            $createdIds[$type][] = $record->id;
                        }
                    }
                    foreach ($fresh['payload']['sections']['projects'] as $project) {
                        $projectId = $maps['projects'][$project['key']];
                        foreach ($project['tasks'] as $task) {
                            if ($task['action'] === 'existing') continue;
                            if ($task['action'] !== 'new') throw new RuntimeException('The change plan contains an unresolved task conflict.');
                            $record = Task::create($task['data'] + ['project_id' => $projectId]);
                            $counts['tasks']++;
                            $createdIds['tasks'][] = $record->id;
                        }
                    }
                    Audit::log('workspace.imported', 'Imported '.array_sum($counts).' missing workspace records after validation and review.', null, [
                        'counts' => $counts, 'created_ids' => $createdIds,
                        'snapshot_hash' => $this->hash($fresh), 'source_workspace' => $fresh['meta']['workspace'],
                    ]);
                    return $counts;
                });
            });
        } catch (\Illuminate\Contracts\Cache\LockTimeoutException) {
            throw new RuntimeException('Another workspace import is running. Try again shortly.');
        }
    }

    public function hash(array $plan): string
    {
        return hash('sha256', json_encode($plan, JSON_THROW_ON_ERROR | JSON_INVALID_UTF8_SUBSTITUTE));
    }

    private function plan(array $payload, array $meta, array $warnings = []): array
    {
        $existing = $this->existing();
        $sections = $decisions = [];
        $totals = ['new' => 0, 'existing' => 0, 'conflicts' => 0];
        foreach (array_keys(self::SECTIONS) as $type) {
            $sections[$type] = ['label' => self::SECTIONS[$type], 'new' => 0, 'existing' => 0, 'conflicts' => 0, 'rows' => []];
            $decisions[$type] = [];
            $items = $type === 'tasks' ? [] : ($payload['sections'][$type] ?? []);
            if ($type === 'tasks') {
                foreach ($payload['sections']['projects'] ?? [] as $project) {
                    foreach ($project['tasks'] as $task) $items[] = $task + ['parent' => $project['data']['key']];
                }
            }
            foreach ($items as $item) {
                $identity = $type === 'tasks' ? $item['parent']."\0".$item['key'] : $item['key'];
                $matches = $existing[$type][$identity] ?? [];
                $data = $item['data'];
                if (array_key_exists('application_key', $item)) $data['application_key'] = $item['application_key'];
                $action = count($matches) === 0 ? 'new' : 'existing';
                $reason = count($matches) === 0 ? 'Missing from this workspace' : 'Already present with matching metadata';
                if (count($matches) > 1) {
                    $action = 'conflict';
                    $reason = 'Multiple existing records share this identity. Resolve the duplicates before importing.';
                } elseif (count($matches) === 1 && $this->canonical($this->portable($type, $matches[0]['data'])) !== $this->canonical($this->portable($type, $data))) {
                    $action = 'conflict';
                    $reason = 'An existing record has different metadata. NAi will not overwrite it.';
                }
                $bucket = $action === 'conflict' ? 'conflicts' : $action;
                $sections[$type][$bucket]++;
                $totals[$bucket]++;
                $sections[$type]['rows'][] = [
                    'name' => $data['name'] ?? $data['title'], 'identity' => $identity, 'identity_label' => str_replace("\0", ' / ', $identity),
                    'action' => $action, 'reason' => $reason,
                    'detail' => $item['parent'] ?? ($data['environment'] ?? $data['status'] ?? ''),
                ];
                $decisions[$type][] = ['action' => $action, 'target_id' => $action === 'existing' ? $matches[0]['id'] : null, 'data' => $data];
            }
        }
        // Decisions are server-generated and attached to normalized records, never accepted from the browser.
        foreach (['applications', 'repositories', 'databases', 'infrastructure', 'projects'] as $type) {
            foreach ($payload['sections'][$type] ?? [] as $i => $record) {
                $payload['sections'][$type][$i] = array_replace($record, $decisions[$type][$i]);
            }
        }
        $taskIndex = 0;
        foreach ($payload['sections']['projects'] ?? [] as $p => $project) {
            foreach ($project['tasks'] as $t => $task) {
                $payload['sections']['projects'][$p]['tasks'][$t] = array_replace($task, $decisions['tasks'][$taskIndex++]);
            }
        }
        return [
            'meta' => $meta, 'payload' => $payload, 'sections' => $sections, 'totals' => $totals,
            'errors' => $this->errors, 'error_count' => $this->errorCount, 'warnings' => $warnings,
            'can_apply' => $this->errorCount === 0 && $totals['conflicts'] === 0 && $totals['new'] > 0,
        ];
    }

    private function existing(): array
    {
        $index = array_fill_keys(array_keys(self::SECTIONS), []);
        $appKeys = [];
        foreach (Application::cursor() as $row) {
            $appKeys[$row->id] = $row->slug;
            $data = $this->normalizeStored('applications', $row);
            $index['applications'][$row->slug][] = ['id' => $row->id, 'data' => $data];
        }
        foreach (['repositories', 'databases', 'infrastructure'] as $type) {
            foreach (self::MODELS[$type]::cursor() as $row) {
                $data = $this->normalizeStored($type, $row);
                $data['application_key'] = $row->application_id ? ($appKeys[$row->application_id] ?? null) : null;
                $index[$type][$this->key($type, $data)][] = ['id' => $row->id, 'data' => $data];
            }
        }
        $projectKeys = [];
        foreach (Project::cursor() as $row) {
            $projectKeys[$row->id] = $row->key;
            $index['projects'][$row->key][] = ['id' => $row->id, 'data' => $this->normalizeStored('projects', $row)];
        }
        foreach (Task::cursor() as $row) {
            $data = $this->normalizeStored('tasks', $row);
            $key = ($projectKeys[$row->project_id] ?? '')."\0".$this->key('tasks', $data);
            $index['tasks'][$key][] = ['id' => $row->id, 'data' => $data];
        }
        return $index;
    }

    private function normalizeStored(string $type, $row): array
    {
        $fields = array_keys($this->rules($type));
        $data = [];
        foreach ($fields as $field) $data[$field] = $row->$field;
        if ($type === 'applications') { $data['slug'] = $row->slug; $data['tech_stack'] = $row->tech_stack ?? []; }
        if ($type === 'repositories') { $data['default_branch'] = $row->default_branch ?? 'main'; $data['url'] = $this->normalizeUrl($row->url); }
        if ($type === 'databases') $data['size_mb'] = $row->size_mb === null ? null : round((float) $row->size_mb, 2);
        foreach (['start_date', 'due_date', 'ssl_expires_at'] as $field) if (array_key_exists($field, $data)) $data[$field] = $row->$field ? $row->$field->format('Y-m-d') : null;
        return $data;
    }

    private function normalize(string $type, array $row, string $label): ?array
    {
        $rules = $this->rules($type);
        $input = array_intersect_key($row, $rules);
        if ($type === 'applications') $input['slug'] = $row['slug'] ?? null;
        if (isset($input['tech_stack']) && is_array($input['tech_stack'])) {
            foreach ($input['tech_stack'] as $tech) if (!is_string($tech) || mb_strlen($tech) > 100) { $this->error($label, 'Technology stack must contain strings of at most 100 characters.'); return null; }
            if (mb_strlen(implode(', ', $input['tech_stack'])) > 500) { $this->error($label, 'Technology stack is too long.'); return null; }
        }
        $v = Validator::make($input, $rules + ($type === 'applications' ? ['slug' => 'required|string|max:180|regex:/^[a-z0-9]+(?:-[a-z0-9]+)*$/'] : []));
        if ($v->fails()) { $this->error($label, 'Invalid fields: '.implode(', ', array_keys($v->errors()->messages())).'.'); return null; }
        $data = [];
        foreach (array_keys($rules) as $field) $data[$field] = $input[$field] ?? null;
        if ($type === 'applications') { $data['slug'] = $input['slug']; $data['tech_stack'] = array_values($data['tech_stack'] ?? []); $data += ['status' => 'unknown', 'health_score' => null, 'last_checked_at' => null]; }
        if (in_array($type, ['applications', 'repositories'], true) && $data['url'] !== null) {
            $parts = parse_url($data['url']);
            if (!is_array($parts) || !in_array(strtolower($parts['scheme'] ?? ''), ['http', 'https'], true) || isset($parts['user']) || isset($parts['pass'])) {
                $this->error($label, 'URLs must use HTTP or HTTPS and must not contain embedded credentials.'); return null;
            }
        }
        if ($type === 'repositories') { $data['url'] = $this->normalizeUrl($data['url']); $data['default_branch'] = $data['default_branch'] ?? 'main'; $data['last_synced_at'] = null; }
        if ($type === 'databases') { $data['port'] = $data['port'] === null ? null : (int) $data['port']; $data['size_mb'] = $data['size_mb'] === null ? null : round((float) $data['size_mb'], 2); $data += ['status' => 'unknown', 'backup_status' => 'not_configured', 'last_backup_at' => null]; }
        if ($type === 'infrastructure') $data['status'] = 'unknown';
        foreach (['start_date', 'due_date', 'ssl_expires_at'] as $field) {
            if (!array_key_exists($field, $data)) continue;
            if ($data[$field] !== null) {
                try {
                    $rawDate = $data[$field];
                    $data[$field] = CarbonImmutable::parse($rawDate)->format('Y-m-d');
                    if (!preg_match('/^\d{4}-\d{2}-\d{2}/', $rawDate) || substr($rawDate, 0, 10) !== $data[$field]) throw new \InvalidArgumentException();
                } catch (\Throwable) { $this->error($label, 'Invalid calendar date in '.$field.'. Use YYYY-MM-DD.'); return null; }
            }
        }
        if ($type === 'projects' && $data['start_date'] && $data['due_date'] && $data['due_date'] < $data['start_date']) { $this->error($label, 'Project due date must not precede its start date.'); return null; }
        return $data;
    }

    private function rules(string $type): array
    {
        return match ($type) {
            'applications' => ['name' => 'required|string|max:160', 'description' => 'nullable|string|max:2000', 'url' => 'nullable|url|max:500', 'environment' => 'required|in:production,staging,development', 'tech_stack' => 'nullable|array|max:50', 'notes' => 'nullable|string|max:5000'],
            'repositories' => ['name' => 'required|string|max:160', 'description' => 'nullable|string|max:2000', 'url' => 'required|url|max:500', 'provider' => 'required|in:github,gitlab,bitbucket,azure_devops,self_hosted', 'default_branch' => 'nullable|string|max:120', 'language' => 'nullable|string|max:80', 'visibility' => 'required|in:private,internal,public', 'status' => 'required|in:active,syncing,archived'],
            'databases' => ['name' => 'required|string|max:160', 'engine' => 'required|in:mysql,mariadb,postgresql,sqlserver,mongodb,redis', 'version' => 'nullable|string|max:40', 'host' => 'required|string|max:255', 'port' => 'nullable|integer|min:1|max:65535', 'environment' => 'required|in:production,staging,development', 'size_mb' => 'nullable|numeric|min:0|max:9999999999.99', 'notes' => 'nullable|string|max:5000'],
            'infrastructure' => ['name' => 'required|string|max:160', 'type' => 'required|in:server,hosting,load_balancer,storage,network,cdn', 'provider' => 'required|string|max:120', 'region' => 'nullable|string|max:120', 'hostname' => 'nullable|string|max:255', 'ip_address' => 'nullable|ip', 'environment' => 'required|in:production,staging,development', 'specs' => 'nullable|string|max:500', 'ssl_expires_at' => 'nullable|date', 'notes' => 'nullable|string|max:5000'],
            'projects' => ['name' => 'required|string|max:160', 'key' => 'required|string|max:20|regex:/^[A-Z0-9-]+$/', 'description' => 'nullable|string|max:3000', 'status' => 'required|in:planning,active,on_hold,completed', 'priority' => 'required|in:low,medium,high,urgent', 'start_date' => 'nullable|date', 'due_date' => 'nullable|date'],
            'tasks' => ['title' => 'required|string|max:200', 'description' => 'nullable|string|max:2000', 'status' => 'required|in:backlog,in_progress,review,done', 'priority' => 'required|in:low,medium,high,urgent', 'due_date' => 'nullable|date'],
        };
    }

    private function key(string $type, array $data): string
    {
        return match ($type) {
            'applications' => $data['slug'],
            'repositories' => $this->normalizeUrl($data['url']),
            'databases', 'infrastructure' => mb_strtolower($data['name'])."\0".$data['environment'],
            'projects' => $data['key'],
            'tasks' => mb_strtolower(trim($data['title']))."\0".hash('sha256', trim($data['description'] ?? '')),
        };
    }

    private function normalizeUrl(string $url): string
    {
        $parts = parse_url(trim($url));
        if (!$parts || empty($parts['host'])) return trim($url);
        $path = rtrim($parts['path'] ?? '', '/');
        return strtolower($parts['scheme'] ?? 'https').'://'.strtolower($parts['host']).(isset($parts['port']) ? ':'.$parts['port'] : '').$path.(isset($parts['query']) ? '?'.$parts['query'] : '').(isset($parts['fragment']) ? '#'.$parts['fragment'] : '');
    }

    /** Operational health, sync timestamps, owners, and provenance are not restore metadata. */
    private function portable(string $type, array $data): array
    {
        $fields = array_keys($this->rules($type));
        if ($type === 'applications') $fields[] = 'slug';
        if (in_array($type, ['repositories', 'databases', 'infrastructure'], true)) $fields[] = 'application_key';
        return array_intersect_key($data, array_flip($fields));
    }

    private function canonical(array $data): string
    {
        ksort($data);
        return json_encode($data, JSON_THROW_ON_ERROR | JSON_INVALID_UTF8_SUBSTITUTE);
    }

    private function error(string $field, string $message): void
    {
        $this->errorCount++;
        if (count($this->errors) < 100) $this->errors[] = ['field' => $field, 'message' => $message];
    }

    private function emptyPlan(string $filename): array
    {
        return ['meta' => ['workspace' => 'Unreadable snapshot', 'exported_at' => null, 'filename' => mb_substr(basename(preg_replace('/[\x00-\x1F]/', '', $filename)), 0, 180), 'bytes' => 0],
            'payload' => ['format_version' => self::VERSION, 'sections' => array_fill_keys(['applications', 'repositories', 'databases', 'infrastructure', 'projects'], [])],
            'sections' => [], 'totals' => ['new' => 0, 'existing' => 0, 'conflicts' => 0],
            'errors' => $this->errors, 'error_count' => $this->errorCount, 'warnings' => [], 'can_apply' => false];
    }
}
