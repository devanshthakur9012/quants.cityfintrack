<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class RoleController extends Controller
{
    // ─────────────────────────────────────────────
    //  ROLES
    // ─────────────────────────────────────────────

    public function index()
    {
        $pageTitle = 'Roles';
        $roles     = Role::withCount(['permissions', 'users'])->orderByDesc('id')->paginate(getPaginate());
        return view('admin.roles.index', compact('pageTitle', 'roles'));
    }

    public function create()
    {
        $pageTitle   = 'Create Role';
        $permissions = Permission::orderBy('name')->get()->groupBy(fn($p) => explode('.', $p->name)[0]);
        return view('admin.roles.form', compact('pageTitle', 'permissions'));
    }

    public function store(Request $request)
    {
        $request->validate([
            'name'          => 'required|string|max:100|unique:roles,name',
            'permissions'   => 'nullable|array',
            'permissions.*' => 'exists:permissions,id',
        ]);

        $role = Role::create(['name' => strtolower($request->name), 'guard_name' => 'web']);

        if ($request->permissions) {
            $role->syncPermissions($request->permissions);
        }

        $notify[] = ['success', 'Role created successfully.'];
        return redirect()->route('admin.roles.index')->withNotify($notify);
    }

    public function edit($id)
    {
        $pageTitle   = 'Edit Role';
        $role        = Role::with('permissions')->findOrFail($id);
        $permissions = Permission::orderBy('name')->get()->groupBy(fn($p) => explode('.', $p->name)[0]);
        return view('admin.roles.form', compact('pageTitle', 'role', 'permissions'));
    }

    public function update(Request $request, $id)
    {
        $role = Role::findOrFail($id);

        $request->validate([
            'name'          => 'required|string|max:100|unique:roles,name,' . $role->id,
            'permissions'   => 'nullable|array',
            'permissions.*' => 'exists:permissions,id',
        ]);

        $role->update(['name' => strtolower($request->name)]);
        $role->syncPermissions($request->permissions ?? []);

        $notify[] = ['success', 'Role updated successfully.'];
        return redirect()->route('admin.roles.index')->withNotify($notify);
    }

    public function destroy($id)
    {
        $role = Role::findOrFail($id);

        if (in_array($role->name, ['admin', 'user'])) {
            $notify[] = ['error', 'This role cannot be deleted.'];
            return back()->withNotify($notify);
        }

        $role->delete();

        $notify[] = ['success', 'Role deleted successfully.'];
        return back()->withNotify($notify);
    }

    // ─────────────────────────────────────────────
    //  PERMISSIONS
    // ─────────────────────────────────────────────

    public function permissions()
    {
        $pageTitle   = 'Permissions';
        $permissions = Permission::orderBy('name')->paginate(getPaginate());
        return view('admin.roles.permissions', compact('pageTitle', 'permissions'));
    }

    public function createPermission()
    {
        $pageTitle = 'Create Permission';
        return view('admin.roles.permission_form', compact('pageTitle'));
    }

    public function storePermission(Request $request)
    {
        $request->validate([
            'name'   => 'required|string|max:100|unique:permissions,name',
            'group'  => 'required|string|max:100',
        ]);

        // Convention: group.action  e.g. "users.edit"
        $name = strtolower($request->group . '.' . $request->name);
        Permission::create(['name' => $name, 'guard_name' => 'web']);

        $notify[] = ['success', 'Permission created successfully.'];
        return redirect()->route('admin.roles.permissions')->withNotify($notify);
    }

    public function destroyPermission($id)
    {
        $permission = Permission::findOrFail($id);
        $permission->delete();

        $notify[] = ['success', 'Permission deleted.'];
        return back()->withNotify($notify);
    }
}