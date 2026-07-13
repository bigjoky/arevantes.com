<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('remote_stations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('installation_id')->nullable()->unique()->constrained()->nullOnDelete();
            $table->string('provider', 20)->index();
            $table->string('remote_identifier');
            $table->string('name')->nullable();
            $table->string('address')->nullable();
            $table->string('connection_status')->nullable();
            $table->json('provider_data')->nullable();
            $table->timestamp('discovered_at');
            $table->timestamp('last_seen_at');
            $table->timestamps();

            $table->unique(['provider', 'remote_identifier']);
        });

        $now = now();

        foreach (DB::table('installations')->whereNotNull('deye_station_id')->get() as $installation) {
            DB::table('remote_stations')->insert([
                'installation_id' => $installation->id,
                'provider' => 'deye',
                'remote_identifier' => $installation->deye_station_id,
                'name' => $installation->deye_station_name,
                'connection_status' => $installation->deye_status,
                'provider_data' => $installation->deye_data,
                'discovered_at' => $installation->deye_synced_at ?? $now,
                'last_seen_at' => $installation->deye_synced_at ?? $now,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }

        foreach (DB::table('installations')->whereNotNull('huawei_station_code')->get() as $installation) {
            DB::table('remote_stations')->updateOrInsert(
                ['provider' => 'huawei', 'remote_identifier' => $installation->huawei_station_code],
                [
                    'installation_id' => $installation->id,
                    'name' => $installation->huawei_station_name,
                    'connection_status' => $installation->huawei_status,
                    'provider_data' => $installation->huawei_data,
                    'discovered_at' => $installation->huawei_synced_at ?? $now,
                    'last_seen_at' => $installation->huawei_synced_at ?? $now,
                    'created_at' => $now,
                    'updated_at' => $now,
                ],
            );
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('remote_stations');
    }
};
