<?php

namespace App\Http\Controllers;

use App\Models\BankTransaction;
use App\Models\CrmActivity;
use App\Models\Expense;
use App\Models\MonitoringAlert;
use App\Models\PaymentBatch;
use App\Models\PaymentBatchItem;
use App\Models\PurchaseDocument;
use App\Models\PurchaseInboxDocument;
use App\Models\SalesDocument;
use App\Models\ServiceCorrectiveProposal;
use App\Models\WorkOrder;
use App\Services\AfterSales\RecurringServiceCorrectivePlanWorkflow;
use App\Services\Finance\FinancialAlertService;
use App\Services\Finance\OutstandingBalanceCalculator;
use App\Services\Work\TechnicianSchedule;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Gate;

class ActionCenterController extends Controller
{
    public function __construct(
        private OutstandingBalanceCalculator $balances,
        private FinancialAlertService $financialAlerts,
        private RecurringServiceCorrectivePlanWorkflow $correctiveWorkflow,
    ) {}

    public function __invoke(Request $request, TechnicianSchedule $schedule): JsonResponse
    {
        $groups = collect();

        if (Gate::allows('view-installations')) {
            $groups->push($this->monitoring());
        }

        if (Gate::allows('manage-operations')) {
            $groups->push($this->operations($request, $schedule));
        }

        if (Gate::allows('manage-purchases')) {
            $groups->push($this->supply(), $this->purchaseInbox());
        }

        $improvementTypes = $this->correctiveWorkflow->decisionTypesFor($request->user());
        if ($improvementTypes !== []) {
            $groups->push($this->improvementDecisions($improvementTypes));
        }

        if (Gate::allows('approve-purchase-orders')) {
            $groups->push($this->purchaseApprovals());
        }

        if (Gate::allows('view-finance')) {
            $groups->push($this->collections($request), $this->payments(), $this->paymentBatches(), $this->banking(), $this->financialAlertsGroup());
        }

        $groups = $groups->filter(fn (array $group) => $group['count'] > 0)->values();

        return response()->json([
            'total' => $groups->sum('count'),
            'groups' => $groups,
            'generated_at' => now()->toIso8601String(),
        ]);
    }

    /** @param list<string> $types @return array<string, mixed> */
    private function improvementDecisions(array $types): array
    {
        $query = ServiceCorrectiveProposal::query()
            ->whereIn('type', $types)
            ->whereIn('status', ['prepared', 'returned']);
        $items = (clone $query)
            ->with('plan:id,code,title')
            ->orderByRaw("case status when 'prepared' then 0 else 1 end")
            ->oldest('created_at')
            ->limit(10)
            ->get()
            ->map(fn (ServiceCorrectiveProposal $proposal): array => [
                'id' => 'improvement-proposal-'.$proposal->id,
                'title' => $proposal->title,
                'subtitle' => $proposal->plan->code.' · '.($proposal->status === 'returned' ? __('Returned for revision') : __('Pending decision')),
                'href' => '/improvement-decisions?status=open#proposal-'.$proposal->id,
                'severity' => $proposal->status === 'returned' ? 'critical' : 'warning',
                'due_at' => $proposal->created_at->toIso8601String(),
            ]);

        return $this->group(__('Improvement decisions'), (clone $query)->count(), $items);
    }

