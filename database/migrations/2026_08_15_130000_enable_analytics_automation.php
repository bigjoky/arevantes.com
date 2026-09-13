<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $missingAlertColumns = collect([
            'area',
            'filters',
            'channel',
            'last_value',
            'is_triggered',
            'last_evaluated_at',
        ])->reject(fn (string $column): bool => Schema::hasColumn('analytics_alerts', $column));

        Schema::table('analytics_alerts', function (Blueprint $table) use ($missingAlertColumns): void {
            if ($missingAlertColumns->contains('area')) {
                $table->string('area', 20)->nullable()->after('metric_key');
            }
            if ($missingAlertColumns->contains('filters')) {
                $table->json('filters')->nullable()->after('area');
            }
            if ($missingAlertColumns->contains('channel')) {
                $table->string('channel', 20)->default('database')->after('threshold');
            }
            if ($missingAlertColumns->contains('last_value')) {
                $table->decimal('last_value', 16, 2)->nullable()->after('enabled');
            }
            if ($missingAlertColumns->contains('is_triggered')) {
                $table->boolean('is_triggered')->default(false)->after('last_value');
            }
            if ($missingAlertColumns->contains('last_evaluated_at')) {
                $table->timestamp('last_evaluated_at')->nullable()->after('is_triggered');
            }
        });

        if (! Schema::hasIndex('analytics_report_subscriptions', ['user_id'])) {
            Schema::table('analytics_report_subscriptions', function (Blueprint $table): void {
                $table->index('user_id');
            });
        }

        if (Schema::hasIndex('analytics_report_subscriptions', ['user_id', 'area'], 'unique')) {
            Schema::table('analytics_report_subscriptions', function (Blueprint $table): void {
                $table->dropUnique(['user_id', 'area']);
            });
        }

        $missingSubscriptionColumns = collect([
            'filters',
            'timezone',
            'next_send_at',
        ])->reject(fn (string $column): bool => Schema::hasColumn('analytics_report_subscriptions', $column));

        Schema::table('analytics_report_subscriptions', function (Blueprint $table) use ($missingSubscriptionColumns): void {
            if ($missingSubscriptionColumns->contains('filters')) {
                $table->json('filters')->nullable()->after('format');
            }
            if ($missingSubscriptionColumns->contains('timezone')) {
                $table->string('timezone', 50)->default('Europe/Madrid')->after('filters');
            }
            if ($missingSubscriptionColumns->contains('next_send_at')) {
                $table->timestamp('next_send_at')->nullable()->after('last_sent_at');
            }
        });

        if (! Schema::hasIndex('analytics_report_subscriptions', ['user_id', 'area', 'frequency', 'format'], 'unique')) {
            Schema::table('analytics_report_subscriptions', function (Blueprint $table): void {
                $table->unique(['user_id', 'area', 'frequency', 'format'], 'analytics_subscription_delivery_unique');
            });
        }

        if (! Schema::hasIndex('analytics_report_subscriptions', ['enabled', 'next_send_at'])) {
            Schema::table('analytics_report_subscriptions', function (Blueprint $table): void {
                $table->index(['enabled', 'next_send_at'], 'analytics_subscription_due_idx');
            });
        }
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
