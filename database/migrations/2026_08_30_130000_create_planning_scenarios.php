<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('planning_scenarios', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->unsignedInteger('version')->default(1);
            $table->string('status')->default('frozen')->index();
            $table->date('horizon_starts_on');
            $table->date('horizon_ends_on');
            $table->json('assumptions');
            $table->json('opportunity_snapshot');
            $table->json('result_snapshot');
            $table->char('fingerprint', 64);
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('approved_at')->nullable();
            $table->timestamps();

            $table->unique(['name', 'version']);
            $table->index(['horizon_starts_on', 'horizon_ends_on']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('planning_scenarios');
    }
};
