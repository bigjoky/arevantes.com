<?php

namespace App\Services\Finance;

use App\Models\AuditLog;
use App\Models\Expense;
use App\Models\FiscalEntity;
use App\Models\JournalEntry;
use App\Models\PurchaseDocument;
use App\Models\SalesDocument;
use App\Models\SalesDocumentPayment;
use App\Models\SupplierPayment;
use App\Services\Documents\DocumentPdf;
use Brick\Math\BigDecimal;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use League\Flysystem\FilesystemException;

/** SELECT-only diagnostic. It never claims identities, links sources or repairs history. */
class ReceivedInvoiceAudit
{
    public function report(): array
    {
        $groups = [];
        $paymentInconsistencies = [];
        $unverifiedIdentities = [];
        $registryAvailable = Schema::hasTable('received_invoice_identities');
        $multipleReceivers = DB::table('company_profiles')->count() > 1;
        $incomplete = [];
        foreach ($this->candidates() as $row) {
            $reference = ['kind' => $row->source_kind, 'id' => $row->source_id];
            if (! $row->issuer_tax_id || ! $row->external_number || ! $row->receiver_company_id || strlen($row->issuer_country) !== 2) {
                $incomplete[] = $reference;

                continue;
            }
            $key = hash('sha256', json_encode([$row->receiver_company_id, $row->issuer_country, $row->issuer_tax_id, $row->document_kind, $row->external_number], JSON_THROW_ON_ERROR));
            $source = $row->source_kind === 'purchase' ? PurchaseDocument::findOrFail($row->source_id) : Expense::findOrFail($row->source_id);
            if (($source instanceof PurchaseDocument && blank(data_get($source->supplier_snapshot, 'tax_id')) && (! $registryAvailable || ! DB::table('received_invoice_identities')->where('purchase_document_id', $source->id)->exists()))
                || ($multipleReceivers && ($source->getAttributes()['receiver_company_id'] ?? null) === null)) {
                $unverifiedIdentities[] = $reference;
            }
            $payments = $source->payments()->withoutGlobalScopes()->get();
            $actualPaid = BigDecimal::zero();
            foreach ($payments as $payment) {
                if (! $payment->voided_at) {
                    $actualPaid = $actualPaid->plus($payment->amount);
                }
            }
            if (! $actualPaid->isEqualTo(BigDecimal::of($source->paid_amount ?? '0'))) {
                $paymentInconsistencies[] = [...$reference, 'stored_paid' => $source->paid_amount, 'effective_paid' => (string) $actualPaid];
            }
            $groups[$key][] = [...$reference,
                'receiver_inferred' => ($source->getAttributes()['receiver_company_id'] ?? null) === null,
                'issuer_inferred' => $source instanceof PurchaseDocument && blank(data_get($source->supplier_snapshot, 'tax_id')),
                'document_number' => $source->number,
                'obligation_amount' => $source->amount,
                'journal_ids' => $source->accountingEntry()->pluck('id')->all(),
                'payment_ids' => $payments->pluck('id')->all(),
                'reconciliation_ids' => DB::table('bank_reconciliations')->whereIn('supplier_payment_id', $payments->pluck('id'))->pluck('id')->all()];
        }
        $collisions = [];
        foreach ($groups as $key => $sources) {
            if (count($sources) > 1) {
                $collisions[] = ['identity_sha256' => $key, 'classification' => collect($sources)->contains(fn ($s) => $s['receiver_inferred'] || $s['issuer_inferred']) ? 'requires_identity_review' : 'confirmed_identity_collision', 'same_physical_original_confirmed' => false, 'sources' => $sources];
            }
        }
        $supplierGroups = [];
        foreach (DB::table('suppliers')->select(['id', 'country', 'tax_id'])->cursor() as $supplier) {
            $taxId = strtoupper(str_replace([' ', '-', '.'], '', trim($supplier->tax_id ?? '')));
            $country = strtoupper(trim($supplier->country ?? 'ES'));
            $country = in_array($country, ['', 'SPAIN', 'ESPAÑA']) ? 'ES' : $country;
            if ($taxId !== '') {
                $supplierGroups[hash('sha256', $country.':'.$taxId)][] = $supplier->id;
            }
        }
        $custody = ['original_archived' => [], 'historical_original_missing' => [], 'integrity_failed' => []];
        $custodyUnavailable = [];
        foreach (SalesDocument::whereIn('type', ['invoice', 'credit_note'])->where(fn ($q) => $q->whereNotNull('locked_at')->orWhereNotNull('issued_at')->orWhere('status', '<>', 'draft'))->lazyById() as $document) {
            try {
                $custody[app(DocumentPdf::class)->custody($document)][] = $document->id;
            } catch (FilesystemException|\InvalidArgumentException $exception) {
                $custodyUnavailable[] = ['id' => $document->id, 'error_class' => $exception::class];
            }
        }
        $referencedArchives = SalesDocument::whereNotNull('archived_pdf_path')->pluck('archived_pdf_path')->all();
        foreach (AuditLog::where('event', 'issued_invoice_corrected')->lazyById() as $correction) {
            foreach ([$correction->old_values, $correction->new_values] as $snapshot) {
                if (! empty($snapshot['archived_pdf_path'])) {
                    $referencedArchives[] = $snapshot['archived_pdf_path'];
                }
            }
        }
        $archiveInventory = ['status' => 'available', 'unreferenced_files_require_review' => []];
        try {
            $files = Storage::disk(config('filesystems.private'))->allFiles('issued-invoices');
            $archiveInventory['unreferenced_files_require_review'] = array_values(array_diff($files, $referencedArchives));
        } catch (\Throwable $exception) {
            $archiveInventory = ['status' => 'unavailable', 'reason' => $exception::class];
        }
        $orphanJournals = [];
        foreach (['purchase' => PurchaseDocument::class, 'expense' => Expense::class, 'sales' => SalesDocument::class, 'customer_payment' => SalesDocumentPayment::class, 'supplier_payment' => SupplierPayment::class] as $kind => $model) {
            $table = (new $model)->getTable();
            $orphanJournals[$kind] = DB::table('journal_entries as j')->where('j.source_type', $model)->whereNotExists(fn ($q) => $q->selectRaw('1')->from($table.' as s')->whereColumn('s.id', 'j.source_id'))->pluck('j.id')->all();
        }

        $unbalanced = [];
        foreach (JournalEntry::with('lines')->where('status', 'posted')->lazyById() as $entry) {
            $net = BigDecimal::zero();
            foreach ($entry->lines as $line) {
                $net = $net->plus($line->debit)->minus($line->credit);
            }
            if (! $net->isZero()) {
                $unbalanced[] = $entry->id;
            }
        }
        $orphanSupplierPayments = DB::table('supplier_payments as p')->where(function ($query) {
            $query->where(fn ($q) => $q->whereNotNull('p.purchase_document_id')->whereNotExists(fn ($s) => $s->selectRaw('1')->from('purchase_documents as d')->whereColumn('d.id', 'p.purchase_document_id')))
                ->orWhere(fn ($q) => $q->whereNotNull('p.expense_id')->whereNotExists(fn ($s) => $s->selectRaw('1')->from('expenses as e')->whereColumn('e.id', 'p.expense_id')));
        })->pluck('p.id')->all();
        $orphanCustomerPayments = DB::table('sales_document_payments as p')->whereNotExists(fn ($q) => $q->selectRaw('1')->from('sales_documents as d')->whereColumn('d.id', 'p.sales_document_id'))->pluck('p.id')->all();
        $orphanReconciliations = DB::table('bank_reconciliations as r')->leftJoin('bank_transactions as t', 't.id', '=', 'r.bank_transaction_id')
            ->leftJoin('supplier_payments as sp', 'sp.id', '=', 'r.supplier_payment_id')->leftJoin('sales_document_payments as cp', 'cp.id', '=', 'r.sales_document_payment_id')
            ->where(fn ($q) => $q->whereNull('t.id')->orWhere(fn ($s) => $s->whereNotNull('r.supplier_payment_id')->whereNull('sp.id'))->orWhere(fn ($c) => $c->whereNotNull('r.sales_document_payment_id')->whereNull('cp.id')))->pluck('r.id')->all();
        $migrationBlockers = $this->migrationBlockers();

        $report = ['orphan_supplier_payment_ids' => $orphanSupplierPayments, 'orphan_customer_payment_ids' => $orphanCustomerPayments, 'orphan_reconciliation_ids' => $orphanReconciliations, 'custody_unavailable' => $custodyUnavailable, 'unverified_identity_references' => $unverifiedIdentities, 'schema_identity_guards_installed' => Schema::hasView('received_invoice_candidates'), 'migration_blockers' => $migrationBlockers, 'payment_inconsistencies' => $paymentInconsistencies, 'unbalanced_posted_journal_ids' => $unbalanced, 'read_only' => true, 'history_modified' => false, 'identity_collisions' => $collisions, 'incomplete_identities' => $incomplete, 'possible_duplicate_suppliers' => array_filter($supplierGroups, fn ($ids) => count($ids) > 1), 'custody' => $custody, 'archive_inventory' => $archiveInventory, 'orphan_journal_ids' => $orphanJournals,
            'contradictory_payment_ids' => DB::table('supplier_payments')->where(fn ($q) => $q->where(fn ($both) => $both->whereNotNull('purchase_document_id')->whereNotNull('expense_id'))->orWhere(fn ($neither) => $neither->whereNull('purchase_document_id')->whereNull('expense_id')))->pluck('id')->all()];
        $report['migration_constraints_blocked'] = ! empty($migrationBlockers['purchase_unique_index_collisions']) || ! empty($migrationBlockers['expense_unique_index_collisions']);
        $report['review_findings'] = count($collisions) + count($incomplete) + count($unverifiedIdentities) + count($paymentInconsistencies) + count($unbalanced)
            + count($custody['historical_original_missing']) + count($custody['integrity_failed']) + count($custodyUnavailable)
            + count($orphanSupplierPayments) + count($orphanCustomerPayments) + count($orphanReconciliations) + array_sum(array_map('count', $orphanJournals)) + count($report['contradictory_payment_ids'])
            + count($report['possible_duplicate_suppliers']) + count($archiveInventory['unreferenced_files_require_review'] ?? []);
        $report['deployment_review_required'] = $report['migration_constraints_blocked'] || $report['review_findings'] > 0 || $archiveInventory['status'] === 'unavailable';

        return $report;
    }

