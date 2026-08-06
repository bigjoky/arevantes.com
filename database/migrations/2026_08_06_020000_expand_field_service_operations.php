<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('field_service_profiles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->unique()->constrained()->cascadeOnDelete();
            $table->unsignedSmallInteger('daily_capacity_minutes')->default(480);
            $table->unsignedSmallInteger('max_travel_km')->default(150);
            $table->string('home_postal_code', 20)->nullable();
            $table->json('service_zones')->nullable();
            $table->json('skills')->nullable();
            $table->boolean('is_available')->default(true);
            $table->timestamps();
        });

        Schema::create('work_order_templates', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique();
            $table->string('kind', 30);
            $table->string('title');
            $table->unsignedSmallInteger('estimated_duration_minutes')->default(60);
            $table->json('required_skills')->nullable();
            $table->json('checklist');
            $table->json('allowed_transitions')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::table('work_orders', function (Blueprint $table) {
            $table->foreignId('work_order_template_id')->nullable()->after('installation_id')->constrained()->nullOnDelete();
            $table->unsignedSmallInteger('estimated_duration_minutes')->nullable()->after('kind');
            $table->json('required_skills')->nullable()->after('estimated_duration_minutes');
            $table->string('service_zone')->nullable()->after('required_skills');
        });

        Schema::create('work_order_reschedules', function (Blueprint $table) {
            $table->id();
            $table->foreignId('work_order_id')->constrained()->cascadeOnDelete();
            $table->foreignId('changed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('old_assigned_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('new_assigned_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->dateTime('old_scheduled_start')->nullable();
            $table->dateTime('old_scheduled_end')->nullable();
            $table->dateTime('new_scheduled_start')->nullable();
            $table->dateTime('new_scheduled_end')->nullable();
            $table->uuid('batch_uuid')->nullable()->index();
            $table->text('reason');
            $table->timestamps();
        });

        Schema::create('installation_assets', function (Blueprint $table) {
            $table->id();
            $table->foreignId('installation_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('serial_number_id')->nullable()->unique()->constrained('serial_numbers')->nullOnDelete();
            $table->foreignId('source_purchase_document_id')->nullable()->constrained('purchase_documents')->nullOnDelete();
            $table->foreignId('replaced_by_asset_id')->nullable()->constrained('installation_assets')->nullOnDelete();
            $table->string('asset_tag')->nullable()->unique();
            $table->string('name');
            $table->string('category', 50);
            $table->string('manufacturer')->nullable();
            $table->string('model')->nullable();
            $table->string('serial_number')->nullable();
            $table->string('status', 30)->default('active');
            $table->dateTime('commissioned_at')->nullable();
            $table->dateTime('removed_at')->nullable();
            $table->date('warranty_starts_on')->nullable();
            $table->date('warranty_ends_on')->nullable()->index();
            $table->json('specifications')->nullable();
            $table->timestamps();
            $table->index(['installation_id', 'status']);
        });

        Schema::create('asset_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('installation_asset_id')->constrained()->cascadeOnDelete();
            $table->foreignId('work_order_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('service_ticket_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('recorded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('type', 30);
            $table->string('summary');
            $table->text('details')->nullable();
            $table->dateTime('occurred_at');
            $table->timestamps();
            $table->index(['installation_asset_id', 'occurred_at']);
        });

        Schema::create('service_sla_policies', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('priority', 20);
            $table->string('customer_type', 30)->nullable();
            $table->unsignedInteger('first_response_minutes');
            $table->unsignedInteger('resolution_minutes');
            $table->unsignedInteger('escalation_minutes');
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->index(['priority', 'customer_type', 'is_active']);
        });

        Schema::table('service_tickets', function (Blueprint $table) {
            $table->foreignId('sla_policy_id')->nullable()->after('work_order_id')->constrained('service_sla_policies')->nullOnDelete();
            $table->dateTime('first_response_due_at')->nullable()->after('status')->index();
            $table->dateTime('resolution_due_at')->nullable()->after('first_response_due_at')->index();
            $table->dateTime('first_responded_at')->nullable()->after('resolution_due_at');
            $table->dateTime('escalated_at')->nullable()->after('first_responded_at');
            $table->unsignedTinyInteger('escalation_level')->default(0)->after('escalated_at');
            $table->unsignedSmallInteger('reopened_count')->default(0)->after('escalation_level');
            $table->unsignedTinyInteger('satisfaction_rating')->nullable()->after('reopened_count');
        });

        Schema::table('maintenance_plans', function (Blueprint $table) {
            $table->foreignId('work_order_template_id')->nullable()->after('installation_id')->constrained()->nullOnDelete();
            $table->foreignId('assigned_user_id')->nullable()->after('work_order_template_id')->constrained('users')->nullOnDelete();
            $table->unsignedSmallInteger('lead_days')->default(7)->after('interval_months');
            $table->boolean('generate_work_order')->default(true)->after('is_active');
            $table->dateTime('last_generated_at')->nullable()->after('generate_work_order');
        });
    }

    public function down(): void
    {
        Schema::table('maintenance_plans', function (Blueprint $table) {
            $table->dropConstrainedForeignId('work_order_template_id');
            $table->dropConstrainedForeignId('assigned_user_id');
            $table->dropColumn(['lead_days', 'generate_work_order', 'last_generated_at']);
        });
        Schema::table('service_tickets', function (Blueprint $table) {
            $table->dropConstrainedForeignId('sla_policy_id');
            $table->dropColumn(['first_response_due_at', 'resolution_due_at', 'first_responded_at', 'escalated_at', 'escalation_level', 'reopened_count', 'satisfaction_rating']);
        });
        Schema::dropIfExists('service_sla_policies');
        Schema::dropIfExists('asset_events');
        Schema::dropIfExists('installation_assets');
        Schema::dropIfExists('work_order_reschedules');
        Schema::table('work_orders', function (Blueprint $table) {
            $table->dropConstrainedForeignId('work_order_template_id');
            $table->dropColumn(['estimated_duration_minutes', 'required_skills', 'service_zone']);
        });
        Schema::dropIfExists('work_order_templates');
        Schema::dropIfExists('field_service_profiles');
    }
};
