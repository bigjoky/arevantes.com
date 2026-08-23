<?php

namespace App\Services\Finance;

use App\Models\SalesDocument;
use Illuminate\Support\Collection;

class ReceivableRiskAnalysis
{
    public function __construct(private OutstandingBalanceCalculator $balances) {}

    /** @return array{summary: array<string, int|float>, rows: Collection<int, array<string, mixed>>} */
    public function build(?Collection $receivables = null, float $concentrationThreshold = 25): array
    {
        $receivables ??= SalesDocument::with(['customer:id,first_name,last_name,company_name', 'collectionAction'])
            ->where('type', 'invoice')
            ->whereIn('status', ['issued', 'partial', 'overdue'])
            ->get();

        $documents = $receivables->map(function (SalesDocument $document): array {
            $outstanding = $this->balances->receivable($document);
            $daysOverdue = $document->due_date?->isBefore(today())
                ? $document->due_date->diffInDays(today())
                : 0;
            $customerName = $document->customer?->company_name
                ?: trim(($document->customer?->first_name ?? '').' '.($document->customer?->last_name ?? ''))
                ?: data_get($document->customer_snapshot, 'company_name')
                ?: data_get($document->customer_snapshot, 'name')
                ?: __('Unassigned customer');

            return [
                'customerId' => $document->person_id,
                'customer' => $customerName,
                'id' => $document->id,
                'number' => $document->number,
                'dueDate' => $document->due_date?->toDateString(),
                'outstanding' => round($outstanding, 2),
                'daysOverdue' => $daysOverdue,
                'brokenPromise' => $document->collectionAction?->status === 'promised'
                    && $document->collectionAction->promised_on?->isBefore(today()),
                'href' => '/sales/'.$document->id,
            ];
        })->filter(fn (array $document): bool => $document['outstanding'] > 0)->values();

        $total = (float) $documents->sum('outstanding');
        $rows = $documents
            ->groupBy(fn (array $document): string => $document['customerId']
                ? 'customer:'.$document['customerId']
                : 'snapshot:'.mb_strtolower($document['customer']))
            ->map(function (Collection $customerDocuments) use ($total, $concentrationThreshold): array {
                $first = $customerDocuments->first();
                $outstanding = (float) $customerDocuments->sum('outstanding');
                $overdue = (float) $customerDocuments->where('daysOverdue', '>', 0)->sum('outstanding');
                $over90 = (float) $customerDocuments->where('daysOverdue', '>', 90)->sum('outstanding');
                $sharePercent = $total > 0 ? round($outstanding / $total * 100, 1) : 0.0;
                $overduePercent = $outstanding > 0 ? round($overdue / $outstanding * 100, 1) : 0.0;
                $brokenPromises = $customerDocuments->where('brokenPromise', true)->count();
                $isConcentrated = $sharePercent >= $concentrationThreshold;
                $riskLevel = $brokenPromises > 0 || $over90 > 0
                    ? 'critical'
                    : ($isConcentrated || $overdue > 0 ? 'warning' : 'normal');

                return [
                    'customerId' => $first['customerId'],
                    'customer' => $first['customer'],
                    'customerHref' => $first['customerId'] ? '/people/'.$first['customerId'] : null,
                    'invoiceCount' => $customerDocuments->count(),
                    'outstanding' => round($outstanding, 2),
                    'overdue' => round($overdue, 2),
                    'over90' => round($over90, 2),
                    'sharePercent' => $sharePercent,
                    'overduePercent' => $overduePercent,
                    'maxDaysOverdue' => (int) $customerDocuments->max('daysOverdue'),
                    'brokenPromises' => $brokenPromises,
                    'isConcentrated' => $isConcentrated,
                    'riskLevel' => $riskLevel,
                    'documents' => $customerDocuments->sortByDesc('daysOverdue')->values(),
                ];
            })
            ->sortByDesc(fn (array $row): float => ($row['riskLevel'] === 'critical' ? 1000000000 : ($row['riskLevel'] === 'warning' ? 100000000 : 0)) + $row['outstanding'])
            ->values();

        return [
            'summary' => [
                'totalExposure' => round($total, 2),
                'customers' => $rows->count(),
                'concentratedCustomers' => $rows->where('isConcentrated', true)->count(),
                'criticalCustomers' => $rows->where('riskLevel', 'critical')->count(),
                'overdueExposure' => round((float) $rows->sum('overdue'), 2),
                'brokenPromises' => (int) $rows->sum('brokenPromises'),
                'concentrationThreshold' => round($concentrationThreshold, 1),
            ],
            'rows' => $rows,
        ];
    }
}
