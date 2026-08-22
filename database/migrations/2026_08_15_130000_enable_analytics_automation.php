<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('analytics_alerts', function (Blueprint $table) {
            $table->string('area', 20)->nullable()->after('metric_key');
            $table->json('filters')->nullable()->after('area');
            $table->string('channel', 20)->default('database')->after('threshold');
            $table->decimal('last_value', 16, 2)->nullable()->after('enabled');
            $table->boolean('is_triggered')->default(false)->after('last_value');
            $table->timestamp('last_evaluated_at')->nullable()->after('is_triggered');
        });

        Schema::table('analytics_report_subscriptions', function (Blueprint $table) {
            $table->dropUnique(['user_id', 'area']);
            $table->json('filters')->nullable()->after('format');
            $table->string('timezone', 50)->default('Europe/Madrid')->after('filters');
            $table->timestamp('next_send_at')->nullable()->after('last_sent_at');
            $table->unique(['user_id', 'area', 'frequency', 'format'], 'analytics_subscription_delivery_unique');
            $table->index(['enabled', 'next_send_at'], 'analytics_subscription_due_idx');
        });
    }

    public function down(): void
    {
        Schema::table('analytics_report_subscriptions', function (Blueprint $table) {
            $table->dropIndex('analytics_subscription_due_idx');
            $table->dropUnique('analytics_subscription_delivery_unique');
            $table->dropColumn(['filters', 'timezone', 'next_send_at']);
            $table->unique(['user_id', 'area']);
        });

        Schema::table('analytics_alerts', function (Blueprint $table) {
            $table->dropColumn(['area', 'filters', 'channel', 'last_value', 'is_triggered', 'last_evaluated_at']);
        });
    }
};
