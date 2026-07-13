<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ledger_accounts', function (Blueprint $table) {
            $table->id();
            $table->string('code', 20)->unique();
            $table->string('name');
            $table->string('type', 20);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('journal_entries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('fiscal_year_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->date('entry_date')->index();
            $table->string('number', 30)->unique();
            $table->string('description');
            $table->nullableMorphs('source');
            $table->string('event', 30)->default('document');
            $table->string('status', 20)->default('posted');
            $table->timestamp('posted_at')->nullable();
            $table->timestamps();
            $table->unique(['source_type', 'source_id', 'event'], 'journal_source_event_unique');
        });

        Schema::create('journal_lines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('journal_entry_id')->constrained()->cascadeOnDelete();
            $table->foreignId('ledger_account_id')->constrained()->restrictOnDelete();
            $table->string('description')->nullable();
            $table->decimal('debit', 14, 2)->default(0);
            $table->decimal('credit', 14, 2)->default(0);
            $table->timestamps();
        });

        $now = now();
        DB::table('ledger_accounts')->insert(collect([
            ['430', 'Customers', 'asset'], ['400', 'Suppliers', 'liability'], ['410', 'Trade creditors', 'liability'],
            ['472', 'Input VAT', 'asset'], ['477', 'Output VAT', 'liability'], ['570', 'Cash', 'asset'],
            ['572', 'Banks', 'asset'], ['600', 'Purchases', 'expense'], ['621', 'Leases and rentals', 'expense'],
            ['622', 'Repairs and maintenance', 'expense'], ['623', 'Professional services', 'expense'],
            ['628', 'Utilities', 'expense'], ['629', 'Other services', 'expense'], ['700', 'Sales', 'income'],
            ['705', 'Services', 'income'],
        ])->map(fn ($account) => ['code' => $account[0], 'name' => $account[1], 'type' => $account[2], 'is_active' => true, 'created_at' => $now, 'updated_at' => $now])->all());
    }

    public function down(): void
    {
        Schema::dropIfExists('journal_lines');
        Schema::dropIfExists('journal_entries');
        Schema::dropIfExists('ledger_accounts');
    }
};
