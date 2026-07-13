<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('installations', function (Blueprint $table) {
            $table->string('site_geocoding_provider')->nullable()->after('site_longitude');
            $table->string('site_geocoding_place_id')->nullable()->after('site_geocoding_provider');
            $table->string('site_geocoding_osm_type')->nullable()->after('site_geocoding_place_id');
            $table->string('site_geocoding_osm_id')->nullable()->after('site_geocoding_osm_type');
            $table->text('site_geocoding_display_name')->nullable()->after('site_geocoding_osm_id');
            $table->json('site_geocoding_data')->nullable()->after('site_geocoding_display_name');
            $table->timestamp('site_geocoded_at')->nullable()->after('site_geocoding_data');
        });
    }

    public function down(): void
    {
        Schema::table('installations', function (Blueprint $table) {
            $table->dropColumn([
                'site_geocoding_provider',
                'site_geocoding_place_id',
                'site_geocoding_osm_type',
                'site_geocoding_osm_id',
                'site_geocoding_display_name',
                'site_geocoding_data',
                'site_geocoded_at',
            ]);
        });
    }
};
