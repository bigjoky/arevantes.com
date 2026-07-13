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
        Schema::create('installations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('person_id')->constrained('people')->cascadeOnDelete();
            $table->string('service_type');
            $table->string('status')->default('lead');
            $table->string('project_reference')->nullable()->index();
            $table->string('site_name')->nullable();
            $table->string('site_address_line_1');
            $table->string('site_address_line_2')->nullable();
            $table->string('site_city')->nullable();
            $table->string('site_province_state')->nullable();
            $table->string('site_postal_code')->nullable();
            $table->string('site_country')->nullable();
            $table->date('survey_date')->nullable();
            $table->date('scheduled_date')->nullable();
            $table->date('completed_date')->nullable();
            $table->decimal('estimated_budget', 12, 2)->nullable();
            $table->decimal('system_power_kw', 8, 2)->nullable();
            $table->unsignedInteger('panel_count')->nullable();
            $table->string('inverter_brand')->nullable();
            $table->decimal('battery_capacity_kwh', 8, 2)->nullable();
            $table->decimal('charger_power_kw', 8, 2)->nullable();
            $table->string('charger_connector_type')->nullable();
            $table->string('hvac_system_type')->nullable();
            $table->string('electrical_scope')->nullable();
            $table->text('site_notes')->nullable();
            $table->text('technical_notes')->nullable();
            $table->json('extra_details')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('installations');
    }
};
