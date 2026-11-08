<x-layout title="Team" subtitle="Workspace members and access roles">
<div class="page-head"><div><h2>Team</h2><p>Manage access to the NAi workspace. Admins configure integrations; operators manage inventory; viewers have read-only access.</p></div></div>
<div class="grid grid-2" style="align-items:start">
<div class="table-wrap"><table><thead><tr><th>Member</th><th>Role</th><th class="text-right">Actions</th></tr></thead><tbody>
@foreach($users as $user)
<tr><td><div class="cell-title"><span class="avatar" style="width:34px;height:34px">{{ $user->initials() }}</span><span><strong>{{ $user->name }}</strong><span>{{ $user->email }}</span></span></div></td>
<td><form method="POST" action="{{ route('users.update',$user) }}" data-autosubmit>@csrf @method('PUT')<input type="hidden" name="name" value="{{ $user->name }}"><input type="hidden" name="email" value="{{ $user->email }}"><input type="hidden" name="title" value="{{ $user->title }}"><select name="role" @disabled($user->id===auth()->id()) style="width:120px">@foreach(['admin'=>'Admin','operator'=>'Operator','viewer'=>'Viewer'] as $val=>$label)<option value="{{ $val }}" @selected($user->role===$val)>{{ $label }}</option>@endforeach</select></form></td>
<td><div class="actions-cell"><a class="icon-btn" href="{{ route('users.edit',$user) }}" title="Edit"><x-icon name="edit" :size="15" /></a>@if($user->id!==auth()->id())<form method="POST" action="{{ route('users.destroy',$user) }}" data-confirm="Remove {{ $user->email }}?">@csrf @method('DELETE')<button class="icon-btn"><x-icon name="trash" :size="15" /></button></form>@endif</div></td></tr>
@endforeach
</tbody></table></div>
<div class="card"><div class="card-head"><h3>Add team member</h3></div><form method="POST" action="{{ route('users.store') }}">@csrf
<div class="field"><label>Name</label><input class="input" name="name" required></div>
<div class="field"><label>Email</label><input class="input" type="email" name="email" required></div>
<div class="field"><label>Title</label><input class="input" name="title"></div>
<div class="field"><label>Role</label><select name="role">@foreach(['operator'=>'Operator','viewer'=>'Viewer','admin'=>'Admin'] as $val=>$label)<option value="{{ $val }}">{{ $label }}</option>@endforeach</select></div>
<div class="field"><label>Password</label><input class="input" type="password" name="password" required minlength="10"></div>
<div class="field"><label>Confirm password</label><input class="input" type="password" name="password_confirmation" required></div>
<div class="form-actions"><button class="btn btn-primary"><x-icon name="plus" :size="16" />Add member</button></div>
</form></div>
</div>
<x-pagination :paginator="$users" />
</x-layout>
