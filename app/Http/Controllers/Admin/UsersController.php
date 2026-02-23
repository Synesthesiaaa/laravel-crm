<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\View\View;

class UsersController extends Controller
{
    public function index(Request $request): View
    {
        $users = User::orderBy('username')->get();
        return view('admin.users', [
            'users' => $users,
            'campaignName' => $request->session()->get('campaign_name', 'CRM'),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'username' => 'required|string|max:80|unique:users,username',
            'full_name' => 'required|string|max:255',
            'password' => 'required|string|min:4|confirmed',
            'role' => 'required|in:Super Admin,Admin,Team Leader,Agent',
            'vici_user' => 'nullable|string|max:80',
            'vici_pass' => 'nullable|string|max:255',
        ], [
            'username.required' => 'Username is required.',
            'username.unique' => 'That username is already in use.',
            'full_name.required' => 'Full name is required.',
            'password.required' => 'Password is required.',
            'password.min' => 'Password must be at least 4 characters.',
            'password.confirmed' => 'Password confirmation does not match.',
        ]);
        User::create([
            'username' => $validated['username'],
            'full_name' => $validated['full_name'],
            'password' => Hash::make($validated['password']),
            'role' => $validated['role'],
            'vici_user' => $validated['vici_user'] ?? null,
            'vici_pass' => $validated['vici_pass'] ?? null,
        ]);
        return redirect()->route('admin.users.index')->with('success', 'User created.');
    }

    public function update(Request $request, User $user): RedirectResponse
    {
        $validated = $request->validate([
            'username' => 'required|string|max:80|unique:users,username,' . $user->id,
            'full_name' => 'required|string|max:255',
            'role' => 'required|in:Super Admin,Admin,Team Leader,Agent',
            'vici_user' => 'nullable|string|max:80',
            'vici_pass' => 'nullable|string|max:255',
            'password' => 'nullable|string|min:4|confirmed',
        ], [
            'username.required' => 'Username is required.',
            'username.unique' => 'That username is already in use.',
            'full_name.required' => 'Full name is required.',
            'password.min' => 'Password must be at least 4 characters.',
            'password.confirmed' => 'Password confirmation does not match.',
        ]);
        $user->username = $validated['username'];
        $user->full_name = $validated['full_name'];
        $user->role = $validated['role'];
        $user->vici_user = $validated['vici_user'] ?? null;
        if ($request->filled('vici_pass')) {
            $user->vici_pass = $validated['vici_pass'];
        }
        if ($request->filled('password')) {
            $user->password = Hash::make($validated['password']);
        }
        $user->save();
        return redirect()->route('admin.users.index')->with('success', 'User updated.');
    }

    public function destroy(Request $request): RedirectResponse
    {
        $id = (int) $request->input('id');
        $user = User::findOrFail($id);
        if ($user->id === $request->user()->id) {
            return redirect()->route('admin.users.index')->with('error', 'You cannot delete your own account.');
        }
        $user->delete();
        return redirect()->route('admin.users.index')->with('success', 'User deleted.');
    }
}
