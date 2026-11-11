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
        $permissions = [];

        foreach (['Publish:Report', 'Revoke:Report'] as $name) {
            $permissionId = DB::table($tables['permissions'])
                ->where('name', $name)
                ->where('guard_name', 'web')
                ->value('id');

            if (! $permissionId) {
                $permissionId = DB::table($tables['permissions'])->insertGetId([
                    'name' => $name,
                    'guard_name' => 'web',
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }

            $permissions[] = $permissionId;
        }

        $validatorRoleId = DB::table($tables['roles'])
            ->where('name', 'Validador')
            ->where('guard_name', 'web')
            ->value('id');

        if ($validatorRoleId) {
            foreach ($permissions as $permissionId) {
                DB::table($tables['role_has_permissions'])->insertOrIgnore([
                    'permission_id' => $permissionId,
                    'role_id' => $validatorRoleId,
                ]);
            }
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $tables = config('permission.table_names');
        $permissionIds = DB::table($tables['permissions'])
            ->whereIn('name', ['Publish:Report', 'Revoke:Report'])
            ->where('guard_name', 'web')
            ->pluck('id');
        $validatorRoleId = DB::table($tables['roles'])
            ->where('name', 'Validador')
            ->where('guard_name', 'web')
            ->value('id');

        if ($validatorRoleId) {
            DB::table($tables['role_has_permissions'])
                ->where('role_id', $validatorRoleId)
                ->whereIn('permission_id', $permissionIds)
                ->delete();
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
};
