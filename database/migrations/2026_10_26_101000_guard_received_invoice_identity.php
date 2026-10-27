<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $pg = DB::getDriverName() === 'pgsql';
        $tax = $pg ? "p.supplier_snapshot->>'tax_id'" : "json_extract(p.supplier_snapshot, '$.tax_id')";
        $country = $pg ? "p.supplier_snapshot->>'country'" : "json_extract(p.supplier_snapshot, '$.country')";
        $normalize = fn ($v) => "upper(replace(replace(replace(trim(coalesce($v, '')), ' ', ''), '-', ''), '.', ''))";
        $countryKey = fn ($v) => "CASE WHEN upper(trim(coalesce($v, 'ES'))) IN ('SPAIN', 'ESPAÑA', '') THEN 'ES' ELSE upper(trim(coalesce($v, 'ES'))) END";
        DB::statement("CREATE VIEW received_invoice_candidates AS
            SELECT 'purchase' AS source_kind, p.id AS source_id,
                coalesce(p.receiver_company_id, (SELECT min(id) FROM company_profiles)) AS receiver_company_id,
                ".$countryKey("coalesce($country, s.country)").' AS issuer_country,
                '.$normalize("coalesce($tax, s.tax_id)")." AS issuer_tax_id,
                CASE WHEN p.type = 'supplier_credit_note' THEN 'credit_note' ELSE 'invoice' END AS document_kind,
                trim(coalesce(p.supplier_number, '')) AS external_number
            FROM purchase_documents p LEFT JOIN suppliers s ON s.id = p.supplier_id
            WHERE p.type IN ('supplier_invoice', 'supplier_credit_note')
            UNION ALL
            SELECT 'expense', e.id, coalesce(e.receiver_company_id, (SELECT min(id) FROM company_profiles)),
                ".$countryKey('e.issuer_country').', '.$normalize('e.issuer_tax_id').", 'invoice', trim(coalesce(e.document_number, ''))
            FROM expenses e WHERE e.type IN ('invoice', 'simplified_invoice')");

        if ($pg) {
            DB::unprepared(<<<'SQL'
CREATE OR REPLACE FUNCTION guard_received_invoice_identity() RETURNS trigger LANGUAGE plpgsql AS $$
DECLARE c record; previous record;
BEGIN
    SELECT * INTO c FROM received_invoice_candidates WHERE source_kind = TG_ARGV[0] AND source_id = NEW.id;
    SELECT * INTO previous FROM received_invoice_identities WHERE
        (TG_ARGV[0] = 'purchase' AND purchase_document_id = NEW.id) OR
        (TG_ARGV[0] = 'expense' AND expense_id = NEW.id);
    IF FOUND THEN
        IF EXISTS (SELECT 1 FROM received_invoice_identities i WHERE
            ((TG_ARGV[0] = 'purchase' AND i.purchase_document_id = NEW.id) OR (TG_ARGV[0] = 'expense' AND i.expense_id = NEW.id)) AND
            (i.receiver_company_id, i.issuer_country, i.issuer_tax_id, i.document_kind, i.external_number) =
            (c.receiver_company_id, c.issuer_country, c.issuer_tax_id, c.document_kind, c.external_number)) THEN RETURN NEW; END IF;
        IF TG_ARGV[0] <> 'expense' OR coalesce(current_setting('arevantes.received_invoice_correction_id', true), '') <> NEW.id::text THEN
            RAISE EXCEPTION 'received_invoice_identity_immutable: Received invoice identity is immutable; authorized documentary review required' USING ERRCODE = '23514', CONSTRAINT = 'received_invoice_identity_immutable';
        END IF;
    ELSIF TG_OP = 'UPDATE' THEN RETURN NEW;
    END IF;
    IF c.source_id IS NULL THEN RETURN NEW; END IF;
    IF c.issuer_tax_id = '' OR c.external_number = '' OR c.receiver_company_id IS NULL THEN
        IF TG_ARGV[0] = 'purchase' OR NEW.type = 'invoice' THEN
            RAISE EXCEPTION 'received_invoice_identity_incomplete: Complete supplier tax identity, recipient and external invoice number before registering the invoice' USING ERRCODE = '23514', CONSTRAINT = 'received_invoice_identity_incomplete';
        END IF;
        RETURN NEW;
    END IF;
    PERFORM pg_advisory_xact_lock(hashtextextended(jsonb_build_array(c.receiver_company_id, c.issuer_country, c.issuer_tax_id, c.document_kind, c.external_number)::text, 0));
    IF EXISTS (SELECT 1 FROM received_invoice_candidates other WHERE
        (other.source_kind, other.source_id) <> (c.source_kind, c.source_id) AND
        (other.receiver_company_id, other.issuer_country, other.issuer_tax_id, other.document_kind, other.external_number) =
        (c.receiver_company_id, c.issuer_country, c.issuer_tax_id, c.document_kind, c.external_number)) THEN
        RAISE EXCEPTION 'received_invoice_fiscal_identity_unique: This received invoice already exists in purchases or expenses; use the existing document' USING ERRCODE = '23505', CONSTRAINT = 'received_invoice_fiscal_identity_unique';
    END IF;
    INSERT INTO received_invoice_identities (receiver_company_id, issuer_country, issuer_tax_id, document_kind, external_number, purchase_document_id, expense_id)
    VALUES (c.receiver_company_id, c.issuer_country, c.issuer_tax_id, c.document_kind, c.external_number,
        CASE WHEN c.source_kind = 'purchase' THEN c.source_id END,
        CASE WHEN c.source_kind = 'expense' THEN c.source_id END);
    RETURN NEW;
END $$;
CREATE TRIGGER purchase_received_identity AFTER INSERT OR UPDATE ON purchase_documents FOR EACH ROW EXECUTE FUNCTION guard_received_invoice_identity('purchase');
CREATE TRIGGER expense_received_identity AFTER INSERT OR UPDATE ON expenses FOR EACH ROW EXECUTE FUNCTION guard_received_invoice_identity('expense');
SQL);
        } else {
            foreach (['purchase_documents' => 'purchase', 'expenses' => 'expense'] as $table => $kind) {
                $owner = $kind === 'purchase' ? 'purchase_document_id' : 'expense_id';
                $otherOwner = $kind === 'purchase' ? 'expense_id' : 'purchase_document_id';
                $mandatory = $kind === 'purchase' ? '1=1' : "NEW.type = 'invoice'";
                DB::unprepared("CREATE TRIGGER {$kind}_received_identity AFTER INSERT ON $table BEGIN
                    SELECT RAISE(ABORT, 'received_invoice_identity_incomplete') FROM received_invoice_candidates c WHERE c.source_kind = '$kind' AND c.source_id = NEW.id AND ($mandatory) AND (c.issuer_tax_id = '' OR c.external_number = '' OR c.receiver_company_id IS NULL);
                    SELECT RAISE(ABORT, 'received_invoice_fiscal_identity_unique') FROM received_invoice_candidates c JOIN received_invoice_candidates other
                        ON other.receiver_company_id = c.receiver_company_id AND other.issuer_country = c.issuer_country AND other.issuer_tax_id = c.issuer_tax_id AND other.document_kind = c.document_kind AND other.external_number = c.external_number
                        WHERE c.source_kind = '$kind' AND c.source_id = NEW.id AND c.issuer_tax_id <> '' AND c.external_number <> '' AND (other.source_kind <> c.source_kind OR other.source_id <> c.source_id);
                    INSERT INTO received_invoice_identities (receiver_company_id, issuer_country, issuer_tax_id, document_kind, external_number, $owner, $otherOwner)
                        SELECT receiver_company_id, issuer_country, issuer_tax_id, document_kind, external_number, source_id, NULL FROM received_invoice_candidates WHERE source_kind = '$kind' AND source_id = NEW.id AND issuer_tax_id <> '' AND external_number <> '';
                END");
                DB::unprepared("CREATE TRIGGER {$kind}_received_identity_update AFTER UPDATE ON $table BEGIN
                    SELECT RAISE(ABORT, 'received_invoice_identity_immutable') FROM received_invoice_identities i WHERE i.$owner = NEW.id AND received_identity_correction_allowed(NEW.id) = 0 AND NOT EXISTS (
                        SELECT 1 FROM received_invoice_candidates c JOIN received_invoice_identities own ON own.$owner = NEW.id WHERE c.source_kind = '$kind' AND c.source_id = NEW.id AND c.receiver_company_id = own.receiver_company_id AND c.issuer_country = own.issuer_country AND c.issuer_tax_id = own.issuer_tax_id AND c.document_kind = own.document_kind AND c.external_number = own.external_number);
                    INSERT INTO received_invoice_identities (receiver_company_id, issuer_country, issuer_tax_id, document_kind, external_number, $owner, $otherOwner)
                        SELECT receiver_company_id, issuer_country, issuer_tax_id, document_kind, external_number, source_id, NULL FROM received_invoice_candidates c WHERE source_kind = '$kind' AND source_id = NEW.id AND received_identity_correction_allowed(NEW.id) = 1 AND NOT EXISTS (SELECT 1 FROM received_invoice_identities own WHERE own.$owner = NEW.id AND own.receiver_company_id = c.receiver_company_id AND own.issuer_country = c.issuer_country AND own.issuer_tax_id = c.issuer_tax_id AND own.document_kind = c.document_kind AND own.external_number = c.external_number);
                END");
            }
        }
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
            throw new RuntimeException('Identity evidence exists; preserve guards and registry.');
        }
        foreach (['purchase' => 'purchase_documents', 'expense' => 'expenses'] as $kind => $table) {
            DB::statement('DROP TRIGGER IF EXISTS '.$kind.'_received_identity'.(DB::getDriverName() === 'pgsql' ? ' ON '.$table : ''));
            if (DB::getDriverName() !== 'pgsql') {
                DB::statement('DROP TRIGGER IF EXISTS '.$kind.'_received_identity_update');
            }
        }
        if (DB::getDriverName() === 'pgsql') {
            DB::statement('DROP FUNCTION IF EXISTS guard_received_invoice_identity()');
        }
        DB::statement('DROP VIEW IF EXISTS received_invoice_candidates');
    }
};
