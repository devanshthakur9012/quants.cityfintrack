<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class RolePermissionSeeder extends Seeder
{
    /**
     * Permission convention:  {group}.{action}
     * e.g.  users.view,  users.edit,  reports.view
     */
    protected array $permissions = [
        // User management
        'users.view',
        'users.create',
        'users.edit',
        'users.delete',
        'users.ban',
        'users.login-as',

        // Role management
        'roles.view',
        'roles.create',
        'roles.edit',
        'roles.delete',

        // Reports
        'reports.view',
        'reports.export',

        // Notifications
        'notifications.send',

        // Settings
        'settings.view',
        'settings.edit',
    ];

    protected array $roles = [
        'admin'    => '*',          // all permissions
        'hr'       => [
            'users.view', 'users.create', 'users.edit',
        ],
        'employee' => [
            'reports.view',
        ],
        'user'     => [],           // no admin permissions
    ];

    public function run(): void
    {
        // Create all permissions
        foreach ($this->permissions as $name) {
            Permission::firstOrCreate(['name' => $name, 'guard_name' => 'web']);
        }

        // Create roles and assign permissions
        foreach ($this->roles as $roleName => $perms) {
            $role = Role::firstOrCreate(['name' => $roleName, 'guard_name' => 'web']);

            if ($perms === '*') {
                $role->syncPermissions(Permission::all());
            } elseif (!empty($perms)) {
                $role->syncPermissions($perms);
            }
        }

        $this->command->info('Roles and permissions seeded successfully.');
    }
}