    /** @return array<string, mixed> */
    private function supply(): array
    {
        $query = PurchaseDocument::query()
            ->where('type', 'purchase_order')
            ->whereIn('status', ['ordered', 'partially_received'])
            ->where(function ($query) {
                $query->where(function ($query) {
                    $query->where(fn ($query) => $query
                        ->whereNotNull('supplier_confirmed_delivery_date')
                        ->whereDate('supplier_confirmed_delivery_date', '<', today()))
                        ->orWhere(fn ($query) => $query
                            ->whereNull('supplier_confirmed_delivery_date')
                            ->whereDate('expected_delivery_date', '<', today()));
                })
                    ->orWhereDate('next_follow_up_date', '<=', today())
                    ->orWhere(fn ($query) => $query
                        ->whereNull('supplier_confirmed_at')
                        ->whereDate('issue_date', '<=', today()->subDays(2)));
            });

        $items = (clone $query)
            ->with(['supplier:id,legal_name,trade_name', 'assignee:id,name'])
            ->limit(20)
            ->get()
            ->map(function (PurchaseDocument $order): array {
                $supplier = $order->supplier?->trade_name ?: $order->supplier?->legal_name ?: __('Supplier');
                $owner = $order->assignee?->name ?: __('Unassigned');
                $eta = $order->supplier_confirmed_delivery_date ?: $order->expected_delivery_date;
                if ($eta?->lt(today())) {
                    $daysLate = abs(today()->diffInDays($eta, false));

                    return [
                        'id' => 'supply-'.$order->id,
                        'title' => $order->number.' · '.$supplier,
                        'subtitle' => __('Delivery is :days day(s) late · Owner: :owner', ['days' => $daysLate, 'owner' => $owner]),
                        'href' => '/purchases/'.$order->id,
                        'severity' => 'critical',
                        'due_at' => $eta->startOfDay()->toIso8601String(),
                    ];
                }

                if ($order->next_follow_up_date?->lte(today())) {
                    return [
                        'id' => 'supply-'.$order->id,
                        'title' => $order->number.' · '.$supplier,
                        'subtitle' => __('Supplier follow-up is due · Owner: :owner', ['owner' => $owner]),
                        'href' => '/purchases/'.$order->id,
                        'severity' => $order->next_follow_up_date->lt(today()) ? 'critical' : 'warning',
                        'due_at' => $order->next_follow_up_date->startOfDay()->toIso8601String(),
                    ];
                }

                $dueAt = $order->issue_date->copy()->addDays(2)->startOfDay();

                return [
                    'id' => 'supply-'.$order->id,
                    'title' => $order->number.' · '.$supplier,
                    'subtitle' => __('Supplier confirmation is still pending · Owner: :owner', ['owner' => $owner]),
                    'href' => '/purchases/'.$order->id,
                    'severity' => 'warning',
                    'due_at' => $dueAt->toIso8601String(),
                ];
            })
            ->sortBy('due_at')
            ->take(10)
            ->values();

        return $this->group(__('Supply'), (clone $query)->count(), $items);
    }

    /** @return array<string, mixed> */
    private function purchaseApprovals(): array
    {
        $query = PurchaseDocument::query()
            ->where('type', 'purchase_order')
            ->where('status', 'pending_approval');
        $items = (clone $query)
            ->with(['supplier:id,legal_name,trade_name', 'orderApprovals' => fn ($query) => $query->where('status', 'pending')])
            ->oldest('created_at')
            ->limit(10)
            ->get()
            ->map(function (PurchaseDocument $order): array {
                $supplier = $order->supplier?->trade_name ?: $order->supplier?->legal_name ?: __('Supplier');

                return [
                    'id' => 'purchase-approval-'.$order->id,
                    'title' => $order->number.' · '.$supplier,
                    'subtitle' => __(':count approval(s) pending · :amount', ['count' => $order->orderApprovals->count(), 'amount' => number_format((float) $order->amount, 2, ',', '.').' €']),
                    'href' => '/purchases/'.$order->id,
                    'severity' => $order->created_at->lt(now()->subDay()) ? 'critical' : 'warning',
                    'due_at' => $order->created_at->addDay()->toIso8601String(),
                ];
            });

        return $this->group(__('Purchase approvals'), (clone $query)->count(), $items);
    }

    /** @return array<string, mixed> */
    private function monitoring(): array
    {
        $query = MonitoringAlert::query()->whereNull('resolved_at');
        $items = (clone $query)
            ->with('installation:id,project_reference,site_name')
            ->latest('last_seen_at')
            ->limit(8)
            ->get()
            ->map(fn (MonitoringAlert $alert) => [
                'id' => 'monitoring-'.$alert->id,
                'title' => $alert->installation?->project_reference ?: $alert->installation?->site_name ?: __('Installation alert'),
                'subtitle' => $alert->message,
                'href' => '/installations/'.$alert->installation_id,
                'severity' => $alert->status === 'fault' ? 'critical' : 'warning',
                'due_at' => $alert->last_seen_at?->toIso8601String(),
            ]);

        return $this->group(__('Monitoring'), (clone $query)->count(), $items);
    }

