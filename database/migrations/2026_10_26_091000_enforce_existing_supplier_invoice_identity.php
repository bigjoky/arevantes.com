<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // Enforce the existing validators, without deleting, merging or renumbering history.
        // Existing duplicates deliberately block migration and require authorized review.
        DB::transaction(function (): void {
            DB::statement("CREATE UNIQUE INDEX purchase_supplier_document_identity_unique ON purchase_documents (supplier_id, type, supplier_number) WHERE type IN ('supplier_invoice', 'supplier_credit_note') AND supplier_number IS NOT NULL AND supplier_number <> ''");
            DB::statement("CREATE UNIQUE INDEX expense_issuer_document_identity_unique ON expenses (coalesce(issuer_country, 'ES'), upper(replace(replace(replace(issuer_tax_id, ' ', ''), '-', ''), '.', '')), document_number) WHERE issuer_tax_id IS NOT NULL AND issuer_tax_id <> '' AND document_number IS NOT NULL AND document_number <> ''");
        });
    }

    public function down(): void
    {
        DB::statement('DROP INDEX IF EXISTS purchase_supplier_document_identity_unique');
        DB::statement('DROP INDEX IF EXISTS expense_issuer_document_identity_unique');
    }
};
