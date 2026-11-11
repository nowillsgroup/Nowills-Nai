<?php
namespace App\Jobs;

use App\Models\User;
use App\Models\WorkforceAssignment;
use App\Services\Workforce\Access;
use App\Services\Workforce\Events;
use App\Services\Workforce\Planner;
use App\Services\Workforce\ToolRegistry;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\DB;
use Throwable;

class PlanWorkforceAssignment implements ShouldQueue
{
    use Queueable;

    // No blind retry: a provider request can have a charge even when it times out.
    public int $tries = 1;
    public int $timeout = 75;

    public function __construct(public int $assignmentId)
    {
        $this->onConnection('database')->onQueue('workforce');
    }

    public function handle(Planner $planner, Access $access, Events $events, ToolRegistry $tools): void
    {
        $assignment = DB::transaction(function () use ($events) {
            $a = WorkforceAssignment::whereKey($this->assignmentId)->lockForUpdate()->first();
            if (! $a || $a->status !== 'queued') return null;
            $a->update(['status' => 'planning', 'started_at' => now(), 'error' => null]);
            $events->record($a->company_id, $a->id, $a->requested_by, 'planning_started');
            return $a;
        });
        if (! $assignment) return;

        try {
            $requester = User::find($assignment->requested_by);
            if (! $requester || ! $access->allows($requester, $assignment->company_id, 'operate')) throw new \RuntimeException('The requester no longer has permission. No operation was performed.');
            $answer = $planner->plan($assignment->instruction);
            if (($answer['plan']['decision'] ?? null) === 'unsupported') {
                $proposal = null;
                $status = 'unsupported';
            } else {
                $proposal = $tools->validatePlan($answer['plan']);
                $status = 'awaiting_approval';
            }
            DB::transaction(function () use ($assignment, $access, $events, $answer, $proposal, $status) {
                $a = WorkforceAssignment::whereKey($assignment->id)->lockForUpdate()->firstOrFail();
                if ($a->status !== 'planning') return;
                $requester = User::find($a->requested_by);
                if (! $requester || ! $access->allows($requester, $a->company_id, 'operate')) {
                    $a->update(['status' => 'failed', 'error' => 'The requester no longer has permission. No operation was performed.', 'finished_at' => now()]);
                    $events->record($a->company_id, $a->id, $a->requested_by, 'permission_revoked');
                    return;
                }
                $a->update(['status' => $status, 'agent' => $proposal['agent'] ?? null, 'proposal' => $proposal,
                    'input_tokens' => is_numeric($answer['input_tokens'] ?? null) ? max(0, (int) $answer['input_tokens']) : null,
                    'output_tokens' => is_numeric($answer['output_tokens'] ?? null) ? max(0, (int) $answer['output_tokens']) : null,
                    'finished_at' => $status === 'unsupported' ? now() : null]);
                $events->record($a->company_id, $a->id, $a->requested_by, $status);
            });
        } catch (Throwable $e) {
            $this->markFailed($events);
        }
    }

    public function failed(?Throwable $e): void
    {
        $this->markFailed(app(Events::class));
    }

    private function markFailed(Events $events): void
    {
        DB::transaction(function () use ($events) {
            $a = WorkforceAssignment::whereKey($this->assignmentId)->lockForUpdate()->first();
            if (! $a || ! in_array($a->status, ['queued', 'planning'], true)) return;
            // Never persist an exception from an external provider; it might contain secrets.
            $a->update(['status' => 'failed', 'error' => 'Planning failed or permissions changed. Check configuration and retry. No operation was performed.', 'finished_at' => now()]);
            $events->record($a->company_id, $a->id, $a->requested_by, 'planning_failed');
        });
    }
}
