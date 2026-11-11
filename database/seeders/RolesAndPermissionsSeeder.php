<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class RolesAndPermissionsSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        DB::transaction(function (): void {
            $permissions = [
                'Access:Panel', 'ViewAny:Patient', 'View:Patient', 'Create:Patient', 'Update:Patient',
                'ViewAny:Exam', 'View:Exam', 'Create:Exam', 'Update:Exam',
                'ViewAny:ExamSection', 'View:ExamSection', 'Create:ExamSection', 'Update:ExamSection',
                'ViewAny:ExamParameter', 'View:ExamParameter', 'Create:ExamParameter', 'Update:ExamParameter',
                'ViewAny:Order', 'View:Order', 'Create:Order', 'Update:Order', 'Cancel:Order',
                'Validate:Order', 'Reopen:Order',
                'Capture:Result', 'View:Result',
                'Generate:Report', 'Publish:Report', 'Revoke:Report',
                'Manage:User', 'Manage:Role', 'Manage:Template', 'Manage:LabSettings', 'View:Audit',
            ];

            foreach ($permissions as $permission) {
                Permission::findOrCreate($permission, 'web');
            }

            app(PermissionRegistrar::class)->forgetCachedPermissions();

            Role::findOrCreate('Administrador', 'web')->syncPermissions(array_values(array_diff($permissions, [
                'Validate:Order', 'Reopen:Order', 'Publish:Report', 'Revoke:Report',
            ])));
            Role::findOrCreate('Técnico', 'web')->syncPermissions([
                'Access:Panel', 'ViewAny:Patient', 'View:Patient', 'Create:Patient', 'Update:Patient',
            ]);
            Role::findOrCreate('Validador', 'web')->syncPermissions([
                'Access:Panel', 'ViewAny:Patient', 'View:Patient',
                'ViewAny:Exam', 'View:Exam',
                'ViewAny:ExamSection', 'View:ExamSection',
                'ViewAny:ExamParameter', 'View:ExamParameter',
                'ViewAny:Order', 'View:Order',
                'View:Result',
                'Validate:Order', 'Reopen:Order',
                'Generate:Report',
                'Publish:Report', 'Revoke:Report',
            ]);
        });

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
}
