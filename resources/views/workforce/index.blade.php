<x-layout title="AI Workforce" subtitle="Company-scoped assignments and approvals">
<div class="page-head"><div><h2>AI Workforce</h2><p>Choose a company to manage real assignments and reviewed work items.</p></div></div>
<div class="notice warn"><span>This foundation currently supports one tool: creating an internal Operations work item after manager approval. Other agents and integrations are not yet available. Legacy NAi records are not company-isolated by this module.</span></div>
<div class="settings-grid" style="margin-top:20px">
<div class="card"><div class="card-head"><h3>Your companies</h3></div>
@forelse($companies as $company)
<p><a class="btn btn-block" href="{{ route('workforce.company',$company) }}">{{ $company->name }} →</a></p>
@empty <p class="muted">You are not a member of a workforce company yet.</p> @endforelse
</div>
@can('admin')<div class="card"><div class="card-head"><h3>Create company</h3></div><p class="muted">You become the first company manager. Add other users explicitly afterward.</p><form method="POST" action="{{ route('workforce.companies.create') }}">@csrf<div class="field"><label for="company-name">Company name</label><input class="input" id="company-name" name="name" maxlength="160" required></div><button class="btn btn-primary">Create company</button></form></div>@endcan
</div>
</x-layout>
