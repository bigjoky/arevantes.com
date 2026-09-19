<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::transaction(function (): void {
            $source = DB::table('permissions')->where('key', 'manage-installations')->value('id');
            foreach (['issue-invoices' => 'Emitir facturas', 'prepare-credit-notes' => 'Preparar rectificativas'] as $key => $name) {
                if (DB::table('permissions')->where('key', $key)->exists()) {
                    continue;
                }
                $id = DB::table('permissions')->insertGetId([
                    'key' => $key, 'name' => $name, 'category' => 'finance', 'is_sensitive' => true,
                    'created_at' => now(), 'updated_at' => now(),
                ]);
                DB::table('permission_profile_permission')->insertUsing(
                    ['permission_profile_id', 'permission_id'],
                    DB::table('permission_profile_permission')->where('permission_id', $source)
                        ->select('permission_profile_id')->selectRaw('? as permission_id', [$id]),
                );
                // Preserve individual denials as well as grants, including their provenance.
                DB::table('user_permission_overrides')->insertUsing(
                    ['user_id', 'permission_id', 'allowed', 'granted_by', 'created_at', 'updated_at'],
                    DB::table('user_permission_overrides')->where('permission_id', $source)
                        ->select('user_id')->selectRaw('? as permission_id', [$id])
                        ->addSelect(['allowed', 'granted_by', 'created_at', 'updated_at']),
                );
            }
        });
    }

    public function down(): void
    {
        DB::table('permissions')->whereIn('key', ['issue-invoices', 'prepare-credit-notes'])->delete();
    }
};
