<?php
namespace App\Services\Workforce;

use App\Models\User;
use App\Models\WorkforceAssignment;
use App\Models\WorkforceWorkItem;
use Illuminate\Support\Facades\Validator;
use RuntimeException;

/** The only executable tool is deliberately allowlisted and company-scoped. */
class ToolRegistry
{
    public function validatePlan(array $plan): array
    {
        if (($plan['decision'] ?? null) !== 'propose' || ($plan['agent'] ?? null) !== 'operations' || ($plan['tool'] ?? null) !== 'create_work_item') {
            throw new RuntimeException('This assignment is not supported by an available agent or tool. No operation was performed.');
        }
        $validated = Validator::make($plan, [
            'title' => 'required|string|max:200',
            'description' => 'nullable|string|max:2000',
        ])->validate();
        return ['agent' => 'operations', 'tool' => 'create_work_item', 'title' => trim($validated['title']), 'description' => trim($validated['description'] ?? '')];
    }

    /** Must be called inside the locked approval transaction. */
    public function execute(WorkforceAssignment $assignment, User $actor): array
    {
        $plan = $this->validatePlan(array_merge($assignment->proposal ?? [], ['decision' => 'propose']));
        $item = WorkforceWorkItem::create([
            'company_id' => $assignment->company_id,
            'assignment_id' => $assignment->id,
            'created_by' => $actor->id,
            'title' => $plan['title'],
            'description' => $plan['description'],
            'status' => 'open',
        ]);
        return ['tool' => 'create_work_item', 'work_item_id' => $item->id];
    }
}
