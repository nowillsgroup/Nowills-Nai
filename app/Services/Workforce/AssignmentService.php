<?php
namespace App\Services\Workforce;

use App\Jobs\PlanWorkforceAssignment;
use App\Models\User;
use App\Models\WorkforceAssignment;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class AssignmentService
{
    public function __construct(private Access $access, private Events $events, private ToolRegistry $tools) {}

    public function submit(User $actor, int $companyId, string $instruction): WorkforceAssignment
    {
        abort_unless($this->access->allows($actor, $companyId, 'operate'), 403);
        return DB::transaction(function () use ($actor, $companyId, $instruction) {
            $a = WorkforceAssignment::create(['company_id' => $companyId, 'requested_by' => $actor->id, 'instruction' => $instruction, 'status' => 'queued']);
            $this->events->record($companyId, $a->id, $actor->id, 'assignment_submitted');
            PlanWorkforceAssignment::dispatch($a->id)->afterCommit();
            return $a;
        });
    }

    public function decide(User $actor, int $companyId, int $assignmentId, bool $approve): void
    {
        DB::transaction(function () use ($actor, $companyId, $assignmentId, $approve) {
            $a = WorkforceAssignment::where('company_id', $companyId)->whereKey($assignmentId)->lockForUpdate()->firstOrFail();
            abort_unless($this->access->allows($actor, $companyId, 'manage'), 403);
            if ($a->status !== 'awaiting_approval') throw ValidationException::withMessages(['assignment' => 'This assignment is no longer awaiting approval.']);
            if (! $approve) {
                $a->update(['status' => 'rejected', 'finished_at' => now()]);
                $this->events->record($companyId, $a->id, $actor->id, 'rejected');
                return;
            }
            // Permissions are checked again at execution time for BOTH users.
            $requester = User::find($a->requested_by);
            if (! $requester || ! $this->access->allows($requester, $companyId, 'operate')) {
                $a->update(['status' => 'failed', 'error' => 'The requester no longer has permission. No operation was performed.', 'finished_at' => now()]);
                $this->events->record($companyId, $a->id, $actor->id, 'permission_revoked');
                return;
            }
            // Internal DB-only operation and audit commit atomically with approval.
            // External actions will need a separate durable idempotency protocol.
            $this->events->record($companyId, $a->id, $actor->id, 'approved');
            $result = $this->tools->execute($a, $actor);
            $a->update(['status' => 'completed', 'result' => $result, 'finished_at' => now()]);
            $this->events->record($companyId, $a->id, $actor->id, 'tool_completed');
        });
    }
}
