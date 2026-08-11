<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $profileId = DB::table('permission_profiles')->where('slug', 'finance')->value('id');
        $permissionId = DB::table('permissions')->where('key', 'view-purchases')->value('id');

        if ($profileId && $permissionId) {
            DB::table('permission_profile_permission')->insertOrIgnore([
                'permission_profile_id' => $profileId,
                'permission_id' => $permissionId,
            ]);
        }
    }

    public function down(): void
    {
        $profileId = DB::table('permission_profiles')->where('slug', 'finance')->value('id');
        $permissionId = DB::table('permissions')->where('key', 'view-purchases')->value('id');

        if ($profileId && $permissionId) {
            DB::table('permission_profile_permission')
                ->where('permission_profile_id', $profileId)
                ->where('permission_id', $permissionId)
                ->delete();
        }
    }
};
