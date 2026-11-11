<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\PermissionRegistrar;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        $tables = config('permission.table_names');
        $permissionId = DB::table($tables['permissions'])
            ->where('name', 'Manage:LabSettings')
            ->where('guard_name', 'web')
            ->value('id');

        if (! $permissionId) {
            $permissionId = DB::table($tables['permissions'])->insertGetId([
                'name' => 'Manage:LabSettings',
                'guard_name' => 'web',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        $administratorRoleId = DB::table($tables['roles'])
            ->where('name', 'Administrador')
            ->where('guard_name', 'web')
            ->value('id');

        if ($administratorRoleId) {
            DB::table($tables['role_has_permissions'])->insertOrIgnore([
                'permission_id' => $permissionId,
                'role_id' => $administratorRoleId,
            ]);
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $tables = config('permission.table_names');
        $permissionId = DB::table($tables['permissions'])
            ->where('name', 'Manage:LabSettings')
            ->where('guard_name', 'web')
            ->value('id');
        $administratorRoleId = DB::table($tables['roles'])
            ->where('name', 'Administrador')
            ->where('guard_name', 'web')
            ->value('id');

        if ($permissionId && $administratorRoleId) {
            DB::table($tables['role_has_permissions'])
                ->where('permission_id', $permissionId)
                ->where('role_id', $administratorRoleId)
                ->delete();
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
};