    private function candidates(): iterable
    {
        if (Schema::hasView('received_invoice_candidates')) {
            yield from DB::table('received_invoice_candidates')->orderBy('source_kind')->orderBy('source_id')->cursor();

            return;
        }
        $receiver = DB::table('company_profiles')->min('id');
        foreach (PurchaseDocument::with('supplier')->whereIn('type', ['supplier_invoice', 'supplier_credit_note'])->lazyById() as $source) {
            $identity = $source->supplierIdentity();
            $country = strtoupper(trim($identity['country'] ?? 'ES'));
            yield (object) ['source_kind' => 'purchase', 'source_id' => $source->id, 'receiver_company_id' => $source->getAttributes()['receiver_company_id'] ?? $receiver,
                'issuer_country' => in_array($country, ['SPAIN', 'ESPAÑA', '']) ? 'ES' : $country,
                'issuer_tax_id' => FiscalEntity::taxKey($identity['tax_id'] ?? ''),
                'document_kind' => $source->type === 'supplier_credit_note' ? 'credit_note' : 'invoice', 'external_number' => trim($source->supplier_number ?? '')];
        }
        foreach (Expense::whereIn('type', ['invoice', 'simplified_invoice'])->lazyById() as $source) {
            yield (object) ['source_kind' => 'expense', 'source_id' => $source->id, 'receiver_company_id' => $source->getAttributes()['receiver_company_id'] ?? $receiver,
                'issuer_country' => strtoupper(trim($source->issuer_country ?? 'ES')),
                'issuer_tax_id' => FiscalEntity::taxKey($source->issuer_tax_id ?? ''), 'document_kind' => 'invoice', 'external_number' => trim($source->document_number ?? '')];
        }
    }

