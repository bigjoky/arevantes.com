<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('commercial_field_sessions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->timestamp('started_at')->index();
            $table->timestamp('ended_at')->nullable();
            $table->timestamps();
            $table->index(['user_id', 'ended_at']);
        });
        Schema::table('commercial_visits', function (Blueprint $table): void {
            $table->foreignId('commercial_field_session_id')->nullable()->constrained()->nullOnDelete();
        });
        Schema::table('commercial_route_plans', function (Blueprint $table): void {
            $table->string('sector_label', 150)->nullable();
            $table->string('instructions', 500)->nullable();
        });
        foreach (['record-commercial-field' => ['Registrar actividad de campo', false, ['administrator', 'commercial']], 'supervise-commercial-field' => ['Supervisar actividad de campo', true, ['administrator']]] as $key => [$name, $sensitive, $profiles]) {
            $id = DB::table('permissions')->insertGetId([
                'key' => $key, 'name' => $name, 'category' => 'commercial', 'is_sensitive' => $sensitive,
                'created_at' => now(), 'updated_at' => now(),
            ]);
            foreach (DB::table('permission_profiles')->whereIn('slug', $profiles)->pluck('id') as $profile) {
                DB::table('permission_profile_permission')->insert(['permission_profile_id' => $profile, 'permission_id' => $id]);
            }
        }
    }

    public function down(): void
    {
        DB::table('permissions')->whereIn('key', ['record-commercial-field', 'supervise-commercial-field'])->delete();
        Schema::table('commercial_visits', fn (Blueprint $table) => $table->dropConstrainedForeignId('commercial_field_session_id'));
        Schema::dropIfExists('commercial_field_sessions');
        Schema::table('commercial_route_plans', fn (Blueprint $table) => $table->dropColumn(['sector_label', 'instructions']));
    }
};
