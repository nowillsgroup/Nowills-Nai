<?php
namespace App\Http\Controllers;

use App\Models\User;
use App\Models\WorkforceAssignment;
use App\Models\WorkforceCompany;
use App\Models\WorkforceEvent;
use App\Models\WorkforceMembership;
use App\Models\WorkforceWorkItem;
use App\Services\Workforce\Access;
use App\Services\Workforce\AssignmentService;
use App\Services\Workforce\Events;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class WorkforceController extends Controller
{
    public function index(Request $request)
    {
        $ids = WorkforceMembership::where('user_id', $request->user()->id)->pluck('company_id');
        $companies = WorkforceCompany::whereIn('id', $ids)->orderBy('name')->get();
        return view('workforce.index', compact('companies'));
    }

    public function createCompany(Request $request, Events $events)
    {
        abort_unless($request->user()->isAdmin(), 403);
        $data = $request->validate(['name' => 'required|string|max:160']);
        $company = DB::transaction(function () use ($data, $request, $events) {
            $company = WorkforceCompany::create($data);
            WorkforceMembership::create(['company_id' => $company->id, 'user_id' => $request->user()->id, 'role' => 'manager']);
            $events->record($company->id, null, $request->user()->id, 'company_created');
            return $company;
        });
        return redirect()->route('workforce.company', $company)->with('success', 'Company created. Only explicitly added members can access its workforce records.');
    }

    public function show(Request $request, WorkforceCompany $company, Access $access)
    {
        abort_unless($access->allows($request->user(), $company->id), 403);
        $assignments = WorkforceAssignment::where('company_id', $company->id)->latest('id')->limit(50)->get();
        $items = WorkforceWorkItem::where('company_id', $company->id)->latest('id')->limit(30)->get();
        $events = WorkforceEvent::where('company_id', $company->id)->latest('id')->limit(30)->get();
        $counts = WorkforceAssignment::where('company_id', $company->id)->selectRaw('status, count(*) as total')->groupBy('status')->pluck('total', 'status');
        $tokenUsage = WorkforceAssignment::where('company_id', $company->id)->selectRaw('sum(input_tokens) as input_total, sum(output_tokens) as output_total')->first();
        $canOperate = $access->allows($request->user(), $company->id, 'operate');
        $canManage = $access->allows($request->user(), $company->id, 'manage');
        $users = $request->user()->isAdmin() && $canManage ? User::orderBy('name')->get(['id', 'name', 'email']) : collect();
        $members = $request->user()->isAdmin() && $canManage ? WorkforceMembership::where('company_id', $company->id)->get() : collect();
        return view('workforce.company', compact('company', 'assignments', 'items', 'events', 'counts', 'tokenUsage', 'canOperate', 'canManage', 'users', 'members'));
    }

    public function addMember(Request $request, WorkforceCompany $company, Access $access, Events $events)
    {
        // Explicit global administration AND company management are required.
        abort_unless($request->user()->isAdmin() && $access->allows($request->user(), $company->id, 'manage'), 403);
        $data = $request->validate(['user_id' => 'required|integer|exists:users,id', 'role' => 'required|in:manager,operator,viewer']);
        DB::transaction(function () use ($data, $company, $request, $events) {
            $created = WorkforceMembership::firstOrCreate(['company_id' => $company->id, 'user_id' => $data['user_id']], ['role' => $data['role']]);
            if (! $created->wasRecentlyCreated) throw \Illuminate\Validation\ValidationException::withMessages(['user_id' => 'This user is already a member.']);
            $events->record($company->id, null, $request->user()->id, 'member_added');
        });
        return back()->with('success', 'Member added.');
    }

    public function removeMember(Request $request, WorkforceCompany $company, int $member, Access $access, Events $events)
    {
        DB::transaction(function () use ($request, $company, $member, $access, $events) {
            $memberships = WorkforceMembership::where('company_id', $company->id)->lockForUpdate()->get();
            abort_unless($request->user()->isAdmin() && $access->allows($request->user(), $company->id, 'manage'), 403);
            $target = $memberships->firstWhere('id', $member);
            abort_unless($target, 404);
            if ($target->role === 'manager' && $memberships->where('role', 'manager')->count() <= 1) {
                throw \Illuminate\Validation\ValidationException::withMessages(['member' => 'A company must retain at least one manager.']);
            }
            $target->delete();
            $events->record($company->id, null, $request->user()->id, 'member_removed');
        });
        return redirect()->route('workforce.index')->with('success', 'Company membership removed.');
    }

    public function completeItem(Request $request, WorkforceCompany $company, int $item, Access $access, Events $events)
    {
        DB::transaction(function () use ($request, $company, $item, $access, $events) {
            abort_unless($access->allows($request->user(), $company->id, 'operate'), 403);
            $workItem = WorkforceWorkItem::where('company_id', $company->id)->whereKey($item)->lockForUpdate()->firstOrFail();
            if ($workItem->status !== 'open') throw \Illuminate\Validation\ValidationException::withMessages(['item' => 'Work item is already complete.']);
            $workItem->update(['status' => 'done']);
            $events->record($company->id, $workItem->assignment_id, $request->user()->id, 'work_item_completed');
        });
        return back()->with('success', 'Work item marked complete.');
    }

    public function submit(Request $request, WorkforceCompany $company, AssignmentService $service, Access $access)
    {
        abort_unless($access->allows($request->user(), $company->id, 'operate'), 403);
        $data = $request->validate(['instruction' => 'required|string|max:4000']);
        if (! filled(config('workforce.openai_key'))) return back()->with('error', 'Workforce AI is not configured. An administrator must set WORKFORCE_OPENAI_API_KEY on the server.');
        $service->submit($request->user(), $company->id, $data['instruction']);
        return back()->with('success', 'Assignment queued for planning. No action will be executed without a manager’s approval.');
    }

    public function retry(Request $request, WorkforceCompany $company, int $assignment, AssignmentService $service, Access $access)
    {
        abort_unless($access->allows($request->user(), $company->id, 'operate'), 403);
        $old = WorkforceAssignment::where('company_id', $company->id)->whereKey($assignment)->firstOrFail();
        if (! in_array($old->status, ['failed', 'unsupported'], true)) throw \Illuminate\Validation\ValidationException::withMessages(['assignment' => 'Only failed or unsupported assignments can be retried.']);
        if (! filled(config('workforce.openai_key'))) return back()->with('error', 'Workforce AI is not configured.');
        $service->submit($request->user(), $company->id, $old->instruction);
        return back()->with('success', 'A new planning attempt was queued. It may incur AI charges.');
    }

    public function decide(Request $request, WorkforceCompany $company, int $assignment, AssignmentService $service)
    {
        $data = $request->validate(['decision' => 'required|in:approve,reject']);
        $service->decide($request->user(), $company->id, $assignment, $data['decision'] === 'approve');
        return back()->with('success', $data['decision'] === 'approve' ? 'Decision processed; check the assignment result.' : 'Proposal rejected.');
    }
}
