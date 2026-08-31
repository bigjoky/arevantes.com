<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('web_vital_samples', function (Blueprint $table): void {
            $table->id();
            $table->string('route_name', 160);
            $table->string('metric', 8);
            $table->string('rating', 24);
            $table->decimal('value', 12, 4);
            $table->decimal('delta', 12, 4);
            $table->string('device', 12);
            $table->string('navigation_type', 30);
            $table->string('release', 100)->nullable();
            $table->timestampTz('occurred_at');
            $table->timestampTz('created_at')->useCurrent();

            $table->index(['metric', 'occurred_at'], 'web_vitals_metric_period_index');
            $table->index(['route_name', 'metric', 'occurred_at'], 'web_vitals_route_period_index');
            $table->index(['release', 'occurred_at'], 'web_vitals_release_period_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('web_vital_samples');
    }
};
