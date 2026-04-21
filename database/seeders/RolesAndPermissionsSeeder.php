<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class RolesAndPermissionsSeeder extends Seeder
{
    public function run(): void
    {
        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();

        $permissions = [
            'manage-users',
            'manage-campaigns',
            'manage-forms',
            'manage-servers',
            'view-reports',
            'export-data',
            'manage-disposition-codes',
            'view-agent-dashboard',
            'manage-field-logic',
            'manage-agent-screen',
            'manage-leads',
            'import-leads',
            'export-leads',
            'manage-lead-fields',
        ];

        foreach ($permissions as $permission) {
            Permission::firstOrCreate(['name' => $permission, 'guard_name' => 'web']);
        }

        $superAdmin = Role::firstOrCreate(['name' => 'Super Admin', 'guard_name' => 'web']);
        $superAdmin->syncPermissions(Permission::all());

        $admin = Role::firstOrCreate(['name' => 'Admin', 'guard_name' => 'web']);
        $admin->syncPermissions([
            'manage-campaigns',
            'manage-forms',
            'manage-disposition-codes',
            'manage-field-logic',
            'manage-agent-screen',
            'view-reports',
            'export-data',
            'manage-leads',
            'import-leads',
            'export-leads',
        ]);

        $teamLeader = Role::firstOrCreate(['name' => 'Team Leader', 'guard_name' => 'web']);
        $teamLeader->syncPermissions([
            'view-reports',
            'export-data',
            'view-agent-dashboard',
            'export-leads',
        ]);

        $agent = Role::firstOrCreate(['name' => 'Agent', 'guard_name' => 'web']);
        $agent->syncPermissions([
            'view-agent-dashboard',
        ]);
    }
}
