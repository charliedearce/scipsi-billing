<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('permissions')->insertOrIgnore([
            'name' => 'document_series:manage',
            'category' => 'Billing',
            'description' => 'View document number series and update prefixes for future allocations',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $permissionId = DB::table('permissions')->where('name', 'document_series:manage')->value('id');
        $administratorRoleIds = DB::table('roles')
            ->where('name', 'Administrator')
            ->where('is_system', true)
            ->pluck('id');

        foreach ($administratorRoleIds as $roleId) {
            DB::table('role_permissions')->insertOrIgnore([
                'role_id' => $roleId,
                'permission_id' => $permissionId,
            ]);
        }
    }

    public function down(): void
    {
        $permissionId = DB::table('permissions')->where('name', 'document_series:manage')->value('id');

        if ($permissionId === null) {
            return;
        }

        DB::table('role_permissions')->where('permission_id', $permissionId)->delete();
        DB::table('permissions')->where('id', $permissionId)->delete();
    }
};
