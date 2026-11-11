<?php
namespace Tests\Feature;

use App\Jobs\PlanWorkforceAssignment;
use App\Models\User;
use App\Models\WorkforceAssignment;
use App\Models\WorkforceCompany;
use App\Models\WorkforceMembership;
use App\Services\Workforce\Access;
use App\Services\Workforce\Events;
use App\Services\Workforce\Planner;
use App\Services\Workforce\ToolRegistry;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class WorkforceTest extends TestCase
{
    use RefreshDatabase;

    private function person(string $email, string $role = 'operator'): User
    {
        return User::create(['name' => $email, 'email' => $email, 'password' => 'password-123', 'role' => $role]);
    }

    private function company(User $user, string $role = 'manager'): WorkforceCompany
    {
        $company = WorkforceCompany::create(['name' => 'Test company']);
        WorkforceMembership::create(['company_id' => $company->id, 'user_id' => $user->id, 'role' => $role]);
        return $company;
    }

    private function assignment(WorkforceCompany $company, User $user, string $status = 'queued'): WorkforceAssignment
    {
        return WorkforceAssignment::create(['company_id' => $company->id, 'requested_by' => $user->id, 'instruction' => 'Please create a work item for a board meeting', 'status' => $status]);
    }

    private function plan(WorkforceAssignment $assignment, array $plan): void
    {
        $planner = new class($plan) implements Planner {
            public function __construct(private array $plan) {}
            public function plan(string $instruction): array { return ['plan' => $this->plan, 'input_tokens' => 10, 'output_tokens' => 20]; }
        };
        (new PlanWorkforceAssignment($assignment->id))->handle($planner, app(Access::class), app(Events::class), app(ToolRegistry::class));
    }

    private function validPlan(): array
    {
        return ['decision' => 'propose', 'agent' => 'operations', 'tool' => 'create_work_item', 'title' => 'Prepare board meeting', 'description' => 'Prepare agenda'];
    }

    public function test_company_creation_requires_admin_and_grants_only_creator_membership(): void
    {
        $admin = $this->person('admin@test.local', 'admin');
        $this->actingAs($admin)->post('/workforce/companies', ['name' => 'Subsidiary'])->assertRedirect();
        $company = WorkforceCompany::firstOrFail();
        $this->assertDatabaseHas('workforce_memberships', ['company_id' => $company->id, 'user_id' => $admin->id, 'role' => 'manager']);
        $this->assertDatabaseHas('workforce_events', ['company_id' => $company->id, 'event' => 'company_created', 'actor_id' => $admin->id]);
        $operator = $this->person('op@test.local');
        $this->actingAs($operator)->post('/workforce/companies', ['name' => 'No'])->assertForbidden();
        $this->get('/workforce/companies/'.$company->id)->assertForbidden();
    }

    public function test_request_is_queued_without_placing_secret_or_prompt_in_job_payload(): void
    {
        Queue::fake(); config(['workforce.openai_key' => 'not-a-real-key']);
        $user = $this->person('operator@test.local'); $company = $this->company($user, 'operator');
        $this->actingAs($user)->post('/workforce/companies/'.$company->id.'/assignments', ['instruction' => 'Private work item'])->assertRedirect();
        $a = WorkforceAssignment::firstOrFail();
        $this->assertSame('queued', $a->status);
        $this->assertStringNotContainsString('Private work item', (string) \DB::table('workforce_assignments')->value('instruction'));
        Queue::assertPushed(PlanWorkforceAssignment::class, fn ($j) => $j->assignmentId === $a->id && ! str_contains(serialize($j), 'not-a-real-key') && ! str_contains(serialize($j), 'Private work item'));
        $this->assertDatabaseHas('workforce_events', ['assignment_id' => $a->id, 'event' => 'assignment_submitted']);
    }

    public function test_planning_requires_manager_approval_and_executes_once_with_traceable_result(): void
    {
        $user = $this->person('manager@test.local'); $company = $this->company($user);
        $a = $this->assignment($company, $user); $this->plan($a, $this->validPlan());
        $this->assertSame('awaiting_approval', $a->fresh()->status);
        $this->assertDatabaseCount('workforce_work_items', 0);
        $this->actingAs($user)->post('/workforce/companies/'.$company->id.'/assignments/'.$a->id.'/decision', ['decision' => 'approve'])->assertRedirect();
        $this->assertSame('completed', $a->fresh()->status);
        $this->assertDatabaseCount('workforce_work_items', 1);
        $this->assertSame('Prepare board meeting', \App\Models\WorkforceWorkItem::firstOrFail()->title);
        $this->assertDatabaseHas('workforce_events', ['assignment_id' => $a->id, 'event' => 'approved', 'actor_id' => $user->id]);
        $this->assertDatabaseHas('workforce_events', ['assignment_id' => $a->id, 'event' => 'tool_completed']);
        $this->post('/workforce/companies/'.$company->id.'/assignments/'.$a->id.'/decision', ['decision' => 'approve'])->assertSessionHasErrors();
        $this->assertDatabaseCount('workforce_work_items', 1);
        $this->get('/workforce/companies/'.$company->id)->assertOk()->assertSee('Prepare board meeting')->assertSee('Created work item');
    }

    public function test_company_isolation_and_role_checks_cover_read_submit_and_approval(): void
    {
        config(['workforce.openai_key' => 'test']);
        $manager = $this->person('manager@test.local'); $company = $this->company($manager);
        $a = $this->assignment($company, $manager); $this->plan($a, $this->validPlan());
        $outsider = $this->person('outsider@test.local', 'admin'); $other = $this->company($outsider);
        $this->actingAs($outsider)->get('/workforce/companies/'.$company->id)->assertForbidden();
        $this->post('/workforce/companies/'.$company->id.'/assignments', ['instruction' => 'no'])->assertForbidden();
        $this->post('/workforce/companies/'.$other->id.'/assignments/'.$a->id.'/decision', ['decision' => 'approve'])->assertNotFound();
        $operator = $this->person('operator@test.local');
        WorkforceMembership::create(['company_id' => $company->id, 'user_id' => $operator->id, 'role' => 'operator']);
        $this->actingAs($operator)->post('/workforce/companies/'.$company->id.'/assignments/'.$a->id.'/decision', ['decision' => 'approve'])->assertForbidden();
        $viewer = $this->person('viewer@test.local', 'viewer');
        WorkforceMembership::create(['company_id' => $company->id, 'user_id' => $viewer->id, 'role' => 'manager']);
        $this->actingAs($viewer)->get('/workforce/companies/'.$company->id)->assertOk();
        $this->post('/workforce/companies/'.$company->id.'/assignments/'.$a->id.'/decision', ['decision' => 'approve'])->assertForbidden();
        $this->post('/workforce/companies/'.$company->id.'/assignments', ['instruction' => 'no'])->assertForbidden();
        $this->assertDatabaseCount('workforce_work_items', 0);
    }

    public function test_unsupported_or_unrecognised_tools_never_execute(): void
    {
        $user = $this->person('manager@test.local'); $company = $this->company($user);
        $a = $this->assignment($company, $user); $this->plan($a, ['decision' => 'unsupported']);
        $this->assertSame('unsupported', $a->fresh()->status);
        $b = $this->assignment($company, $user); $this->plan($b, ['decision' => 'propose', 'agent' => 'finance', 'tool' => 'send_payment', 'title' => 'Pay']);
        $this->assertSame('failed', $b->fresh()->status);
        $this->assertDatabaseCount('workforce_work_items', 0);
    }

    public function test_revoked_requester_cannot_execute_after_planning(): void
    {
        $manager = $this->person('manager@test.local'); $company = $this->company($manager);
        $op = $this->person('op@test.local'); WorkforceMembership::create(['company_id' => $company->id, 'user_id' => $op->id, 'role' => 'operator']);
        $a = $this->assignment($company, $op); $this->plan($a, $this->validPlan());
        WorkforceMembership::where('company_id', $company->id)->where('user_id', $op->id)->delete();
        $this->actingAs($manager)->post('/workforce/companies/'.$company->id.'/assignments/'.$a->id.'/decision', ['decision' => 'approve'])->assertRedirect();
        $this->assertSame('failed', $a->fresh()->status);
        $this->assertDatabaseCount('workforce_work_items', 0);
        $this->assertDatabaseHas('workforce_events', ['assignment_id' => $a->id, 'event' => 'permission_revoked']);
    }

    public function test_rejection_is_audited_without_creating_a_work_item(): void
    {
        $user = $this->person('manager@test.local'); $company = $this->company($user);
        $a = $this->assignment($company, $user); $this->plan($a, $this->validPlan());
        $this->actingAs($user)->post('/workforce/companies/'.$company->id.'/assignments/'.$a->id.'/decision', ['decision' => 'reject'])->assertRedirect();
        $this->assertSame('rejected', $a->fresh()->status);
        $this->assertDatabaseHas('workforce_events', ['assignment_id' => $a->id, 'event' => 'rejected', 'actor_id' => $user->id]);
        $this->assertDatabaseCount('workforce_work_items', 0);
    }

    public function test_actual_openai_planner_request_is_scoped_and_errors_do_not_become_successes(): void
    {
        config(['workforce.openai_key' => 'test-secret', 'workforce.model' => 'gpt-5.4']);
        $user = $this->person('manager@test.local'); $company = $this->company($user);
        Http::fake(['api.openai.com/*' => Http::response(['error' => ['message' => 'test-secret']], 401)]);
        $a = $this->assignment($company, $user);
        (new PlanWorkforceAssignment($a->id))->handle(app(Planner::class), app(Access::class), app(Events::class), app(ToolRegistry::class));
        $this->assertSame('failed', $a->fresh()->status);
        $this->assertStringNotContainsString('test-secret', $a->fresh()->error);
        Http::assertSent(fn ($r) => $r->url() === 'https://api.openai.com/v1/chat/completions' && $r->hasHeader('Authorization', 'Bearer test-secret') && ! str_contains(json_encode($r->data()), 'test-secret'));
        $this->assertDatabaseCount('workforce_work_items', 0);
    }
    public function test_openai_success_is_validated_and_stored_before_approval(): void
    {
        config(['workforce.openai_key' => 'test-key']);
        $user = $this->person('manager@test.local'); $company = $this->company($user);
        $a = $this->assignment($company, $user);
        Http::fake(['api.openai.com/*' => Http::response(['choices' => [['message' => ['content' => json_encode($this->validPlan())]]], 'usage' => ['prompt_tokens' => 11, 'completion_tokens' => 12]])]);
        (new PlanWorkforceAssignment($a->id))->handle(app(Planner::class), app(Access::class), app(Events::class), app(ToolRegistry::class));
        $this->assertSame('awaiting_approval', $a->fresh()->status);
        $this->assertSame(11, $a->fresh()->input_tokens);
        $this->assertSame(12, $a->fresh()->output_tokens);
        $this->assertStringNotContainsString('Prepare board meeting', (string) \DB::table('workforce_assignments')->value('proposal'));
        $this->assertDatabaseCount('workforce_work_items', 0);
    }

    public function test_work_item_completion_is_company_scoped_and_audited(): void
    {
        $user = $this->person('manager@test.local'); $company = $this->company($user);
        $a = $this->assignment($company, $user); $this->plan($a, $this->validPlan());
        $this->actingAs($user)->post('/workforce/companies/'.$company->id.'/assignments/'.$a->id.'/decision', ['decision' => 'approve']);
        $item = \App\Models\WorkforceWorkItem::firstOrFail();
        $other = $this->person('other@test.local'); $otherCompany = $this->company($other);
        $this->actingAs($other)->post('/workforce/companies/'.$otherCompany->id.'/items/'.$item->id.'/complete')->assertNotFound();
        $this->actingAs($user)->post('/workforce/companies/'.$company->id.'/items/'.$item->id.'/complete')->assertRedirect();
        $this->assertSame('done', $item->fresh()->status);
        $this->assertDatabaseHas('workforce_events', ['company_id' => $company->id, 'assignment_id' => $a->id, 'event' => 'work_item_completed', 'actor_id' => $user->id]);
    }

    public function test_member_removal_is_scoped_and_last_manager_is_protected(): void
    {
        $admin = $this->person('admin@test.local', 'admin'); $company = $this->company($admin);
        $manager = WorkforceMembership::where('company_id', $company->id)->firstOrFail();
        $this->actingAs($admin)->delete('/workforce/companies/'.$company->id.'/members/'.$manager->id)->assertSessionHasErrors('member');
        $op = $this->person('op@test.local');
        $this->post('/workforce/companies/'.$company->id.'/members', ['user_id' => $op->id, 'role' => 'operator'])->assertRedirect();
        $member = WorkforceMembership::where('user_id', $op->id)->firstOrFail();
        $this->delete('/workforce/companies/'.$company->id.'/members/'.$member->id)->assertRedirect();
        $this->assertDatabaseMissing('workforce_memberships', ['id' => $member->id]);
        $this->assertDatabaseHas('workforce_events', ['company_id' => $company->id, 'event' => 'member_removed', 'actor_id' => $admin->id]);
        $this->actingAs($op)->get('/workforce/companies/'.$company->id)->assertForbidden();
    }

    public function test_retry_creates_a_new_queued_assignment_without_mutating_old_record(): void
    {
        Queue::fake(); config(['workforce.openai_key' => 'test']);
        $user = $this->person('manager@test.local'); $company = $this->company($user);
        $old = $this->assignment($company, $user, 'failed');
        $this->actingAs($user)->post('/workforce/companies/'.$company->id.'/assignments/'.$old->id.'/retry')->assertRedirect();
        $this->assertSame('failed', $old->fresh()->status);
        $this->assertDatabaseCount('workforce_assignments', 2);
        $new = WorkforceAssignment::latest('id')->firstOrFail();
        $this->assertSame('queued', $new->status);
        $this->assertSame($old->instruction, $new->instruction);
        Queue::assertPushed(PlanWorkforceAssignment::class, fn ($j) => $j->assignmentId === $new->id);
    }
    public function test_real_database_queue_worker_plans_and_persists_a_reviewable_assignment(): void
    {
        $user = $this->person('worker@test.local'); $company = $this->company($user);
        $a = $this->assignment($company, $user);
        $plan = $this->validPlan();
        $this->app->instance(Planner::class, new class($plan) implements Planner {
            public function __construct(private array $plan) {}
            public function plan(string $instruction): array { return ['plan' => $this->plan, 'input_tokens' => 1, 'output_tokens' => 2]; }
        });
        PlanWorkforceAssignment::dispatch($a->id);
        $this->assertDatabaseCount('jobs', 1);
        $this->artisan('queue:work', ['connection' => 'database', '--queue' => 'workforce', '--once' => true, '--tries' => 1])->assertExitCode(0);
        $this->assertDatabaseCount('jobs', 0);
        $this->assertSame('awaiting_approval', $a->fresh()->status);
        $this->assertDatabaseCount('workforce_work_items', 0);
    }
}