    private function migrationBlockers(): array
    {
        $scope = Schema::hasView('received_invoice_candidates') ? 'coalesce(receiver_company_id, 0), ' : '';
        $selectedScope = $scope ? 'coalesce(receiver_company_id, 0) AS receiver_scope, ' : '';
        $purchase = DB::table('purchase_documents')->selectRaw($selectedScope.'supplier_id, type, supplier_number, count(*) AS copies')->whereIn('type', ['supplier_invoice', 'supplier_credit_note'])->whereNotNull('supplier_number')->where('supplier_number', '<>', '')->groupByRaw($scope.'supplier_id, type, supplier_number')->havingRaw('count(*) > 1')->get();
        $tax = "upper(replace(replace(replace(issuer_tax_id, ' ', ''), '-', ''), '.', ''))";
        $expense = DB::table('expenses')->selectRaw($selectedScope."coalesce(issuer_country, 'ES') AS country, $tax AS tax_key, document_number, count(*) AS copies")->whereNotNull('issuer_tax_id')->where('issuer_tax_id', '<>', '')->whereNotNull('document_number')->where('document_number', '<>', '')->groupByRaw($scope."coalesce(issuer_country, 'ES'), $tax, document_number")->havingRaw('count(*) > 1')->get();

        return ['purchase_unique_index_collisions' => $purchase->map(fn ($row) => ['key_sha256' => hash('sha256', json_encode([$row->receiver_scope ?? null, $row->supplier_id, $row->type, $row->supplier_number])), 'records' => (int) $row->copies])->all(),
            'expense_unique_index_collisions' => $expense->map(fn ($row) => ['key_sha256' => hash('sha256', json_encode([$row->receiver_scope ?? null, $row->country, $row->tax_key, $row->document_number])), 'records' => (int) $row->copies])->all()];
    }
}
