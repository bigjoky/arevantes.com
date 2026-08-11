<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('crm_settings', function (Blueprint $table) {
            $table->id();
            $table->unsignedSmallInteger('stale_after_days')->default(14);
            $table->unsignedSmallInteger('customer_contactable_weight')->default(20);
            $table->unsignedSmallInteger('source_identified_weight')->default(10);
            $table->unsignedSmallInteger('owner_assigned_weight')->default(15);
            $table->unsignedSmallInteger('expected_close_set_weight')->default(15);
            $table->unsignedSmallInteger('next_action_scheduled_weight')->default(20);
            $table->unsignedSmallInteger('estimated_value_set_weight')->default(10);
            $table->unsignedSmallInteger('advanced_stage_weight')->default(10);
            $table->timestamps();
        });

        DB::table('crm_settings')->insert([
            'id' => 1,
            'stale_after_days' => 14,
            'customer_contactable_weight' => 20,
            'source_identified_weight' => 10,
            'owner_assigned_weight' => 15,
            'expected_close_set_weight' => 15,
            'next_action_scheduled_weight' => 20,
            'estimated_value_set_weight' => 10,
            'advanced_stage_weight' => 10,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('crm_settings');
    }
};
