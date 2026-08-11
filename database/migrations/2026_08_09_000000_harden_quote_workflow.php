<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('quote_status_histories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('quote_id')->constrained()->cascadeOnDelete();
            $table->foreignId('changed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('from_status', 20)->nullable();
            $table->string('to_status', 20);
            $table->string('channel', 30)->default('internal');
            $table->text('reason')->nullable();
            $table->timestamp('changed_at');
            $table->timestamps();
            $table->index(['quote_id', 'changed_at']);
        });

        $now = now();
        DB::table('quotes')->orderBy('id')->chunkById(500, function ($quotes) use ($now): void {
            DB::table('quote_status_histories')->insert($quotes->map(fn ($quote) => [
                'quote_id' => $quote->id,
                'changed_by' => $quote->created_by,
                'from_status' => null,
                'to_status' => $quote->status,
                'channel' => 'migration',
                'reason' => null,
                'changed_at' => $quote->created_at ?? $now,
                'created_at' => $now,
                'updated_at' => $now,
            ])->all());
        });

        if (Schema::hasTable('permissions')) {
            DB::table('permissions')->insertOrIgnore([
                'key' => 'approve-quotes',
                'name' => 'Aprobar ofertas comerciales',
                'category' => 'commercial',
                'is_sensitive' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
            $permissionId = DB::table('permissions')->where('key', 'approve-quotes')->value('id');
            $administratorId = DB::table('permission_profiles')->where('slug', 'administrator')->value('id');
            if ($permissionId && $administratorId) {
                DB::table('permission_profile_permission')->insertOrIgnore([
                    'permission_profile_id' => $administratorId,
                    'permission_id' => $permissionId,
                ]);
            }
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('permissions')) {
            $permissionId = DB::table('permissions')->where('key', 'approve-quotes')->value('id');
            if ($permissionId) {
                DB::table('permission_profile_permission')->where('permission_id', $permissionId)->delete();
                DB::table('user_permission_overrides')->where('permission_id', $permissionId)->delete();
                DB::table('permissions')->where('id', $permissionId)->delete();
            }
        }
        Schema::dropIfExists('quote_status_histories');
    }
};
