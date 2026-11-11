<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\PermissionRegistrar;

/**
 * Crea el rol Recepción y da al Técnico los mismos permisos para cubrirla: registrar pacientes y médicos,
 * crear órdenes, capturar resultados e imprimir reportes. Solo agrega: no quita permisos ya configurados.
 */
return new class extends Migration
{
    private const FRONT_DESK_PERMISSIONS = [
        'Access:Panel', 'ViewAny:Patient', 'View:Patient', 'Create:Patient', 'Update:Patient',
        'ViewAny:ReferringDoctor', 'View:ReferringDoctor', 'Create:ReferringDoctor', 'Update:ReferringDoctor',
        'ViewAny:Order', 'View:Order', 'Create:Order', 'Update:Order',
        'Capture:Result', 'View:Result', 'Generate:Report',
    ];

    /**
     * Run the migrations.
     */
    public function up(): void
    {
        $tables = config('permission.table_names');

        foreach (['Recepción', 'Técnico'] as $roleName) {
            $roleId = DB::table($tables['roles'])->where('name', $roleName)->where('guard_name', 'web')->value('id')
                ?? DB::table($tables['roles'])->insertGetId([
                    'name' => $roleName,
                    'guard_name' => 'web',
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);

            foreach (self::FRONT_DESK_PERMISSIONS as $permission) {
                $permissionId = DB::table($tables['permissions'])->where('name', $permission)->where('guard_name', 'web')->value('id')
                    ?? DB::table($tables['permissions'])->insertGetId([
                        'name' => $permission,
                        'guard_name' => 'web',
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);

                DB::table($tables['role_has_permissions'])->insertOrIgnore(['permission_id' => $permissionId, 'role_id' => $roleId]);
            }
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    /**
     * Quita el rol Recepción si nadie lo tiene asignado; los permisos del Técnico se dejan como estén.
     */
    public function down(): void
    {
        $tables = config('permission.table_names');
        $roleId = DB::table($tables['roles'])->where('name', 'Recepción')->where('guard_name', 'web')->value('id');

        if ($roleId && ! DB::table($tables['model_has_roles'])->where('role_id', $roleId)->exists()) {
            DB::table($tables['role_has_permissions'])->where('role_id', $roleId)->delete();
            DB::table($tables['roles'])->where('id', $roleId)->delete();
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
};