    /** @return array<string, mixed> */
    private function operations(Request $request, TechnicianSchedule $schedule): array
    {
        $activityQuery = CrmActivity::query()
            ->whereNull('work_order_id')
            ->whereIn('status', ['pending', 'in_progress'])
            ->where(fn ($query) => $query->whereNull('assigned_user_id')->orWhere('assigned_user_id', $request->user()->id))
            ->where(fn ($query) => $query->whereNull('due_at')->orWhere('due_at', '<=', now()->addDays(14)));

        $workOrderQuery = WorkOrder::query()
            ->whereNotIn('status', ['completed', 'cancelled'])
            ->where(fn ($query) => $query->whereNull('scheduled_start')->orWhere('scheduled_start', '<=', now()->addDays(14)));

        if ($request->user()->hasRole('technician')) {
            $workOrderQuery->where('assigned_user_id', $request->user()->id);
        }

        $activities = (clone $activityQuery)
            ->orderByRaw('case when due_at is null then 1 else 0 end')
            ->orderBy('due_at')
            ->limit(6)
            ->get()
            ->map(fn (CrmActivity $activity) => [
                'id' => 'activity-'.$activity->id,
                'title' => $activity->title,
                'subtitle' => __('Activity').' · '.($activity->due_at?->format('d/m/Y H:i') ?: __('No due date')),
                'href' => '/operations?focus='.$activity->id,
                'severity' => $this->dateSeverity($activity->due_at),
                'due_at' => $activity->due_at?->toIso8601String(),
            ]);

        $workOrderItems = (clone $workOrderQuery)
            ->orderByRaw('case when scheduled_start is null then 1 else 0 end')
            ->orderBy('scheduled_start')
            ->limit(6)
            ->get();
        $conflicts = $schedule->conflictIds($workOrderItems);
        $workOrders = $workOrderItems->map(fn (WorkOrder $workOrder) => [
            'id' => 'work-order-'.$workOrder->id,
            'title' => $workOrder->number.' · '.$workOrder->title,
            'subtitle' => (isset($conflicts[$workOrder->id]) ? __('Schedule conflict').' · ' : __('Work order').' · ').($workOrder->scheduled_start?->format('d/m/Y H:i') ?: __('Unscheduled')),
            'href' => '/work-orders?work_order='.$workOrder->id,
            'severity' => isset($conflicts[$workOrder->id]) ? 'critical' : $this->dateSeverity($workOrder->scheduled_start),
            'due_at' => $workOrder->scheduled_start?->toIso8601String(),
        ]);

        $items = $activities->concat($workOrders)->sortBy(fn (array $item) => $item['due_at'] ?? '9999')->take(10)->values();

        return $this->group(__('Operations'), (clone $activityQuery)->count() + (clone $workOrderQuery)->count(), $items);
    }

    /** @return array<string, mixed> */
    private function collections(Request $request): array
    {
        $query = SalesDocument::query()
            ->where('type', 'invoice')
            ->whereIn('status', ['issued', 'partial', 'overdue'])
            ->where(function ($query) {
                $query->where(function ($query) {
                    $query->whereDoesntHave('collectionAction')
                        ->where(fn ($query) => $query->whereNull('due_date')->orWhere('due_date', '<=', now()->addDays(14)));
                })->orWhereHas('collectionAction', fn ($query) => $query
                    ->whereNot('status', 'resolved')
                    ->where(fn ($query) => $query
                        ->whereNull('next_action_on')
                        ->orWhere('next_action_on', '<=', now()->addDays(14))
                        ->orWhere(fn ($query) => $query->where('status', 'promised')->where('promised_on', '<', today()))));
            });

        if (! $request->user()->hasRole('admin')) {
            $query->where(fn ($query) => $query
                ->whereDoesntHave('collectionAction')
                ->orWhereHas('collectionAction', fn ($query) => $query
                    ->whereNull('assigned_to')
                    ->orWhere('assigned_to', $request->user()->id)));
        }

        $items = (clone $query)
            ->with(['customer:id,first_name,last_name,company_name', 'collectionAction'])
            ->limit(20)
            ->get()
            ->map(function (SalesDocument $document) {
                $action = $document->collectionAction;
                $actionDate = $action?->next_action_on ?: $document->due_date;
                $promiseBreached = $action?->status === 'promised' && $action->promised_on?->isBefore(today());
                $detail = $action
                    ? __(':status · next action :date', [
                        'status' => $promiseBreached ? __('Broken promise') : ucfirst($action->status),
                        'date' => $action->next_action_on?->format('d/m/Y') ?: __('Not scheduled'),
                    ])
                    : __('Due :date', ['date' => $document->due_date?->format('d/m/Y') ?: __('No due date')]);

                return [
                    'id' => 'sale-'.$document->id,
                    'title' => $document->number.' · '.$this->customerName($document),
                    'subtitle' => __('Collect :amount', ['amount' => $this->money($this->balances->receivable($document))]).' · '.$detail,
                    'href' => '/finance?year='.$document->issue_date->year,
                    'severity' => $action?->status === 'disputed' || $promiseBreached ? 'critical' : $this->dateSeverity($actionDate),
                    'due_at' => $actionDate?->toIso8601String(),
                ];
            })
            ->sortBy(fn (array $item) => $item['due_at'] ?? '9999')
            ->take(8)
            ->values();

        return $this->group(__('Collections'), (clone $query)->count(), $items);
    }

