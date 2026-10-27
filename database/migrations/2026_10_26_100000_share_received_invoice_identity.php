<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        foreach (['purchase_documents', 'expenses'] as $table) {
            // Native additive ALTER preserves SQLite expression indexes; no table reconstruction.
            DB::statement("ALTER TABLE $table ADD COLUMN receiver_company_id BIGINT REFERENCES company_profiles(id) ON DELETE RESTRICT");
        }
        Schema::create('received_invoice_identities', function (Blueprint $t): void {
            $t->id();
            $t->foreignId('receiver_company_id')->constrained('company_profiles')->restrictOnDelete();
            $t->string('issuer_country', 2);
            $t->string('issuer_tax_id', 64);
            $t->string('document_kind', 32);
            $t->string('external_number');
            $t->foreignId('purchase_document_id')->nullable()->index()->constrained('purchase_documents')->restrictOnDelete();
            $t->foreignId('expense_id')->nullable()->index()->constrained('expenses')->restrictOnDelete();
            $t->timestampTz('created_at')->useCurrent();
            $t->unique(['receiver_company_id', 'issuer_country', 'issuer_tax_id', 'document_kind', 'external_number'], 'received_invoice_fiscal_identity_unique');
        });
        if (DB::getDriverName() === 'pgsql') {
            DB::statement('ALTER TABLE received_invoice_identities ADD CONSTRAINT received_invoice_one_owner CHECK ((purchase_document_id IS NULL) <> (expense_id IS NULL))');
        }
    }

    public function down(): void
    {
        if (DB::getDriverName() === 'pgsql') {
            DB::statement('LOCK TABLE received_invoice_identities IN ACCESS EXCLUSIVE MODE');
        }
        if (DB::table('received_invoice_identities')->exists()) {
            throw new RuntimeException('Received invoice identity evidence exists; preserve it.');
        }
        Schema::dropIfExists('received_invoice_identities');
        foreach (['purchase_documents', 'expenses'] as $table) {
            DB::statement("ALTER TABLE $table DROP COLUMN receiver_company_id");
        }
    }
};
