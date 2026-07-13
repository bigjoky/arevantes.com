<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('maintenance_plans', function (Blueprint $table) {
            $table->id();
            $table->foreignId('installation_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->unsignedSmallInteger('interval_months');
            $table->date('starts_on');
            $table->date('next_due_on')->index();
            $table->date('last_completed_on')->nullable();
            $table->boolean('is_active')->default(true);
            $table->text('scope')->nullable();
            $table->timestamps();
        });

        Schema::create('service_tickets', function (Blueprint $table) {
            $table->id();
            $table->foreignId('installation_id')->constrained()->cascadeOnDelete();
            $table->foreignId('maintenance_plan_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('assigned_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('work_order_id')->nullable()->constrained()->nullOnDelete();
            $table->string('number')->unique();
            $table->string('kind', 20);
            $table->string('priority', 20)->default('normal');
            $table->string('status', 20)->default('open');
            $table->string('title');
            $table->text('description')->nullable();
            $table->text('resolution')->nullable();
            $table->timestamp('resolved_at')->nullable();
            $table->timestamps();
            $table->index(['status', 'priority', 'created_at']);
        });

        Schema::table('serial_numbers', function (Blueprint $table) {
            $table->date('warranty_starts_on')->nullable()->after('issued_at');
            $table->date('warranty_ends_on')->nullable()->after('warranty_starts_on')->index();
        });
    }

    public function down(): void
    {
        Schema::table('serial_numbers', fn (Blueprint $table) => $table->dropColumn(['warranty_starts_on', 'warranty_ends_on']));
        Schema::dropIfExists('service_tickets');
        Schema::dropIfExists('maintenance_plans');
    }
};
