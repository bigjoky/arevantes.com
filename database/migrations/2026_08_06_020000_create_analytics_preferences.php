<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('analytics_alerts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('metric_key', 60);
            $table->string('operator', 5);
            $table->decimal('threshold', 16, 2);
            $table->boolean('enabled')->default(true);
            $table->timestamp('last_triggered_at')->nullable();
            $table->timestamps();
            $table->index(['user_id', 'enabled']);
        });

        Schema::create('analytics_report_subscriptions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('area', 20);
            $table->string('frequency', 20);
            $table->string('format', 10);
            $table->boolean('enabled')->default(true);
            $table->timestamp('last_sent_at')->nullable();
            $table->timestamps();
            $table->unique(['user_id', 'area']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('analytics_report_subscriptions');
        Schema::dropIfExists('analytics_alerts');
    }
};
