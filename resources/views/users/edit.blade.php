<x-layout :title="'Edit '.$user->name" subtitle="Team">
<div class="page-head"><div><h2>Edit team member</h2><p>Update profile, role, or reset the account password.</p></div><div class="head-actions"><a href="{{ route('users.index') }}" class="btn">Cancel</a></div></div>
<div class="card form-card" style="max-width:640px"><form method="POST" action="{{ route('users.update',$user) }}">@csrf @method('PUT')
<div class="form-grid">
<div class="field full"><label>Name</label><input class="input" name="name" value="{{ old('name',$user->name) }}" required></div>
<div class="field full"><label>Email</label><input class="input" type="email" name="email" value="{{ old('email',$user->email) }}" required></div>
<div class="field"><label>Title</label><input class="input" name="title" value="{{ old('title',$user->title) }}"></div>
<div class="field"><label>Role</label><select name="role" @disabled($user->id===auth()->id())>@foreach(['admin'=>'Admin','operator'=>'Operator','viewer'=>'Viewer'] as $val=>$label)<option value="{{ $val }}" @selected(old('role',$user->role)===$val)>{{ $label }}</option>@endforeach</select>@if($user->id===auth()->id())<input type="hidden" name="role" value="{{ $user->role }}">@endif</div>
<div class="field"><label>New password</label><input class="input" type="password" name="password" minlength="10" autocomplete="new-password"><div class="help">Leave blank to keep the current password.</div></div>
<div class="field"><label>Confirm password</label><input class="input" type="password" name="password_confirmation" autocomplete="new-password"></div>
</div>
<div class="form-actions"><a href="{{ route('users.index') }}" class="btn">Cancel</a><button class="btn btn-primary"><x-icon name="check" :size="16" />Save member</button></div>
</form></div>
</x-layout>
