<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        if (DB::getDriverName() !== 'pgsql') {
            foreach (['UPDATE', 'DELETE'] as $operation) {
                DB::unprepared('CREATE TRIGGER received_identity_preserve_'.strtolower($operation)." BEFORE $operation ON received_invoice_identities BEGIN SELECT RAISE(ABORT, 'received_invoice_identity_immutable'); END");
            }

            return; // Economic service checks are shared; raw economic guards/concurrency require PostgreSQL.
        }
        DB::unprepared(<<<'SQL'
CREATE OR REPLACE FUNCTION assert_received_invoice_effect(source_kind text, source_id bigint) RETURNS void LANGUAGE plpgsql AS $$
DECLARE c record;
BEGIN
    IF source_kind = 'purchase' THEN PERFORM 1 FROM purchase_documents WHERE id = assert_received_invoice_effect.source_id FOR UPDATE;
    ELSE PERFORM 1 FROM expenses WHERE id = assert_received_invoice_effect.source_id FOR UPDATE; END IF;
    SELECT * INTO c FROM received_invoice_candidates v WHERE v.source_kind = assert_received_invoice_effect.source_kind AND v.source_id = assert_received_invoice_effect.source_id;
    IF NOT FOUND THEN
        IF (source_kind = 'purchase' AND NOT EXISTS (SELECT 1 FROM purchase_documents WHERE id = assert_received_invoice_effect.source_id)) OR
           (source_kind = 'expense' AND NOT EXISTS (SELECT 1 FROM expenses WHERE id = assert_received_invoice_effect.source_id)) THEN
            RAISE EXCEPTION 'received_invoice_identity_incomplete: Economic owner does not exist' USING ERRCODE = '23503';
        END IF;
        RETURN;
    END IF;
    IF c.issuer_tax_id = '' OR c.external_number = '' OR c.receiver_company_id IS NULL THEN
        RAISE EXCEPTION 'received_invoice_identity_incomplete: Review incomplete fiscal identity before recording economic effects' USING ERRCODE = '23514';
    END IF;
    IF c.receiver_company_id <> (SELECT min(id) FROM company_profiles) THEN
        RAISE EXCEPTION 'received_invoice_identity_incomplete: Recipient differs from the configured operating company' USING ERRCODE = '23514';
    END IF;
    IF EXISTS (SELECT 1 FROM received_invoice_identities own WHERE
        (c.source_kind = 'purchase' AND own.purchase_document_id = c.source_id) OR (c.source_kind = 'expense' AND own.expense_id = c.source_id)) AND
        NOT EXISTS (SELECT 1 FROM received_invoice_identities own WHERE
        ((c.source_kind = 'purchase' AND own.purchase_document_id = c.source_id) OR (c.source_kind = 'expense' AND own.expense_id = c.source_id)) AND
        (own.receiver_company_id, own.issuer_country, own.issuer_tax_id, own.document_kind, own.external_number) =
        (c.receiver_company_id, c.issuer_country, c.issuer_tax_id, c.document_kind, c.external_number)) THEN
        RAISE EXCEPTION 'received_invoice_identity_incomplete: Fiscal identity differs from preserved evidence' USING ERRCODE = '23514';
    END IF;
    IF EXISTS (SELECT 1 FROM received_invoice_identities i WHERE
        (i.receiver_company_id, i.issuer_country, i.issuer_tax_id, i.document_kind, i.external_number) =
        (c.receiver_company_id, c.issuer_country, c.issuer_tax_id, c.document_kind, c.external_number) AND
        ((c.source_kind = 'purchase' AND i.purchase_document_id IS DISTINCT FROM c.source_id) OR
         (c.source_kind = 'expense' AND i.expense_id IS DISTINCT FROM c.source_id))) THEN
        RAISE EXCEPTION 'received_invoice_fiscal_identity_unique: Identity reserved by another economic owner' USING ERRCODE = '23505';
    END IF;
    IF EXISTS (SELECT 1 FROM received_invoice_candidates other WHERE
        (other.source_kind, other.source_id) <> (c.source_kind, c.source_id) AND
        (other.receiver_company_id, other.issuer_country, other.issuer_tax_id, other.document_kind, other.external_number) =
        (c.receiver_company_id, c.issuer_country, c.issuer_tax_id, c.document_kind, c.external_number)) THEN
        RAISE EXCEPTION 'received_invoice_fiscal_identity_unique: Conflicting received invoice owners; authorized review required' USING ERRCODE = '23505';
    END IF;
END $$;
CREATE OR REPLACE FUNCTION guard_received_invoice_effect() RETURNS trigger LANGUAGE plpgsql AS $$
BEGIN
    IF TG_TABLE_NAME = 'bank_reconciliations' THEN
        IF NEW.supplier_payment_id IS NOT NULL THEN
            PERFORM assert_received_invoice_effect('purchase', p.purchase_document_id) FROM supplier_payments p WHERE p.id = NEW.supplier_payment_id AND p.purchase_document_id IS NOT NULL;
            PERFORM assert_received_invoice_effect('expense', p.expense_id) FROM supplier_payments p WHERE p.id = NEW.supplier_payment_id AND p.expense_id IS NOT NULL;
        END IF;
    ELSIF TG_TABLE_NAME = 'supplier_payments' THEN
        IF (NEW.purchase_document_id IS NULL) = (NEW.expense_id IS NULL) THEN
            RAISE EXCEPTION 'received_invoice_identity_incomplete: A payment must have exactly one economic owner' USING ERRCODE = '23514';
        END IF;
        IF NEW.purchase_document_id IS NOT NULL THEN
            PERFORM assert_received_invoice_effect('purchase', NEW.purchase_document_id);
        ELSE
            PERFORM assert_received_invoice_effect('expense', NEW.expense_id);
        END IF;
    ELSE
        IF NEW.source_type = 'App\Models\PurchaseDocument' THEN
            PERFORM assert_received_invoice_effect('purchase', NEW.source_id);
        ELSIF NEW.source_type = 'App\Models\Expense' THEN
            PERFORM assert_received_invoice_effect('expense', NEW.source_id);
        END IF;
    END IF;
    RETURN NEW;
END $$;
CREATE TRIGGER supplier_payment_received_identity BEFORE INSERT OR UPDATE OF purchase_document_id, expense_id, amount ON supplier_payments FOR EACH ROW EXECUTE FUNCTION guard_received_invoice_effect();
CREATE TRIGGER reconciliation_received_identity BEFORE INSERT OR UPDATE OF supplier_payment_id ON bank_reconciliations FOR EACH ROW EXECUTE FUNCTION guard_received_invoice_effect();
CREATE TRIGGER journal_received_identity BEFORE INSERT ON journal_entries FOR EACH ROW EXECUTE FUNCTION guard_received_invoice_effect();
CREATE OR REPLACE FUNCTION preserve_received_invoice_claim() RETURNS trigger LANGUAGE plpgsql AS $$
BEGIN
    RAISE EXCEPTION 'received_invoice_identity_immutable: Preserve fiscal identity evidence; authorized resolution required' USING ERRCODE = '23514';
END $$;
CREATE TRIGGER received_identity_preserve BEFORE UPDATE OR DELETE ON received_invoice_identities FOR EACH ROW EXECUTE FUNCTION preserve_received_invoice_claim();
SQL);
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
            throw new RuntimeException('Identity evidence exists; preserve economic guards.');
        }
        if (DB::getDriverName() !== 'pgsql') {
            DB::statement('DROP TRIGGER IF EXISTS received_identity_preserve_update');
            DB::statement('DROP TRIGGER IF EXISTS received_identity_preserve_delete');
        }
        if (DB::getDriverName() === 'pgsql') {
            DB::unprepared('DROP TRIGGER IF EXISTS supplier_payment_received_identity ON supplier_payments; DROP TRIGGER IF EXISTS journal_received_identity ON journal_entries; DROP TRIGGER IF EXISTS reconciliation_received_identity ON bank_reconciliations; DROP TRIGGER IF EXISTS received_identity_preserve ON received_invoice_identities; DROP FUNCTION IF EXISTS guard_received_invoice_effect(); DROP FUNCTION IF EXISTS assert_received_invoice_effect(text, bigint); DROP FUNCTION IF EXISTS preserve_received_invoice_claim();');
        }
    }
};
