<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('roles', function (Blueprint $table) {
            $table->unsignedInteger('lock_version')->default(1);
        });

        DB::table('permissions')->insertOrIgnore([
            'name' => 'roles:manage',
            'category' => 'Identity',
            'description' => 'Manage organization roles and their permission bundles',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $permissionId = DB::table('permissions')->where('name', 'roles:manage')->value('id');
        foreach (DB::table('roles')->where('name', 'Administrator')->pluck('id') as $roleId) {
            DB::table('role_permissions')->insertOrIgnore([
                'role_id' => $roleId,
                'permission_id' => $permissionId,
            ]);
        }
    }

    public function down(): void
    {
        $permissionId = DB::table('permissions')->where('name', 'roles:manage')->value('id');
        DB::table('role_permissions')->where('permission_id', $permissionId)->delete();
        DB::table('permissions')->where('id', $permissionId)->delete();
        Schema::table('roles', function (Blueprint $table) {
            $table->dropColumn('lock_version');
        });
    }
};
