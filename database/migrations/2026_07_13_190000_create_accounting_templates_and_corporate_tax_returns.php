<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('accounting_templates', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('description')->nullable();
            $table->boolean('is_system')->default(false);
            $table->boolean('is_active')->default(true);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
        Schema::create('accounting_template_lines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('accounting_template_id')->constrained()->cascadeOnDelete();
            $table->string('account_code', 20);
            $table->string('side', 6);
            $table->decimal('percentage', 7, 4);
            $table->string('description')->nullable();
            $table->unsignedSmallInteger('position')->default(0);
            $table->timestamps();
            $table->foreign('account_code')->references('code')->on('ledger_accounts')->restrictOnDelete();
        });
        Schema::create('corporate_tax_returns', function (Blueprint $table) {
            $table->id();
            $table->foreignId('fiscal_year_id')->unique()->constrained()->restrictOnDelete();
            $table->string('regime', 30)->default('general');
            $table->decimal('prior_year_revenue', 15, 2)->default(0);
            $table->decimal('accounting_result', 15, 2)->default(0);
            $table->decimal('permanent_positive', 15, 2)->default(0);
            $table->decimal('permanent_negative', 15, 2)->default(0);
            $table->decimal('temporary_positive', 15, 2)->default(0);
            $table->decimal('temporary_negative', 15, 2)->default(0);
            $table->decimal('loss_carryforward', 15, 2)->default(0);
            $table->decimal('taxable_base', 15, 2)->default(0);
            $table->decimal('gross_tax', 15, 2)->default(0);
            $table->decimal('deductions', 15, 2)->default(0);
            $table->decimal('withholdings', 15, 2)->default(0);
            $table->decimal('instalment_payments', 15, 2)->default(0);
            $table->decimal('net_tax', 15, 2)->default(0);
            $table->string('status', 20)->default('draft');
            $table->json('calculation');
            $table->foreignId('prepared_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('prepared_at')->nullable();
            $table->string('filing_reference')->nullable();
            $table->date('filed_on')->nullable();
            $table->foreignId('filed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('receipt_path')->nullable();
            $table->timestamps();
        });

        $now = now();
        $templates = [
            ['Bank fees', 'Record fees charged by the bank.', [['626', 'debit'], ['572', 'credit']]],
            ['Capital contribution', 'Cash contribution from shareholders.', [['572', 'debit'], ['118', 'credit']]],
            ['Customer advance', 'Advance collected before invoicing.', [['572', 'debit'], ['438', 'credit']]],
            ['Supplier advance', 'Advance paid before receiving an invoice.', [['407', 'debit'], ['572', 'credit']]],
            ['Short-term loan received', 'Receipt of short-term financing.', [['572', 'debit'], ['520', 'credit']]],
        ];
        foreach ($templates as [$name, $description, $lines]) {
            if (collect($lines)->pluck(0)->every(fn ($code) => DB::table('ledger_accounts')->where('code', $code)->exists())) {
                $id = DB::table('accounting_templates')->insertGetId(['name' => $name, 'description' => $description, 'is_system' => true, 'is_active' => true, 'created_at' => $now, 'updated_at' => $now]);
                foreach ($lines as $position => [$code, $side]) {
                    DB::table('accounting_template_lines')->insert(['accounting_template_id' => $id, 'account_code' => $code, 'side' => $side, 'percentage' => 100, 'position' => $position, 'created_at' => $now, 'updated_at' => $now]);
                }
            }
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('corporate_tax_returns');
        Schema::dropIfExists('accounting_template_lines');
        Schema::dropIfExists('accounting_templates');
    }
};
