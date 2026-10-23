<?php

use App\Support\Database\BackendTableSecurity;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    public function up(): void
    {
        foreach (['fiscal_entities', 'expense_fiscal_classifications', 'company_census_versions', 'purchase_fiscal_classifications', 'purchase_receipt_requests', 'company_documents', 'company_document_versions'] as $table) {
            BackendTableSecurity::restrict($table);
        }
    }

    public function down(): void
    {
        // A rollback must not grant public access to private backend data.
    }
};
