<?php
namespace App\Services\Workforce;

use App\Models\WorkforceEvent;

class Events
{
    public function record(int $companyId, ?int $assignmentId, ?int $actorId, string $event): void
    {
        WorkforceEvent::create(['company_id' => $companyId, 'assignment_id' => $assignmentId, 'actor_id' => $actorId, 'event' => $event]);
    }
}
