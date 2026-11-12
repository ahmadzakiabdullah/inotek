<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Role;
use App\Services\AuditLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class RoleController extends Controller
{
    /**
     * System roles the application logic depends on. Protected by name, not ID,
     * so they stay safe regardless of how the roles table was seeded.
     */
    private const PROTECTED_ROLES = ['admin', 'lecturer', 'judge', 'user'];

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

        if (Schema::hasColumn('roles', 'guard_name')) {
            $validated['guard_name'] = 'web';
        }

        Role::create($validated);

        // Flash toast notification using current system convention
        $this->toast([
                'type' => 'success',
                'message' => __('Role created successfully.'),
            ]);

        return redirect()->route('admin.roles.index');
    }

    /**
     * Update the specified role in storage.
     */
    public function update(Request $request, Role $role): RedirectResponse
    {
        $isProtected = in_array($role->name, self::PROTECTED_ROLES, true);

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
            $this->toast(['type' => 'error', 'message' => $msg]);

            return redirect()->route('admin.roles.index');
        }

        $role->update($validated);

        $msg = __('Role updated successfully.');
        $this->toast(['type' => 'success', 'message' => $msg]);

        return redirect()->route('admin.roles.index');
    }

    /**
     * Remove the specified role from storage.
     */
    public function destroy(Role $role): RedirectResponse
    {
        if (in_array($role->name, self::PROTECTED_ROLES, true)) {
            $msg = __('Cannot delete system-protected roles.');
            $this->toast(['type' => 'error', 'message' => $msg]);

            return redirect()->route('admin.roles.index');
        }

        if ($role->users()->exists()) {
            $this->toast(['type' => 'error', 'message' => __('Reassign the users in this role before deleting it.')]);

            return redirect()->route('admin.roles.index');
        }

        $role->delete();

        AuditLogger::log('DELETE_ROLE', "Deleted role: '{$role->name}'");

        $msg = __('Role deleted successfully.');
        $this->toast(['type' => 'success', 'message' => $msg]);

        return redirect()->route('admin.roles.index');
    }
}
