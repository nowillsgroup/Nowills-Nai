<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Support\Audit;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class UserController extends Controller
{
    public function index()
    {
        return view('users.index', ['users' => User::orderBy('name')->paginate(20)]);
    }

    public function store(Request $request)
    {
        $data = $request->validate(['name' => 'required|string|max:120', 'email' => 'required|email|unique:users,email', 'role' => 'required|in:admin,operator,viewer', 'title' => 'nullable|string|max:120', 'password' => 'required|string|min:10|confirmed']);
        $user = User::create($data);
        Audit::log('user.created', 'Added team member '.$user->email, $user);
        return back()->with('success', 'Team member added.');
    }

    public function edit(User $user)
    {
        return view('users.edit', compact('user'));
    }

    public function update(Request $request, User $user)
    {
        $data = $request->validate(['name' => 'required|string|max:120', 'email' => ['required', 'email', Rule::unique('users', 'email')->ignore($user->id)], 'role' => 'required|in:admin,operator,viewer', 'title' => 'nullable|string|max:120', 'password' => 'nullable|string|min:10|confirmed']);
        if ($user->id === auth()->id() && $data['role'] !== 'admin') return back()->with('error', 'You cannot remove your own admin role.');
        if (empty($data['password'])) unset($data['password']);
        $user->update($data);
        Audit::log('user.updated', 'Updated team member '.$user->email, $user);
        return back()->with('success', 'Team member updated.');
    }

    public function destroy(User $user)
    {
        if ($user->id === auth()->id()) return back()->with('error', 'You cannot delete your own account.');
        $email = $user->email; $user->delete();
        Audit::log('user.deleted', 'Removed team member '.$email);
        return back()->with('success', 'Team member removed.');
    }
}
