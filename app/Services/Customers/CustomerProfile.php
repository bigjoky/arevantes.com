<?php

namespace App\Services\Customers;

use App\Models\CrmActivity;
use App\Models\DocumentDelivery;
use App\Models\Opportunity;
use App\Models\Person;
use App\Models\Quote;
use App\Models\SalesDocument;
use App\Models\SalesDocumentPayment;
use App\Models\ServiceTicket;
use App\Models\WorkOrder;
use Illuminate\Support\Collection;

class CustomerProfile
{
    /** @return array<string, mixed> */
    public function build(Person $customer): array
    {
        $quotes = Quote::query()
            ->with('installation:id,project_reference,site_name')
            ->where('person_id', $customer->id)
            ->latest('issue_date')
            ->latest('id')
            ->limit(12)
            ->get();
        $documents = SalesDocument::query()
            ->where('person_id', $customer->id)
            ->latest('issue_date')
            ->latest('id')
            ->limit(12)
            ->get();
        $activities = CrmActivity::query()
            ->with('installation:id,project_reference,site_name')
            ->where(fn ($query) => $query
                ->where('person_id', $customer->id)
                ->orWhereHas('installation', fn ($installation) => $installation->where('person_id', $customer->id)))
            ->latest()
            ->limit(12)
            ->get();
        $workOrders = WorkOrder::query()
            ->with('installation:id,project_reference,site_name')
            ->whereHas('installation', fn ($query) => $query->where('person_id', $customer->id))
            ->latest()
            ->limit(12)
            ->get();
        $opportunities = Opportunity::query()
            ->where('person_id', $customer->id)
            ->latest()
            ->limit(12)
            ->get();
        $payments = SalesDocumentPayment::query()
            ->with('document:id,person_id,number')
            ->whereHas('document', fn ($query) => $query->where('person_id', $customer->id))
            ->latest('paid_at')
            ->limit(12)
            ->get();
        $tickets = ServiceTicket::query()
            ->with('installation:id,person_id,project_reference,site_name')
            ->whereHas('installation', fn ($query) => $query->where('person_id', $customer->id))
            ->latest()
            ->limit(12)
            ->get();
        $communications = DocumentDelivery::query()
            ->with(['quote:id,person_id,number', 'salesDocument:id,person_id,number'])
            ->where(fn ($query) => $query
                ->whereHas('quote', fn ($quote) => $quote->where('person_id', $customer->id))
                ->orWhereHas('salesDocument', fn ($document) => $document->where('person_id', $customer->id)))
            ->latest()
            ->limit(12)
            ->get();

        $invoices = SalesDocument::query()->where('person_id', $customer->id)->where('type', 'invoice');

        return [
            'metrics' => [
                'billed' => round((float) (clone $invoices)->sum('amount'), 2),
                'outstanding' => round((float) (clone $invoices)->whereIn('status', ['issued', 'partial', 'overdue'])->selectRaw('coalesce(sum(amount - paid_amount), 0) as total')->value('total'), 2),
                'open_activities' => CrmActivity::query()
                    ->where(fn ($query) => $query
                        ->where('person_id', $customer->id)
                        ->orWhereHas('installation', fn ($installation) => $installation->where('person_id', $customer->id)))
                    ->whereIn('status', ['pending', 'in_progress'])
                    ->count(),
                'active_work_orders' => WorkOrder::query()
                    ->whereHas('installation', fn ($query) => $query->where('person_id', $customer->id))
                    ->whereNotIn('status', ['completed', 'cancelled'])
                    ->count(),
                'open_pipeline' => round((float) Opportunity::query()
                    ->where('person_id', $customer->id)
                    ->whereNotIn('stage', ['won', 'lost'])
                    ->sum('estimated_value'), 2),
                'open_tickets' => ServiceTicket::query()
                    ->whereHas('installation', fn ($query) => $query->where('person_id', $customer->id))
                    ->whereNotIn('status', ['resolved', 'closed', 'cancelled'])
                    ->count(),
                'collected' => round((float) SalesDocumentPayment::query()
                    ->whereHas('document', fn ($query) => $query->where('person_id', $customer->id))
                    ->sum('amount'), 2),
            ],
            'quotes' => $quotes->map(fn (Quote $quote) => [
                'id' => $quote->id,
                'number' => $quote->number,
                'status' => $quote->status,
                'amount' => (float) $quote->amount,
                'date' => $quote->issue_date?->toDateString(),
                'installation' => $quote->installation?->project_reference ?: $quote->installation?->site_name,
            ]),
            'documents' => $documents->map(fn (SalesDocument $document) => [
                'id' => $document->id,
                'number' => $document->number,
                'type' => $document->type,
                'status' => $document->status,
                'amount' => (float) $document->amount,
                'outstanding' => max(0, round((float) $document->amount - (float) $document->paid_amount, 2)),
                'date' => $document->issue_date?->toDateString(),
            ]),
            'timeline' => $this->timeline($quotes, $documents, $activities, $workOrders, $opportunities, $payments, $tickets, $communications),
        ];
    }

