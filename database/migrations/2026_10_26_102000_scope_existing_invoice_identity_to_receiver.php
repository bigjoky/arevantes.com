<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::transaction(function (): void {
            DB::statement('DROP INDEX purchase_supplier_document_identity_unique');
            DB::statement('DROP INDEX expense_issuer_document_identity_unique');
            $this->indexes(true);
        });
    }

    private function indexes(bool $receiver): void
    {
        $scope = $receiver ? 'coalesce(receiver_company_id, 0), ' : '';
        DB::statement("CREATE UNIQUE INDEX purchase_supplier_document_identity_unique ON purchase_documents ({$scope}supplier_id, type, supplier_number) WHERE type IN ('supplier_invoice', 'supplier_credit_note') AND supplier_number IS NOT NULL AND supplier_number <> ''");
        DB::statement("CREATE UNIQUE INDEX expense_issuer_document_identity_unique ON expenses ({$scope}coalesce(issuer_country, 'ES'), upper(replace(replace(replace(issuer_tax_id, ' ', ''), '-', ''), '.', '')), document_number) WHERE issuer_tax_id IS NOT NULL AND issuer_tax_id <> '' AND document_number IS NOT NULL AND document_number <> ''");
    }

    public function down(): void
    {
        if (DB::getDriverName() === 'pgsql') {
            if (! DB::transactionLevel()) {
                throw new RuntimeException('Controlled transactional migration required.');
            }
            DB::statement('LOCK TABLE received_invoice_identities IN ACCESS EXCLUSIVE MODE');
        }
        if (DB::table('received_invoice_identities')->exists()) {
            throw new RuntimeException('Identity evidence exists; preserve receiver scope.');
        }
        DB::transaction(function (): void {
            DB::statement('DROP INDEX purchase_supplier_document_identity_unique');
            DB::statement('DROP INDEX expense_issuer_document_identity_unique');
            $this->indexes(false);
        });
    }
};
