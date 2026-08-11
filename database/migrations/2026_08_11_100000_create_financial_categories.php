<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('financial_categories', function (Blueprint $table) {
            $table->id();
            $table->string('code', 60)->unique();
            $table->string('name');
            $table->enum('reporting_group', ['revenue', 'costs', 'cash_in', 'cash_out']);
            $table->boolean('is_system')->default(false);
            $table->boolean('is_active')->default(true);
            $table->foreignId('created_by')->nullable()->index()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->index(['reporting_group', 'is_active']);
        });

        $now = now();
        DB::table('financial_categories')->insert([
            ['code' => 'sales_revenue', 'name' => 'Sales revenue', 'reporting_group' => 'revenue', 'is_system' => true, 'is_active' => true, 'created_at' => $now, 'updated_at' => $now],
            ['code' => 'other_revenue', 'name' => 'Other revenue', 'reporting_group' => 'revenue', 'is_system' => true, 'is_active' => true, 'created_at' => $now, 'updated_at' => $now],
            ['code' => 'operating_costs', 'name' => 'Operating costs', 'reporting_group' => 'costs', 'is_system' => true, 'is_active' => true, 'created_at' => $now, 'updated_at' => $now],
            ['code' => 'payroll_costs', 'name' => 'Payroll costs', 'reporting_group' => 'costs', 'is_system' => true, 'is_active' => true, 'created_at' => $now, 'updated_at' => $now],
            ['code' => 'tax_costs', 'name' => 'Taxes and duties', 'reporting_group' => 'costs', 'is_system' => true, 'is_active' => true, 'created_at' => $now, 'updated_at' => $now],
            ['code' => 'customer_collections', 'name' => 'Customer collections', 'reporting_group' => 'cash_in', 'is_system' => true, 'is_active' => true, 'created_at' => $now, 'updated_at' => $now],
            ['code' => 'financing_inflows', 'name' => 'Financing inflows', 'reporting_group' => 'cash_in', 'is_system' => true, 'is_active' => true, 'created_at' => $now, 'updated_at' => $now],
            ['code' => 'supplier_payments', 'name' => 'Supplier payments', 'reporting_group' => 'cash_out', 'is_system' => true, 'is_active' => true, 'created_at' => $now, 'updated_at' => $now],
            ['code' => 'payroll_payments', 'name' => 'Payroll payments', 'reporting_group' => 'cash_out', 'is_system' => true, 'is_active' => true, 'created_at' => $now, 'updated_at' => $now],
            ['code' => 'tax_payments', 'name' => 'Tax payments', 'reporting_group' => 'cash_out', 'is_system' => true, 'is_active' => true, 'created_at' => $now, 'updated_at' => $now],
            ['code' => 'debt_service', 'name' => 'Debt service', 'reporting_group' => 'cash_out', 'is_system' => true, 'is_active' => true, 'created_at' => $now, 'updated_at' => $now],
            ['code' => 'other_cash_out', 'name' => 'Other cash outflows', 'reporting_group' => 'cash_out', 'is_system' => true, 'is_active' => true, 'created_at' => $now, 'updated_at' => $now],
        ]);

        foreach (['financial_budgets', 'financial_forecast_items', 'ledger_accounts'] as $tableName) {
            Schema::table($tableName, function (Blueprint $table) {
                $table->foreignId('financial_category_id')->nullable()->index()->constrained('financial_categories')->restrictOnDelete();
            });
        }

        $categoryIds = DB::table('financial_categories')->pluck('id', 'code');
        $budgetDefaults = [
            'revenue' => $categoryIds['sales_revenue'],
            'costs' => $categoryIds['operating_costs'],
            'cash_in' => $categoryIds['customer_collections'],
            'cash_out' => $categoryIds['supplier_payments'],
        ];
        foreach ($budgetDefaults as $group => $categoryId) {
            DB::table('financial_budgets')->where('category', $group)->update(['financial_category_id' => $categoryId]);
        }
        DB::table('financial_forecast_items')->orderBy('id')->each(function (object $item) use ($categoryIds) {
            $label = strtolower((string) $item->category.' '.(string) $item->description);
            $code = $item->direction === 'in'
                ? (str_contains($label, 'loan') || str_contains($label, 'financ') ? 'financing_inflows' : 'customer_collections')
                : (str_contains($label, 'payroll') || str_contains($label, 'salary') || str_contains($label, 'nómina') ? 'payroll_payments'
                    : (str_contains($label, 'tax') || str_contains($label, 'vat') || str_contains($label, 'iva') ? 'tax_payments'
                        : (str_contains($label, 'loan') || str_contains($label, 'debt') || str_contains($label, 'credit') ? 'debt_service' : 'other_cash_out')));
            DB::table('financial_forecast_items')->where('id', $item->id)->update(['financial_category_id' => $categoryIds[$code]]);
        });
        DB::table('ledger_accounts')->where('type', 'income')->update(['financial_category_id' => $categoryIds['other_revenue']]);
        DB::table('ledger_accounts')->where('type', 'income')->where('code', 'like', '70%')->update(['financial_category_id' => $categoryIds['sales_revenue']]);
        DB::table('ledger_accounts')->where('type', 'expense')->update(['financial_category_id' => $categoryIds['operating_costs']]);
        DB::table('ledger_accounts')->where('type', 'expense')->where('code', 'like', '64%')->update(['financial_category_id' => $categoryIds['payroll_costs']]);
        DB::table('ledger_accounts')->where('type', 'expense')->where('code', 'like', '63%')->update(['financial_category_id' => $categoryIds['tax_costs']]);

        Schema::table('financial_budgets', function (Blueprint $table) {
            $table->dropUnique(['year', 'month', 'category']);
            $table->unique(['year', 'month', 'category', 'financial_category_id'], 'financial_budgets_period_taxonomy_unique');
        });
    }

    public function down(): void
    {
        Schema::table('financial_budgets', function (Blueprint $table) {
            $table->dropUnique('financial_budgets_period_taxonomy_unique');
            $table->unique(['year', 'month', 'category']);
        });
        foreach (['ledger_accounts', 'financial_forecast_items', 'financial_budgets'] as $tableName) {
            Schema::table($tableName, fn (Blueprint $table) => $table->dropConstrainedForeignId('financial_category_id'));
        }
        Schema::dropIfExists('financial_categories');
    }
};
