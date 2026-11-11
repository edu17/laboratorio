<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\PermissionRegistrar;

/**
 * Crea los permisos del catálogo de médicos remitentes y los asigna a los roles existentes,
 * igual que RolesAndPermissionsSeeder en instalaciones nuevas.
 */
return new class extends Migration
{
    /** @var array<string, list<string>> */
    private const GRANTS = [
        'Administrador' => ['ViewAny:ReferringDoctor', 'View:ReferringDoctor', 'Create:ReferringDoctor', 'Update:ReferringDoctor'],
        'Técnico' => ['ViewAny:ReferringDoctor', 'View:ReferringDoctor', 'Create:ReferringDoctor', 'Update:ReferringDoctor'],
        'Validador' => ['ViewAny:ReferringDoctor', 'View:ReferringDoctor'],
    ];

    /**
     * Run the migrations.
     */
    public function up(): void
    {
        $tables = config('permission.table_names');

        foreach (self::GRANTS as $roleName => $permissions) {
            $roleId = DB::table($tables['roles'])->where('name', $roleName)->where('guard_name', 'web')->value('id');

            foreach ($permissions as $permission) {
                $permissionId = DB::table($tables['permissions'])->where('name', $permission)->where('guard_name', 'web')->value('id')
                    ?? DB::table($tables['permissions'])->insertGetId([
                        'name' => $permission,
                        'guard_name' => 'web',
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);

                if ($roleId) {
                    DB::table($tables['role_has_permissions'])->insertOrIgnore(['permission_id' => $permissionId, 'role_id' => $roleId]);
                }
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
            ->where('name', 'like', '%:ReferringDoctor')
            ->where('guard_name', 'web')
            ->pluck('id');

        DB::table($tables['role_has_permissions'])->whereIn('permission_id', $permissionIds)->delete();
        DB::table($tables['permissions'])->whereIn('id', $permissionIds)->delete();

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
};
