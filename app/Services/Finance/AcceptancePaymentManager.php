<?php

namespace App\Services\Finance;

use App\Models\Quote;
use App\Models\SalesDocument;
use App\Services\Accounting\AccountingSynchronizer;
use App\Services\Documents\AcceptanceInvoiceNotifier;
use App\Services\Documents\DocumentNumber;
use App\Services\Documents\QuoteBillingPlan;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class AcceptancePaymentManager
{
    public function __construct(
        private readonly QuoteBillingPlan $billing,
        private readonly DocumentNumber $numbers,
        private readonly AccountingSynchronizer $accounting,
        private readonly AcceptanceInvoiceNotifier $notifier,
    ) {}

    public function activate(Quote $quote): ?SalesDocument
    {
        return DB::transaction(function () use ($quote): ?SalesDocument {
            $quote = Quote::lockForUpdate()->with(['lines', 'acceptanceInvoice'])->findOrFail($quote->id);
            abort_unless($quote->status === 'accepted', 422, __('The acceptance payment can only be activated for an accepted quote.'));

            if ((float) $quote->acceptance_payment_percentage <= 0) {
                $this->setStatus($quote, 'cleared');

                return null;
            }

            if ($quote->acceptanceInvoice) {
                $this->refresh($quote);

                return $quote->acceptanceInvoice;
            }

            $summary = $this->billing->summary($quote);
            $percentage = round((float) $quote->acceptance_payment_percentage, 2);
            if ($percentage > $summary['remaining_percentage']) {
                throw ValidationException::withMessages([
                    'acceptance_payment_percentage' => __('Only :percentage% remains available to invoice.', ['percentage' => $summary['remaining_percentage']]),
                ]);
            }

            $installment = $this->billing->installment($quote, $percentage, abs($percentage - $summary['remaining_percentage']) < 0.001);
            if ($installment['lines'] === []) {
                $installment = $this->legacyInstallment($quote, $percentage);
            }
            $issued = $this->numbers->next('invoice', today());
            $document = SalesDocument::create([
                'type' => 'invoice',
                'quote_id' => $quote->id,
                'person_id' => $quote->person_id,
                'installation_id' => $quote->installation_id,
                'document_series_id' => $issued['series']->id,
                'created_by' => $quote->created_by,
                'number' => $issued['number'],
                'status' => 'issued',
                'issue_date' => today(),
                'due_date' => today()->addDays((int) $quote->acceptance_payment_due_days),
                'billing_percentage' => $percentage,
                'billing_sequence' => count($summary['documents']) + 1,
                'billing_label' => __('Acceptance payment'),
                'is_acceptance_invoice' => true,
                'issued_at' => now(),
                'locked_at' => now(),
                ...$installment['totals'],
                'paid_amount' => 0,
                'customer_snapshot' => $quote->customer_snapshot ?? [],
                'notes' => __('Automatically issued after acceptance of quote :number. Use :reference as the bank transfer reference.', [
                    'number' => $quote->number,
                    'reference' => $issued['number'],
                ]),
            ]);
            $document->lines()->createMany($installment['lines']);
            $this->accounting->syncSalesDocument($document->refresh(), $quote->created_by);
            $this->setStatus($quote, 'pending');
            $this->notifier->send($document);

            return $document;
        });
    }

    public function refresh(Quote $quote): string
    {
        $quote->loadMissing('acceptanceInvoice.payments');
        if ((float) $quote->acceptance_payment_percentage <= 0) {
            return $this->setStatus($quote, 'cleared');
        }

        $invoice = $quote->acceptanceInvoice;
        if (! $invoice) {
            return $this->setStatus($quote, $quote->status === 'accepted' ? 'pending' : 'not_required');
        }

        $paid = round((float) $invoice->payments->sum('amount'), 2);
        $required = round((float) $invoice->amount, 2);
        $status = $paid >= $required ? 'cleared' : ($paid > 0 ? 'partial' : 'pending');

        return $this->setStatus($quote, $status);
    }

    private function setStatus(Quote $quote, string $status): string
    {
        $clearedAt = $status === 'cleared' ? ($quote->financially_cleared_at ?? now()) : null;
        $quote->update(['financial_status' => $status, 'financially_cleared_at' => $clearedAt]);
        $quote->installation?->update(['financial_status' => $status, 'financially_cleared_at' => $clearedAt]);

        return $status;
    }

    /** @return array{totals: array{subtotal: float, discount_amount: float, tax_amount: float, amount: float}, lines: array<int, array<string, mixed>>} */
    private function legacyInstallment(Quote $quote, float $percentage): array
    {
        $ratio = $percentage / 100;
        $subtotal = round((float) $quote->subtotal * $ratio, 2);
        $discount = round((float) $quote->discount_amount * $ratio, 2);
        $tax = round((float) $quote->tax_amount * $ratio, 2);
        $amount = round((float) $quote->amount * $ratio, 2);
        $taxable = $subtotal - $discount;

        return [
            'totals' => ['subtotal' => $subtotal, 'discount_amount' => $discount, 'tax_amount' => $tax, 'amount' => $amount],
            'lines' => [[
                'position' => 1,
                'description' => __('Acceptance payment for quote :number', ['number' => $quote->number]),
                'quantity' => 1,
                'unit_price' => $subtotal,
                'discount_percent' => $subtotal > 0 ? round($discount / $subtotal * 100, 2) : 0,
                'tax_percent' => $taxable > 0 ? round($tax / $taxable * 100, 2) : 0,
                'subtotal' => $subtotal,
                'discount_amount' => $discount,
                'tax_amount' => $tax,
                'total' => $amount,
            ]],
        ];
    }
}
