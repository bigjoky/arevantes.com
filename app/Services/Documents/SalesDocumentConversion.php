<?php

namespace App\Services\Documents;

use App\Models\SalesDocument;
use App\Models\SalesDocumentLine;
use App\Services\Quotes\QuoteCalculator;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class SalesDocumentConversion
{
    public function __construct(private QuoteCalculator $calculator, private DocumentNumber $numbers) {}

    /** @return array{targets: array<int, string>, lines: array<int, array<string, mixed>>, children: array<int, array<string, mixed>>}|null */
    public function summary(SalesDocument $document): ?array
    {
        $targets = match ($document->type) {
            'order' => ['delivery_note', 'invoice'],
            'delivery_note' => ['invoice'],
            default => [],
        };
        if ($targets === []) {
            return null;
        }

        $document->loadMissing('lines');
        $converted = $this->convertedQuantities($document);
        $delivered = $this->deliveredQuantities($document);

        return [
            'targets' => $targets,
            'lines' => $document->lines->map(fn ($line) => [
                'id' => $line->id,
                'description' => $line->description,
                'quantity' => (float) $line->quantity,
                'allocated_delivery_quantity' => (float) ($converted['delivery_note'][$line->id] ?? 0),
                'delivered_quantity' => (float) ($delivered[$line->id] ?? 0),
                'invoiced_quantity' => (float) ($converted['invoice'][$line->id] ?? 0),
                'remaining_delivery_quantity' => max(0, round((float) $line->quantity - (float) ($converted['delivery_note'][$line->id] ?? 0), 3)),
                'remaining_invoice_quantity' => max(0, round((float) $line->quantity - (float) ($converted['invoice'][$line->id] ?? 0), 3)),
            ])->all(),
            'children' => $document->childDocuments()->orderByDesc('id')->get(['id', 'type', 'number', 'status', 'amount', 'issue_date'])->map(fn ($child) => [
                ...$child->toArray(),
                'issue_date' => $child->issue_date?->toDateString(),
            ])->all(),
        ];
    }

    /** Serialize capacity shared by direct invoices and invoices reached through delivery notes. */
    public function lockCapacityRoot(SalesDocument $document): void
    {
        $root = null;
        $seen = [];
        while ($document) {
            if (isset($seen[$document->id])) {
                throw new \LogicException('Cyclic sales document origin.');
            }
            $seen[$document->id] = true;
            if (in_array($document->type, ['order', 'delivery_note'], true)) {
                $root = $document;
            }
            $document = $document->source_document_id ? SalesDocument::findOrFail($document->source_document_id) : null;
        }
        if ($root) {
            SalesDocument::whereKey($root->id)->lockForUpdate()->firstOrFail();
        }
    }

    public function convert(SalesDocument $source, string $targetType, array $quantities, int $userId, ?string $issueDate = null): SalesDocument
    {
        $issueDate ??= now()->toDateString();

        return DB::transaction(function () use ($source, $targetType, $quantities, $userId, $issueDate): SalesDocument {
            $this->lockCapacityRoot($source);
            $source = SalesDocument::lockForUpdate()->with('lines')->findOrFail($source->id);
            $allowed = match ($source->type) {
                'order' => ['delivery_note', 'invoice'],
                'delivery_note' => ['invoice'],
                default => [],
            };
            if (! in_array($targetType, $allowed, true)) {
                throw ValidationException::withMessages(['type' => __('This document cannot be converted to the selected type.')]);
            }
            if ($source->type === 'delivery_note' && $source->status !== 'delivered') {
                throw ValidationException::withMessages(['type' => __('Dispatch the delivery note before invoicing it.')]);
            }

            $converted = $this->convertedQuantities($source)[$targetType] ?? [];
            $this->validateOrderInvoiceCapacity($source, $targetType, $quantities);
            $lines = $source->lines->map(function ($line) use ($quantities, $converted): ?array {
                $quantity = round((float) ($quantities[$line->id] ?? 0), 3);
                $remaining = max(0, round((float) $line->quantity - (float) ($converted[$line->id] ?? 0), 3));
                if ($quantity < 0 || $quantity > $remaining) {
                    throw ValidationException::withMessages(["quantities.{$line->id}" => __('The selected quantity exceeds the remaining quantity.')]);
                }
                if ($quantity === 0.0) {
                    return null;
                }

                return [
                    'source_line_id' => $line->id,
                    ...$line->only(['product_id', 'unit_id', 'description', 'unit_price', 'discount_percent', 'tax_percent']),
                    'quantity' => $quantity,
                ];
            })->filter()->values()->all();
            if ($lines === []) {
                throw ValidationException::withMessages(['quantities' => __('Select at least one quantity to convert.')]);
            }

            $totals = $this->calculator->calculate($lines);
            $issued = $targetType === 'invoice' ? null : $this->numbers->next($targetType, $issueDate);
            $document = SalesDocument::create([
                'type' => $targetType,
                'source_document_id' => $source->id,
                'person_id' => $source->person_id,
                'installation_id' => $source->installation_id,
                'document_series_id' => $issued['series']->id ?? null,
                'created_by' => $userId,
                'number' => $issued['number'] ?? 'DRAFT-INV-'.Str::upper(Str::random(8)),
                'status' => 'draft',
                'fulfillment_status' => $targetType === 'delivery_note' ? 'pending' : null,
                'billing_status' => $targetType === 'delivery_note' ? 'pending' : null,
                'issue_date' => $issueDate,
                'due_date' => $targetType === 'invoice' ? Carbon::parse($issueDate)->addDays(30)->toDateString() : null,
                ...array_diff_key($totals, ['lines' => true]),
                'customer_snapshot' => $source->customer_snapshot,
                'notes' => __('Converted from :number.', ['number' => $source->number]),
            ]);
            $document->lines()->createMany($totals['lines']);
            $source->update(['locked_at' => $source->locked_at ?: now()]);
            $this->refresh($source);

            return $document;
        });
    }

    public function refresh(SalesDocument $source): void
    {
        if (! in_array($source->type, ['order', 'delivery_note'], true)) {
            return;
        }
        $source->loadMissing('lines');
        $converted = $this->convertedQuantities($source);
        $delivered = $this->deliveredQuantities($source);
        $complete = function (string $type) use ($source, $converted): bool {
            return $source->lines->every(fn ($line) => (float) ($converted[$type][$line->id] ?? 0) >= (float) $line->quantity);
        };
        $partial = fn (string $type): bool => collect($converted[$type] ?? [])->sum() > 0;

        $updates = ['billing_status' => $complete('invoice') ? 'complete' : ($partial('invoice') ? 'partial' : 'pending')];
        if ($source->type === 'order') {
            $deliveryComplete = $source->lines->every(fn ($line) => (float) ($delivered[$line->id] ?? 0) >= (float) $line->quantity);
            $deliveryPartial = collect($delivered)->sum() > 0;
            $updates['fulfillment_status'] = $deliveryComplete ? 'complete' : ($deliveryPartial ? 'partial' : 'pending');
        }
        $source->update($updates);

        if ($source->type === 'delivery_note' && $source->sourceDocument?->type === 'order') {
            $this->refresh($source->sourceDocument);
        }
    }

    /** @return array<string, array<int, float>> */
    private function convertedQuantities(SalesDocument $source): array
    {
        $rows = SalesDocumentLine::query()
            ->join('sales_documents', 'sales_documents.id', '=', 'sales_document_lines.sales_document_id')
            ->whereIn('sales_document_lines.source_line_id', $source->lines->pluck('id'))
            ->whereIn('sales_documents.type', ['delivery_note', 'invoice'])
            ->groupBy('sales_documents.type', 'sales_document_lines.source_line_id')
            ->selectRaw('sales_documents.type, sales_document_lines.source_line_id, sum(sales_document_lines.quantity) as quantity')
            ->get();

        $converted = $rows->groupBy('type')->map(fn ($items) => $items->pluck('quantity', 'source_line_id')->map(fn ($quantity) => (float) $quantity)->all())->all();

        if ($source->type === 'order') {
            $nestedInvoices = SalesDocumentLine::query()
                ->from('sales_document_lines as invoice_lines')
                ->join('sales_documents as invoices', 'invoices.id', '=', 'invoice_lines.sales_document_id')
                ->join('sales_document_lines as delivery_lines', 'delivery_lines.id', '=', 'invoice_lines.source_line_id')
                ->where('invoices.type', 'invoice')
                ->whereIn('delivery_lines.source_line_id', $source->lines->pluck('id'))
                ->groupBy('delivery_lines.source_line_id')
                ->selectRaw('delivery_lines.source_line_id, sum(invoice_lines.quantity) as quantity')
                ->pluck('quantity', 'source_line_id');

            foreach ($nestedInvoices as $lineId => $quantity) {
                $converted['invoice'][$lineId] = (float) ($converted['invoice'][$lineId] ?? 0) + (float) $quantity;
            }
        }

        return $converted;
    }

    /** @return array<int, float> */
    private function deliveredQuantities(SalesDocument $source): array
    {
        if ($source->type !== 'order') {
            return [];
        }

        return SalesDocumentLine::query()
            ->join('sales_documents', 'sales_documents.id', '=', 'sales_document_lines.sales_document_id')
            ->whereIn('sales_document_lines.source_line_id', $source->lines->pluck('id'))
            ->where('sales_documents.type', 'delivery_note')
            ->where('sales_documents.status', 'delivered')
            ->groupBy('sales_document_lines.source_line_id')
            ->selectRaw('sales_document_lines.source_line_id, sum(sales_document_lines.quantity) as quantity')
            ->pluck('quantity', 'source_line_id')
            ->map(fn ($quantity) => (float) $quantity)
            ->all();
    }

    private function validateOrderInvoiceCapacity(SalesDocument $source, string $targetType, array $quantities): void
    {
        if ($source->type !== 'delivery_note' || $targetType !== 'invoice' || $source->sourceDocument?->type !== 'order') {
            return;
        }

        $order = $source->sourceDocument->loadMissing('lines');
        $alreadyInvoiced = $this->convertedQuantities($order)['invoice'] ?? [];
        $requested = $source->lines
            ->groupBy('source_line_id')
            ->map(fn ($lines) => $lines->sum(fn ($line) => (float) ($quantities[$line->id] ?? 0)));

        foreach ($requested as $orderLineId => $quantity) {
            $orderLine = $order->lines->firstWhere('id', $orderLineId);
            $remaining = max(0, (float) $orderLine?->quantity - (float) ($alreadyInvoiced[$orderLineId] ?? 0));
            if ($quantity > $remaining) {
                throw ValidationException::withMessages(['quantities' => __('The selected quantities exceed what remains to invoice on the original order.')]);
            }
        }
    }
}
