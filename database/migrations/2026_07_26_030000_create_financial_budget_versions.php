<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('financial_budget_periods', 'current_revision')) {
            Schema::table('financial_budget_periods', function (Blueprint $table) {
                $table->unsignedSmallInteger('current_revision')->default(0)->after('status');
            });
        }

        if (! Schema::hasTable('financial_budget_versions')) {
            Schema::create('financial_budget_versions', function (Blueprint $table) {
                $table->id();
                $table->foreignId('financial_budget_period_id')->constrained()->cascadeOnDelete();
                $table->unsignedSmallInteger('revision');
                $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamp('approved_at');
                $table->text('approval_notes')->nullable();
                $table->json('values');
                $table->timestamps();

                $table->unique(['financial_budget_period_id', 'revision'], 'budget_period_revision_unique');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('financial_budget_versions');

        if (Schema::hasColumn('financial_budget_periods', 'current_revision')) {
            Schema::table('financial_budget_periods', function (Blueprint $table) {
                $table->dropColumn('current_revision');
            });
        }
    }
};
