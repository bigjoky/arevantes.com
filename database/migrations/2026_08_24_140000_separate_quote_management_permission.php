<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('permissions')) {
            return;
        }

        $now = now();
        DB::table('permissions')->insertOrIgnore([
            'key' => 'manage-quotes',
            'name' => 'Gestionar propuestas comerciales',
            'category' => 'commercial',
            'is_sensitive' => false,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        $permissionId = DB::table('permissions')->where('key', 'manage-quotes')->value('id');
        $profileIds = DB::table('permission_profiles')
            ->whereIn('slug', ['administrator', 'commercial'])
            ->pluck('id');

        foreach ($profileIds as $profileId) {
            DB::table('permission_profile_permission')->insertOrIgnore([
                'permission_profile_id' => $profileId,
                'permission_id' => $permissionId,
            ]);
        }
    }

    public function down(): void
    {
        if (! Schema::hasTable('permissions')) {
            return;
        }

        $permissionId = DB::table('permissions')->where('key', 'manage-quotes')->value('id');
        if (! $permissionId) {
            return;
        }

        DB::table('permission_profile_permission')->where('permission_id', $permissionId)->delete();
        DB::table('user_permission_overrides')->where('permission_id', $permissionId)->delete();
        DB::table('permissions')->where('id', $permissionId)->delete();
    }
};
