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
     * Recepción y Técnico: registrar pacientes y médicos, crear órdenes, capturar resultados e imprimir reportes.
     *
     * @var list<string>
     */
    public const FRONT_DESK_PERMISSIONS = [
        'Access:Panel', 'ViewAny:Patient', 'View:Patient', 'Create:Patient', 'Update:Patient',
        'ViewAny:ReferringDoctor', 'View:ReferringDoctor', 'Create:ReferringDoctor', 'Update:ReferringDoctor',
        'ViewAny:Order', 'View:Order', 'Create:Order', 'Update:Order',
        'Capture:Result', 'View:Result', 'Generate:Report',
    ];

    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        DB::transaction(function (): void {
            $permissions = [
                'Access:Panel', 'ViewAny:Patient', 'View:Patient', 'Create:Patient', 'Update:Patient',
                'ViewAny:ReferringDoctor', 'View:ReferringDoctor', 'Create:ReferringDoctor', 'Update:ReferringDoctor',
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

            $defaults = [
                'Administrador' => array_values(array_diff($permissions, ['Validate:Order', 'Reopen:Order', 'Publish:Report', 'Revoke:Report'])),
                'Recepción' => self::FRONT_DESK_PERMISSIONS,
                'Técnico' => self::FRONT_DESK_PERMISSIONS,
                'Validador' => [
                    'Access:Panel', 'ViewAny:Patient', 'View:Patient',
                    'ViewAny:ReferringDoctor', 'View:ReferringDoctor',
                    'ViewAny:Exam', 'View:Exam',
                    'ViewAny:ExamSection', 'View:ExamSection',
                    'ViewAny:ExamParameter', 'View:ExamParameter',
                    'ViewAny:Order', 'View:Order',
                    'View:Result',
                    'Validate:Order', 'Reopen:Order',
                    'Generate:Report',
                    'Publish:Report', 'Revoke:Report',
                ],
            ];

            // Los permisos por defecto solo se asignan al crear el rol: después se configuran
            // desde Roles en el panel, y volver a ejecutar el seeder no deshace esos cambios.
            foreach ($defaults as $roleName => $rolePermissions) {
                $role = Role::firstOrCreate(['name' => $roleName, 'guard_name' => 'web']);

                if ($role->wasRecentlyCreated) {
                    $role->syncPermissions($rolePermissions);
                }
            }
        });

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
}
