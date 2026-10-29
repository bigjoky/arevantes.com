<?php

namespace App\Services\Accounting;

use App\Models\AccountingConfiguration;
use App\Models\BankTransactionPosting;
use App\Models\Expense;
use App\Models\JournalEntry;
use App\Models\PurchaseDocument;
use App\Models\SalesDocument;
use App\Models\SalesDocumentPayment;
use App\Models\SupplierPayment;
use Illuminate\Database\Eloquent\Model;

class JournalConcepts
{
    public const DEFAULTS = [
        'sales_credit_note' => ['es' => 'Ntra. fra. rectificativa {numero} — {cliente}', 'en' => 'Our credit note {numero} — {cliente}'],
        'purchase_credit_note' => ['es' => 'S/ fra. rectificativa {numero} — {proveedor}', 'en' => 'Supplier credit note {numero} — {proveedor}'],
        'advance_allocation' => ['es' => 'Aplicación anticipo — fra. {numero} — {cliente}', 'en' => 'Advance allocation — invoice {numero} — {cliente}'],
        'sales_invoice' => ['es' => 'Ntra. fra. {numero} — {cliente}', 'en' => 'Our invoice {numero} — {cliente}'],
        'purchase_invoice' => ['es' => 'S/ fra. {numero} — {proveedor}', 'en' => 'Supplier invoice {numero} — {proveedor}'],
        'receipt' => ['es' => 'Cobro fra. {numero} — {cliente}', 'en' => 'Invoice receipt {numero} — {cliente}'],
        'payment' => ['es' => 'Pago fra. {numero} — {proveedor}', 'en' => 'Invoice payment {numero} — {proveedor}'],
        'customer_advance' => ['es' => 'Anticipo cliente — {cliente}', 'en' => 'Customer advance — {cliente}'],
        'supplier_advance' => ['es' => 'Anticipo proveedor — {proveedor}', 'en' => 'Supplier advance — {proveedor}'],
        'bank_fee' => ['es' => 'Comisión bancaria — {referencia}', 'en' => 'Bank fee — {referencia}'],
        'reversal' => ['es' => 'Anulación {referencia} — {concepto}', 'en' => 'Reversal {referencia} — {concepto}'],
        'correction' => ['es' => 'Rectificación {referencia} — {concepto}', 'en' => 'Correction {referencia} — {concepto}'],
        'vat' => ['es' => 'IVA — {concepto}', 'en' => 'VAT — {concepto}'],
    ];

    public const TOKENS = ['numero', 'cliente', 'proveedor', 'referencia', 'concepto'];

    public function render(string $key, array $values = [], ?string $locale = null): string
    {
        $locale = ($locale ?? app()->getLocale()) === 'en' ? 'en' : 'es';
        $template = AccountingConfiguration::find(1)?->templates[$key][$locale] ?? self::DEFAULTS[$key][$locale];
        $text = strtr($template, collect(self::TOKENS)->mapWithKeys(fn ($t) => ['{'.$t.'}' => (string) ($values[$t] ?? '')])->all());

        return mb_substr(trim(preg_replace('/\s+/u', ' ', $text), " —\t\n\r\0\x0B"), 0, 255);
    }

    public function forSource(?Model $source, string $event, string $fallback): string
    {
        $doc = $source instanceof SalesDocumentPayment ? $source->document : ($source instanceof SupplierPayment ? ($source->expense ?? $source->purchaseDocument) : $source);
        if ($source instanceof SalesDocumentPayment && $event === 'invoice_allocation') {
            return $this->render('advance_allocation', $this->values($doc));
        }
        if ($source instanceof SalesDocumentPayment) {
            return $this->render($doc?->collect_before_invoice && ! $doc?->locked_at ? 'customer_advance' : 'receipt', $this->values($doc));
        }
        if ($source instanceof SupplierPayment) {
            return $this->render('payment', $this->values($doc));
        }
        if ($source instanceof SalesDocument) {
            return $this->render($doc->type === 'credit_note' ? 'sales_credit_note' : 'sales_invoice', $this->values($doc));
        }
        if ($source instanceof PurchaseDocument || $source instanceof Expense) {
            return $this->render($doc instanceof PurchaseDocument && $doc->type === 'supplier_credit_note' ? 'purchase_credit_note' : 'purchase_invoice', $this->values($doc));
        }

        return $fallback;
    }

    public function posting(?Model $source, string $kind, string $event, string $fallback, ?JournalEntry $reverses = null): string
    {
        if ($kind === 'automatic') {
            return $this->forSource($source, $event, $fallback);
        }
        if ($reverses) {
            return $this->render('reversal', ['referencia' => $reverses->number, 'concepto' => $reverses->description]);
        }
        if (in_array($kind, ['invoice_correction', 'expense_correction'], true)) {
            return $this->render('correction', ['referencia' => $source?->accountingEntry?->number ?? '', 'concepto' => $this->forSource($source, $event, $fallback)]);
        }
        if (in_array($kind, ['tax_settlement', 'tax_payment'], true)) {
            return $this->render('vat', ['concepto' => $fallback]);
        }
        if ($source instanceof BankTransactionPosting && $source->counterpart && str_starts_with($source->counterpart->code, '626')) {
            return $this->render('bank_fee', ['referencia' => $source->transaction?->reference ?? $source->transaction?->description ?? '']);
        }

        return $fallback;
    }

    private function values(?Model $doc): array
    {
        if (! $doc) {
            return [];
        }
        $customer = $doc instanceof SalesDocument ? $doc->customer : null;
        $supplier = ($doc instanceof PurchaseDocument || $doc instanceof Expense) ? $doc->supplier : null;
        $snapshot = $doc->customer_snapshot ?? [];

        return ['numero' => $doc instanceof PurchaseDocument ? ($doc->supplier_number ?: '') : ($doc instanceof Expense ? ($doc->document_number ?: '') : $doc->number),
            'cliente' => $customer?->company_name ?: trim(($customer?->first_name ?? '').' '.($customer?->last_name ?? '')) ?: ($snapshot['name'] ?? $snapshot['company_name'] ?? ''),
            'proveedor' => $supplier?->legal_name ?: ($doc->issuer_legal_name ?? $doc->merchant_name ?? ''), 'referencia' => $doc->number ?? ''];
    }
}