    /** @return Collection<int, array<string, mixed>> */
    private function timeline(Collection $quotes, Collection $documents, Collection $activities, Collection $workOrders, Collection $opportunities, Collection $payments, Collection $tickets, Collection $communications): Collection
    {
        $items = collect();

        $quotes->each(fn (Quote $quote) => $items->push([
            'id' => 'quote-'.$quote->id,
            'kind' => 'quote',
            'title' => __('Quote :number', ['number' => $quote->number]),
            'subtitle' => $quote->status.' · '.number_format((float) $quote->amount, 2, ',', '.').' EUR',
            'date' => ($quote->sent_at ?? $quote->created_at)?->toIso8601String(),
            'href' => route('quotes.show', $quote, false),
        ]));
        $documents->each(fn (SalesDocument $document) => $items->push([
            'id' => 'sale-'.$document->id,
            'kind' => 'document',
            'title' => ucfirst(str_replace('_', ' ', $document->type)).' '.$document->number,
            'subtitle' => $document->status.' · '.number_format((float) $document->amount, 2, ',', '.').' EUR',
            'date' => ($document->issued_at ?? $document->created_at)?->toIso8601String(),
            'href' => route('sales-documents.show', $document, false),
        ]));
        $activities->each(fn (CrmActivity $activity) => $items->push([
            'id' => 'activity-'.$activity->id,
            'kind' => 'activity',
            'title' => $activity->title,
            'subtitle' => $activity->type.' · '.$activity->status,
            'date' => ($activity->completed_at ?? $activity->created_at)?->toIso8601String(),
            'href' => route('operations.index', ['focus' => $activity->id], false),
        ]));
        $workOrders->each(fn (WorkOrder $workOrder) => $items->push([
            'id' => 'work-order-'.$workOrder->id,
            'kind' => 'work_order',
            'title' => $workOrder->number.' · '.$workOrder->title,
            'subtitle' => $workOrder->status.' · '.($workOrder->installation?->project_reference ?: $workOrder->installation?->site_name ?: __('Installation')),
            'date' => ($workOrder->completed_at ?? $workOrder->scheduled_start ?? $workOrder->created_at)?->toIso8601String(),
            'href' => route('work-orders.index', ['work_order' => $workOrder->id], false),
        ]));
        $opportunities->each(fn (Opportunity $opportunity) => $items->push([
            'id' => 'opportunity-'.$opportunity->id,
            'kind' => 'opportunity',
            'title' => $opportunity->code.' · '.$opportunity->title,
            'subtitle' => $opportunity->stage.' · '.number_format((float) $opportunity->estimated_value, 2, ',', '.').' EUR · '.$opportunity->probability.'%',
            'date' => $opportunity->updated_at?->toIso8601String(),
            'href' => route('opportunities.index', ['opportunity' => $opportunity->id], false),
        ]));
        $payments->each(fn (SalesDocumentPayment $payment) => $items->push([
            'id' => 'payment-'.$payment->id,
            'kind' => 'payment',
            'title' => __('Payment for :number', ['number' => $payment->document?->number]),
            'subtitle' => number_format((float) $payment->amount, 2, ',', '.').' EUR · '.$payment->method,
            'date' => $payment->paid_at?->toIso8601String(),
            'href' => $payment->document ? route('sales-documents.show', $payment->document, false) : route('finance.index', [], false),
        ]));
        $tickets->each(fn (ServiceTicket $ticket) => $items->push([
            'id' => 'ticket-'.$ticket->id,
            'kind' => 'ticket',
            'title' => $ticket->number.' · '.$ticket->title,
            'subtitle' => $ticket->priority.' · '.$ticket->status,
            'date' => ($ticket->resolved_at ?? $ticket->created_at)?->toIso8601String(),
            'href' => route('after-sales.index', [], false),
        ]));
        $communications->each(function (DocumentDelivery $delivery) use ($items): void {
            $document = $delivery->quote ?? $delivery->salesDocument;
            $items->push([
                'id' => 'communication-'.$delivery->id,
                'kind' => 'communication',
                'title' => __('Commercial communication to :email', ['email' => $delivery->recipient_email]),
                'subtitle' => ($document?->number ?? __('Document')).' · '.$delivery->status,
                'date' => ($delivery->responded_at ?? $delivery->sent_at ?? $delivery->created_at)?->toIso8601String(),
                'href' => $delivery->quote
                    ? route('quotes.show', $delivery->quote, false)
                    : ($delivery->salesDocument ? route('sales-documents.show', $delivery->salesDocument, false) : route('people.index', [], false)),
            ]);
        });

        return $items->sortByDesc('date')->take(20)->values();
    }
}