    /** @return array<string, mixed> */
    private function payments(): array
    {
        $batchedInvoices = PaymentBatchItem::query()
            ->where('payable_type', 'invoice')
            ->whereHas('batch', fn ($query) => $query->whereIn('status', ['draft', 'approved', 'exported']))
            ->pluck('payable_id');
        $batchedExpenses = PaymentBatchItem::query()
            ->where('payable_type', 'expense')
            ->whereHas('batch', fn ($query) => $query->whereIn('status', ['draft', 'approved', 'exported']))
            ->pluck('payable_id');
        $purchasesQuery = PurchaseDocument::query()
            ->withSum('creditNotes as credited_total', 'amount')
            ->where('type', 'supplier_invoice')
            ->whereIn('status', ['registered', 'pending', 'partial', 'overdue'])
            ->whereNotIn('id', $batchedInvoices)
            ->where(fn ($query) => $query->whereNull('due_date')->orWhere('due_date', '<=', now()->addDays(14)));
        $expensesQuery = Expense::query()
            ->whereIn('status', ['pending', 'partial', 'overdue'])
            ->whereNotIn('id', $batchedExpenses)
            ->where(fn ($query) => $query->whereNull('due_date')->orWhere('due_date', '<=', now()->addDays(14)));

        $purchases = (clone $purchasesQuery)->with('supplier:id,legal_name,trade_name')->orderBy('due_date')->limit(6)->get()
            ->map(fn (PurchaseDocument $document) => [
                'id' => 'purchase-'.$document->id,
                'title' => $document->number.' · '.($document->supplier?->trade_name ?: $document->supplier?->legal_name ?: __('Supplier')),
                'subtitle' => __('Pay :amount · due :date', [
                    'amount' => $this->money($this->balances->purchase($document)),
                    'date' => $document->due_date?->format('d/m/Y') ?: __('No due date'),
                ]),
                'href' => '/purchases/'.$document->id,
                'severity' => $this->dateSeverity($document->due_date),
                'due_at' => $document->due_date?->toIso8601String(),
            ]);
        $expenses = (clone $expensesQuery)->orderBy('due_date')->limit(6)->get()
            ->map(fn (Expense $expense) => [
                'id' => 'expense-'.$expense->id,
                'title' => $expense->number.' · '.($expense->merchant_name ?: __('Expense')),
                'subtitle' => __('Pay :amount · due :date', [
                    'amount' => $this->money($this->balances->expense($expense)),
                    'date' => $expense->due_date?->format('d/m/Y') ?: __('No due date'),
                ]),
                'href' => '/expenses/'.$expense->id,
                'severity' => $this->dateSeverity($expense->due_date),
                'due_at' => $expense->due_date?->toIso8601String(),
            ]);
        $items = $purchases->concat($expenses)->sortBy(fn (array $item) => $item['due_at'] ?? '9999')->take(10)->values();

        return $this->group(__('Payments'), (clone $purchasesQuery)->count() + (clone $expensesQuery)->count(), $items);
    }

    /** @return array<string, mixed> */
    private function paymentBatches(): array
    {
        $query = PaymentBatch::query()->where(function ($query) {
            $query->where('status', 'draft')
                ->orWhere(fn ($query) => $query->whereIn('status', ['approved', 'exported'])->where('planned_on', '<=', today()->addDays(14)));
        });
        $items = (clone $query)
            ->with(['account:id,name', 'items'])
            ->orderByRaw("case when status = 'draft' then 0 else 1 end")
            ->orderBy('planned_on')
            ->limit(8)
            ->get()
            ->map(fn (PaymentBatch $batch) => [
                'id' => 'payment-batch-'.$batch->id,
                'title' => $batch->number.' · '.match ($batch->status) {
                    'draft' => __('Approval pending'),
                    'exported' => __('Payment confirmation pending'),
                    default => __('Ready for payment'),
                },
                'subtitle' => __(':amount · planned :date · :account', [
                    'amount' => $this->money((float) $batch->items->sum('amount')),
                    'date' => $batch->planned_on->format('d/m/Y'),
                    'account' => $batch->account?->name ?: __('Default account'),
                ]),
                'href' => '/finance?year='.$batch->planned_on->year,
                'severity' => $batch->status === 'exported' && $batch->planned_on->isPast()
                    ? 'critical'
                    : ($batch->status === 'draft' ? 'warning' : $this->dateSeverity($batch->planned_on)),
                'due_at' => $batch->planned_on->toIso8601String(),
            ]);

        return $this->group(__('Payment batches'), (clone $query)->count(), $items);
    }

