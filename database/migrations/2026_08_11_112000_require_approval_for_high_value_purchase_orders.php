<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('purchase_settings', function (Blueprint $table) {
            $table->decimal('order_approval_threshold', 14, 2)->default(10000)->after('shipping_tax_percent');
        });

        $permissionId = DB::table('permissions')->insertGetId([
            'key' => 'approve-purchase-orders',
            'name' => 'Aprobar pedidos de compra',
            'category' => 'purchasing',
            'is_sensitive' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('permission_profiles')
            ->whereIn('slug', ['administrator', 'commercial', 'purchasing'])
            ->pluck('id')
            ->each(fn ($profileId) => DB::table('permission_profile_permission')->insertOrIgnore([
                'permission_profile_id' => $profileId,
                'permission_id' => $permissionId,
            ]));
    }

    public function down(): void
    {
        $permissionId = DB::table('permissions')->where('key', 'approve-purchase-orders')->value('id');
        if ($permissionId) {
            DB::table('permission_profile_permission')->where('permission_id', $permissionId)->delete();
            DB::table('permissions')->where('id', $permissionId)->delete();
        }

        Schema::table('purchase_settings', function (Blueprint $table) {
            $table->dropColumn('order_approval_threshold');
        });
    }
};
