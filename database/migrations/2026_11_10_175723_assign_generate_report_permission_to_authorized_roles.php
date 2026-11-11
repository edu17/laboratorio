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
            ->where('name', 'Generate:Report')
            ->where('guard_name', 'web')
            ->value('id');

        if (! $permissionId) {
            $permissionId = DB::table($tables['permissions'])->insertGetId([
                'name' => 'Generate:Report',
                'guard_name' => 'web',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        $roleIds = DB::table($tables['roles'])
            ->whereIn('name', ['Administrador', 'Validador'])
            ->where('guard_name', 'web')
            ->pluck('id');

        foreach ($roleIds as $roleId) {
            DB::table($tables['role_has_permissions'])->insertOrIgnore([
                'permission_id' => $permissionId,
                'role_id' => $roleId,
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
            ->where('name', 'Generate:Report')
            ->where('guard_name', 'web')
            ->value('id');

        if (! $permissionId) {
            return;
        }

        $roleIds = DB::table($tables['roles'])
            ->whereIn('name', ['Administrador', 'Validador'])
            ->where('guard_name', 'web')
            ->pluck('id');

        DB::table($tables['role_has_permissions'])
            ->where('permission_id', $permissionId)
            ->whereIn('role_id', $roleIds)
            ->delete();

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
};