    /** @return array<string, mixed> */
    private function purchaseInbox(): array
    {
        $query = PurchaseInboxDocument::query()->whereIn('status', ['pending_review', 'ready']);
        $items = (clone $query)->with('supplier:id,legal_name,trade_name')->oldest()->limit(8)->get()
            ->map(fn (PurchaseInboxDocument $document) => [
                'id' => 'purchase-inbox-'.$document->id,
                'title' => $document->original_name,
                'subtitle' => $document->status === 'ready'
                    ? __('Ready to convert · :supplier', ['supplier' => $document->supplier?->trade_name ?: $document->supplier?->legal_name ?: __('Supplier')])
                    : __('Supplier document awaiting review'),
                'href' => '/purchase-inbox/'.$document->id,
                'severity' => $document->status === 'ready' ? 'info' : 'warning',
                'due_at' => $document->created_at?->toIso8601String(),
            ]);

        return $this->group(__('Document inbox'), (clone $query)->count(), $items);
    }

    /** @return array<string, mixed> */
    private function banking(): array
    {
        $query = BankTransaction::query()->where('status', 'pending');
        $items = (clone $query)->with('account:id,name')->latest('booked_on')->limit(8)->get()
            ->map(fn (BankTransaction $transaction) => [
                'id' => 'bank-'.$transaction->id,
                'title' => $transaction->description ?: __('Bank movement'),
                'subtitle' => ($transaction->account?->name ?: __('Bank')).' · '.$this->money((float) $transaction->amount),
                'href' => '/banking?account='.$transaction->bank_account_id,
                'severity' => 'neutral',
                'due_at' => $transaction->booked_on?->toIso8601String(),
            ]);

        return $this->group(__('Bank reconciliation'), (clone $query)->count(), $items);
    }

    /** @return array<string, mixed> */
    private function financialAlertsGroup(): array
    {
        $year = now()->year;
        $alerts = $this->financialAlerts->forYear($year)->where('status', '!=', 'resolved')->values();
        $items = $alerts->map(fn (array $alert, int $index) => [
            'id' => 'financial-alert-'.$alert['type'].'-'.($alert['month'] ?? 'forecast').'-'.$index,
            'title' => match ($alert['type']) {
                'operating' => ($alert['monthLabel'].' · ').__('Operating result'),
                'cash' => ($alert['monthLabel'].' · ').__('Net cash'),
                'concentration' => __('Receivables concentration'),
                default => __('Liquidity buffer'),
            },
            'subtitle' => $alert['message'].' '.$this->money(abs((float) $alert['variance'])),
            'href' => $alert['href'] ?? '/finance?year='.$year.($alert['month'] ? '#budget-month-'.$alert['month'] : '#financial-alerts'),
            'severity' => $alert['severity'],
            'due_at' => now()->toIso8601String(),
        ]);

        return $this->group(__('Financial alerts'), $alerts->count(), $items);
    }

    /** @param Collection<int, array<string, mixed>> $items
     * @return array<string, mixed>
     */
    private function group(string $label, int $count, Collection $items): array
    {
        return ['label' => $label, 'count' => $count, 'items' => $items->values()];
    }

    private function dateSeverity(mixed $date): string
    {
        if (! $date) {
            return 'neutral';
        }

        return $date->isPast() ? 'critical' : ($date->isToday() || $date->isTomorrow() ? 'warning' : 'neutral');
    }

    private function customerName(SalesDocument $document): string
    {
        return $document->customer?->company_name
            ?: trim(($document->customer?->first_name ?? '').' '.($document->customer?->last_name ?? ''))
            ?: __('Customer');
    }

    private function money(float $amount): string
    {
        return number_format($amount, 2, ',', '.').' EUR';
    }
}
