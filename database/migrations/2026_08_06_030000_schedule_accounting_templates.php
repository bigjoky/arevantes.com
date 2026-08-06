<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('accounting_templates', function (Blueprint $table) {
            $table->decimal('recurring_amount', 14, 2)->nullable()->after('is_active');
            $table->string('recurrence', 20)->nullable()->after('recurring_amount');
            $table->date('next_run_on')->nullable()->after('recurrence');
            $table->date('end_on')->nullable()->after('next_run_on');
            $table->unsignedSmallInteger('reversal_days')->nullable()->after('end_on');
            $table->date('last_run_on')->nullable()->after('reversal_days');
        });

        Schema::create('accounting_recurring_runs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('accounting_template_id')->constrained()->cascadeOnDelete();
            $table->date('scheduled_on');
            $table->decimal('amount', 14, 2);
            $table->foreignId('journal_entry_id')->nullable()->constrained()->nullOnDelete();
            $table->date('reversal_due_on')->nullable();
            $table->foreignId('reversal_entry_id')->nullable()->constrained('journal_entries')->nullOnDelete();
            $table->string('status', 30)->default('pending');
            $table->text('failure_message')->nullable();
            $table->timestamps();

            $table->unique(['accounting_template_id', 'scheduled_on'], 'accounting_recurring_run_unique');
            $table->index(['status', 'reversal_due_on']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('accounting_recurring_runs');
        Schema::table('accounting_templates', function (Blueprint $table) {
            $table->dropColumn(['recurring_amount', 'recurrence', 'next_run_on', 'end_on', 'reversal_days', 'last_run_on']);
        });
    }
};
