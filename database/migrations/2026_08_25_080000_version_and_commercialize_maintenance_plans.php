<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('maintenance_plans', function (Blueprint $table): void {
            $table->unsignedInteger('version')->default(1)->after('name');
            $table->string('service_level', 20)->default('standard')->after('version');
            $table->string('coverage_type', 24)->default('preventive')->after('service_level');
            $table->unsignedSmallInteger('included_visits')->nullable()->after('coverage_type');
            $table->unsignedInteger('response_minutes')->nullable()->after('included_visits');
            $table->unsignedInteger('resolution_minutes')->nullable()->after('response_minutes');
            $table->date('contract_ends_on')->nullable()->after('starts_on')->index();
            $table->unsignedSmallInteger('renewal_notice_days')->default(30)->after('contract_ends_on');
            $table->unsignedSmallInteger('billing_frequency_months')->nullable()->after('renewal_notice_days');
            $table->decimal('billing_amount', 14, 2)->nullable()->after('billing_frequency_months');
            $table->date('next_billing_on')->nullable()->after('billing_amount');
            $table->foreignId('origin_opportunity_id')->nullable()->after('next_billing_on')->constrained('opportunities')->nullOnDelete();
            $table->foreignId('renewal_opportunity_id')->nullable()->after('origin_opportunity_id')->constrained('opportunities')->nullOnDelete();
            $table->foreignId('last_versioned_by')->nullable()->after('renewal_opportunity_id')->constrained('users')->nullOnDelete();
        });

        Schema::create('maintenance_plan_versions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('maintenance_plan_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('version');
            $table->json('snapshot');
            $table->text('change_note');
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->unique(['maintenance_plan_id', 'version']);
        });

        Schema::create('maintenance_plan_assets', function (Blueprint $table): void {
            $table->foreignId('maintenance_plan_id')->constrained()->cascadeOnDelete();
            $table->foreignId('installation_asset_id')->constrained()->cascadeOnDelete();
            $table->timestamps();
            $table->primary(['maintenance_plan_id', 'installation_asset_id'], 'maintenance_plan_assets_primary');
        });

        Schema::table('financial_forecast_items', function (Blueprint $table): void {
            $table->foreignId('maintenance_plan_id')->nullable()->unique()->after('installation_id')->constrained()->nullOnDelete();
        });
        Schema::table('service_tickets', function (Blueprint $table): void {
            $table->unsignedInteger('maintenance_plan_version')->nullable()->after('maintenance_plan_id');
        });

        DB::table('maintenance_plans')->orderBy('id')->eachById(function ($plan): void {
            DB::table('maintenance_plan_versions')->insert([
                'maintenance_plan_id' => $plan->id,
                'version' => 1,
                'snapshot' => json_encode((array) $plan, JSON_THROW_ON_ERROR),
                'change_note' => 'Versión inicial migrada desde el plan operativo existente.',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        });
    }

    public function down(): void
    {
        Schema::table('service_tickets', fn (Blueprint $table) => $table->dropColumn('maintenance_plan_version'));
        Schema::table('financial_forecast_items', fn (Blueprint $table) => $table->dropConstrainedForeignId('maintenance_plan_id'));
        Schema::dropIfExists('maintenance_plan_assets');
        Schema::dropIfExists('maintenance_plan_versions');
        Schema::table('maintenance_plans', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('origin_opportunity_id');
            $table->dropConstrainedForeignId('renewal_opportunity_id');
            $table->dropConstrainedForeignId('last_versioned_by');
            $table->dropColumn(['version', 'service_level', 'coverage_type', 'included_visits', 'response_minutes', 'resolution_minutes', 'contract_ends_on', 'renewal_notice_days', 'billing_frequency_months', 'billing_amount', 'next_billing_on']);
        });
    }
};
