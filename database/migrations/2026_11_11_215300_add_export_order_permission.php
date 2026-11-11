<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\PermissionRegistrar;

/**
 * Permiso para exportar órdenes a Excel, asignado al Administrador; otros roles lo reciben desde Roles y permisos.
 */
return new class extends Migration
{
    public function up(): void
    {
        $tables = config('permission.table_names');
        $permissionId = DB::table($tables['permissions'])->where('name', 'Export:Order')->where('guard_name', 'web')->value('id')
            ?? DB::table($tables['permissions'])->insertGetId([
                'name' => 'Export:Order',
                'guard_name' => 'web',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        $roleId = DB::table($tables['roles'])->where('name', 'Administrador')->where('guard_name', 'web')->value('id');

        if ($roleId) {
            DB::table($tables['role_has_permissions'])->insertOrIgnore(['permission_id' => $permissionId, 'role_id' => $roleId]);
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function down(): void
    {
        $tables = config('permission.table_names');
        $permissionId = DB::table($tables['permissions'])->where('name', 'Export:Order')->where('guard_name', 'web')->value('id');

        if ($permissionId) {
            DB::table($tables['role_has_permissions'])->where('permission_id', $permissionId)->delete();
            DB::table($tables['model_has_permissions'])->where('permission_id', $permissionId)->delete();
            DB::table($tables['permissions'])->where('id', $permissionId)->delete();
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
};
