<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Role;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Inertia\Inertia;
use Inertia\Response;

class UserController extends Controller
{
    /**
     * Display a listing of the users.
     */
    public function index(): Response
    {
        return Inertia::render('admin/users/index', [
            'users' => User::with('role')->orderBy('name')->get(),
            'roles' => Role::orderBy('id')->get(),
        ]);
    }

    /**
     * Store a newly created user in storage.
     */
    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'username' => ['required', 'string', 'alpha_dash', 'min:3', 'max:255', 'unique:users,username'],
            'email' => ['required', 'string', 'email', 'max:255', 'unique:users,email'],
            'password' => ['required', 'string', Password::defaults()],
            'role_id' => ['required', 'exists:roles,id'],
        ]);

        $validated['password'] = Hash::make($validated['password']);

        $newUser = User::create($validated);

        \App\Services\AuditLogger::log(
            'CREATE_USER',
            "Created user: '{$newUser->name}' (Email: {$newUser->email}, Role ID: {$newUser->role_id})"
        );

        if (class_exists(Inertia::class) && method_exists(Inertia::class, 'flash')) {
            Inertia::flash('toast', [
                'type' => 'success',
                'message' => __('User created successfully.'),
            ]);
        } else {
            session()->flash('toast', [
                'type' => 'success',
                'message' => __('User created successfully.'),
            ]);
        }

        return redirect()->route('admin.users.index');
    }

    /**
     * Update the specified user in storage.
     */
    public function update(Request $request, User $user): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'username' => [
                'required',
                'string',
                'alpha_dash',
                'min:3',
                'max:255',
                Rule::unique('users', 'username')->ignore($user->id),
            ],
            'email' => [
                'required',
                'string',
                'email',
                'max:255',
                Rule::unique('users', 'email')->ignore($user->id),
            ],
            'password' => ['nullable', 'string', Password::defaults()],
            'role_id' => ['required', 'exists:roles,id'],
        ]);

        if (! empty($validated['password'])) {
            $validated['password'] = Hash::make($validated['password']);
        } else {
            unset($validated['password']);
        }

        // Prevent self-demotion from admin
        if ($user->id === $request->user()->id && (int) $validated['role_id'] !== 1) {
            $msg = __('You cannot change your own admin role.');
            if (class_exists(Inertia::class) && method_exists(Inertia::class, 'flash')) {
                Inertia::flash('toast', ['type' => 'error', 'message' => $msg]);
            } else {
                session()->flash('toast', ['type' => 'error', 'message' => $msg]);
            }

            return redirect()->route('admin.users.index');
        }

        $user->update($validated);

        \App\Services\AuditLogger::log(
            'UPDATE_USER',
            "Updated user: '{$user->name}' (Email: {$user->email}, Role ID: {$user->role_id})"
        );

        $msg = __('User updated successfully.');
        if (class_exists(Inertia::class) && method_exists(Inertia::class, 'flash')) {
            Inertia::flash('toast', ['type' => 'success', 'message' => $msg]);
        } else {
            session()->flash('toast', ['type' => 'success', 'message' => $msg]);
        }

        return redirect()->route('admin.users.index');
    }

    /**
     * Remove the specified user from storage.
     */
    public function destroy(Request $request, User $user): RedirectResponse
    {
        // Prevent deleting yourself
        if ($user->id === $request->user()->id) {
            $msg = __('You cannot delete your own account.');
            if (class_exists(Inertia::class) && method_exists(Inertia::class, 'flash')) {
                Inertia::flash('toast', ['type' => 'error', 'message' => $msg]);
            } else {
                session()->flash('toast', ['type' => 'error', 'message' => $msg]);
            }

            return redirect()->route('admin.users.index');
        }

        $name = $user->name;
        $email = $user->email;
        $user->delete();

        \App\Services\AuditLogger::log(
            'DELETE_USER',
            "Deleted user: '{$name}' (Email: {$email})"
        );

        $msg = __('User deleted successfully.');
        if (class_exists(Inertia::class) && method_exists(Inertia::class, 'flash')) {
            Inertia::flash('toast', ['type' => 'success', 'message' => $msg]);
        } else {
            session()->flash('toast', ['type' => 'success', 'message' => $msg]);
        }

        return redirect()->route('admin.users.index');
    }
}
