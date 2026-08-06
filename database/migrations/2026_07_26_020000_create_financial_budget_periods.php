<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('financial_budget_periods', function (Blueprint $table) {
            $table->id();
            $table->unsignedSmallInteger('year')->unique();
            $table->string('status', 20)->default('draft');
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('approved_at')->nullable();
            $table->text('approval_notes')->nullable();
            $table->foreignId('reopened_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('reopened_at')->nullable();
            $table->text('reopen_reason')->nullable();
            $table->timestamps();
        });

        $now = now();
        DB::table('financial_budgets')
            ->select('year')
            ->distinct()
            ->orderBy('year')
            ->pluck('year')
            ->each(fn ($year) => DB::table('financial_budget_periods')->insert([
                'year' => $year,
                'status' => 'draft',
                'created_at' => $now,
                'updated_at' => $now,
            ]));
    }

    public function down(): void
    {
        Schema::dropIfExists('financial_budget_periods');
    }
};
