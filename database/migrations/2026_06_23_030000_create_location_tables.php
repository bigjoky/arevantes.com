<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('countries', function (Blueprint $table) {
            $table->id();
            $table->string('code', 2)->unique();
            $table->string('name');
            $table->timestamps();
        });

        Schema::create('provinces', function (Blueprint $table) {
            $table->id();
            $table->foreignId('country_id')->constrained()->cascadeOnDelete();
            $table->string('code')->nullable();
            $table->string('name');
            $table->timestamps();

            $table->unique(['country_id', 'name']);
        });

        Schema::create('municipalities', function (Blueprint $table) {
            $table->id();
            $table->foreignId('province_id')->constrained()->cascadeOnDelete();
            $table->string('code')->nullable();
            $table->string('name');
            $table->timestamps();

            $table->unique(['province_id', 'name']);
        });

        Schema::create('localities', function (Blueprint $table) {
            $table->id();
            $table->foreignId('municipality_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->timestamps();

            $table->unique(['municipality_id', 'name']);
        });

        Schema::create('postal_codes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('country_id')->constrained()->cascadeOnDelete();
            $table->string('code');
            $table->timestamps();

            $table->unique(['country_id', 'code']);
        });

        Schema::create('locality_postal_code', function (Blueprint $table) {
            $table->foreignId('locality_id')->constrained()->cascadeOnDelete();
            $table->foreignId('postal_code_id')->constrained()->cascadeOnDelete();
            $table->primary(['locality_id', 'postal_code_id']);
        });

        Schema::table('people', function (Blueprint $table) {
            $table->string('locality')->nullable()->after('city');
        });

        Schema::table('installations', function (Blueprint $table) {
            $table->string('site_locality')->nullable()->after('site_city');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('installations', function (Blueprint $table) {
            $table->dropColumn('site_locality');
        });

        Schema::table('people', function (Blueprint $table) {
            $table->dropColumn('locality');
        });

        Schema::dropIfExists('locality_postal_code');
        Schema::dropIfExists('postal_codes');
        Schema::dropIfExists('localities');
        Schema::dropIfExists('municipalities');
        Schema::dropIfExists('provinces');
        Schema::dropIfExists('countries');
    }
};
