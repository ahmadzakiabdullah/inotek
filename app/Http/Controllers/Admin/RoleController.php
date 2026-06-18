<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Role;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class RoleController extends Controller
{
    /**
     * Display a listing of the roles.
     */
    public function index(): Response
    {
        return Inertia::render('admin/roles/index', [
            'roles' => Role::withCount('users')->orderBy('id')->get(),
        ]);
    }

    /**
     * Store a newly created role in storage.
     */
    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'alpha_dash', 'max:255', 'unique:roles,name'],
            'label' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:1000'],
        ]);

        // Standardize name to lowercase
        $validated['name'] = strtolower($validated['name']);

        Role::create($validated);

        // Flash toast notification using current system convention
        if (class_exists(Inertia::class) && method_exists(Inertia::class, 'flash')) {
            Inertia::flash('toast', [
                'type' => 'success',
                'message' => __('Role created successfully.'),
            ]);
        } else {
            session()->flash('toast', [
                'type' => 'success',
                'message' => __('Role created successfully.'),
            ]);
        }

        return redirect()->route('admin.roles.index');
    }

    /**
     * Update the specified role in storage.
     */
    public function update(Request $request, Role $role): RedirectResponse
    {
        $protectedRoles = [1, 2, 3, 4];
        $isProtected = in_array($role->id, $protectedRoles);

        $validated = $request->validate([
            'name' => [
                'required',
                'string',
                'alpha_dash',
                'max:255',
                Rule::unique('roles', 'name')->ignore($role->id),
            ],
            'label' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:1000'],
        ]);

        // Standardize name to lowercase
        $validated['name'] = strtolower($validated['name']);

        if ($isProtected && $validated['name'] !== $role->name) {
            $msg = __('Cannot change system name for protected roles.');
            if (class_exists(Inertia::class) && method_exists(Inertia::class, 'flash')) {
                Inertia::flash('toast', ['type' => 'error', 'message' => $msg]);
            } else {
                session()->flash('toast', ['type' => 'error', 'message' => $msg]);
            }

            return redirect()->route('admin.roles.index');
        }

        $role->update($validated);

        $msg = __('Role updated successfully.');
        if (class_exists(Inertia::class) && method_exists(Inertia::class, 'flash')) {
            Inertia::flash('toast', ['type' => 'success', 'message' => $msg]);
        } else {
            session()->flash('toast', ['type' => 'success', 'message' => $msg]);
        }

        return redirect()->route('admin.roles.index');
    }

    /**
     * Remove the specified role from storage.
     */
    public function destroy(Role $role): RedirectResponse
    {
        $protectedRoles = [1, 2, 3, 4];
        if (in_array($role->id, $protectedRoles)) {
            $msg = __('Cannot delete system-protected roles.');
            if (class_exists(Inertia::class) && method_exists(Inertia::class, 'flash')) {
                Inertia::flash('toast', ['type' => 'error', 'message' => $msg]);
            } else {
                session()->flash('toast', ['type' => 'error', 'message' => $msg]);
            }

            return redirect()->route('admin.roles.index');
        }

        $role->delete();

        $msg = __('Role deleted successfully.');
        if (class_exists(Inertia::class) && method_exists(Inertia::class, 'flash')) {
            Inertia::flash('toast', ['type' => 'success', 'message' => $msg]);
        } else {
            session()->flash('toast', ['type' => 'success', 'message' => $msg]);
        }

        return redirect()->route('admin.roles.index');
    }
}
