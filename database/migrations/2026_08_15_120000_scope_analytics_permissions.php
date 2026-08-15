<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $now = now();
        $definitions = [
            ['view-analytics-executive', 'Ver analítica de dirección'],
            ['view-analytics-sales', 'Ver analítica comercial'],
            ['view-analytics-operations', 'Ver analítica de operaciones'],
            ['view-analytics-supply', 'Ver analítica de supply'],
            ['view-analytics-finance', 'Ver analítica financiera'],
        ];

        foreach ($definitions as [$key, $name]) {
            DB::table('permissions')->updateOrInsert(
                ['key' => $key],
                ['name' => $name, 'category' => 'analytics', 'is_sensitive' => true, 'updated_at' => $now, 'created_at' => $now],
            );
        }

        $profilePermissions = [
            'administrator' => array_column($definitions, 0),
            'commercial' => ['view-analytics-sales'],
            'finance' => ['view-analytics', 'view-analytics-executive', 'view-analytics-finance'],
            'purchasing' => ['view-analytics', 'view-analytics-supply'],
            'technician' => ['view-analytics-operations'],
        ];

        foreach ($profilePermissions as $profileSlug => $permissionKeys) {
            $profileId = DB::table('permission_profiles')->where('slug', $profileSlug)->value('id');
            if (! $profileId) {
                continue;
            }

            $permissionIds = DB::table('permissions')->whereIn('key', $permissionKeys)->pluck('id');
            foreach ($permissionIds as $permissionId) {
                DB::table('permission_profile_permission')->updateOrInsert([
                    'permission_profile_id' => $profileId,
                    'permission_id' => $permissionId,
                ]);
            }
        }

        $areaByCapability = [
            'manage-customers' => 'view-analytics-sales',
            'manage-operations' => 'view-analytics-operations',
            'view-purchases' => 'view-analytics-supply',
            'view-finance' => 'view-analytics-finance',
        ];
        $basePermissionId = DB::table('permissions')->where('key', 'view-analytics')->value('id');
        $profilesWithAnalytics = DB::table('permission_profile_permission')
            ->join('permission_profiles', 'permission_profiles.id', '=', 'permission_profile_permission.permission_profile_id')
            ->where('permission_id', $basePermissionId)
            ->where('permission_profiles.is_system', false)
            ->pluck('permission_profile_permission.permission_profile_id');

        foreach ($profilesWithAnalytics as $profileId) {
            $capabilities = DB::table('permissions')
                ->join('permission_profile_permission', 'permission_profile_permission.permission_id', '=', 'permissions.id')
                ->where('permission_profile_permission.permission_profile_id', $profileId)
                ->pluck('permissions.key');
            foreach ($areaByCapability as $capability => $areaPermission) {
                if (! $capabilities->contains($capability)) {
                    continue;
                }
                DB::table('permission_profile_permission')->updateOrInsert([
                    'permission_profile_id' => $profileId,
                    'permission_id' => DB::table('permissions')->where('key', $areaPermission)->value('id'),
                ]);
            }
        }
    }

    public function down(): void
    {
        $permissionIds = DB::table('permissions')->whereIn('key', [
            'view-analytics-executive',
            'view-analytics-sales',
            'view-analytics-operations',
            'view-analytics-supply',
            'view-analytics-finance',
        ])->pluck('id');

        DB::table('permission_profile_permission')->whereIn('permission_id', $permissionIds)->delete();
        DB::table('user_permission_overrides')->whereIn('permission_id', $permissionIds)->delete();
        DB::table('permissions')->whereIn('id', $permissionIds)->delete();
    }
};
