<?php
namespace App\Services\Workforce;

use App\Models\User;
use App\Models\WorkforceMembership;

class Access
{
    public function allows(User $user, int $companyId, string $level = 'view'): bool
    {
        $role = WorkforceMembership::where('company_id', $companyId)->where('user_id', $user->id)->value('role');
        return match ($level) {
            'view' => in_array($role, ['viewer', 'operator', 'manager'], true),
            'operate' => $user->canWrite() && in_array($role, ['operator', 'manager'], true),
            'manage' => $user->canWrite() && $role === 'manager',
            default => false,
        };
    }
}
