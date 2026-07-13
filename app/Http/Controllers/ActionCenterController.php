<?php

namespace App\Http\Controllers;

use App\Models\BankTransaction;
use App\Models\CrmActivity;
use App\Models\Expense;
use App\Models\MonitoringAlert;
use App\Models\PurchaseDocument;
use App\Models\PurchaseInboxDocument;
use App\Models\SalesDocument;
use App\Models\WorkOrder;
use App\Services\Work\TechnicianSchedule;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Gate;

class ActionCenterController extends Controller
{
    public function __invoke(Request $request, TechnicianSchedule $schedule): JsonResponse
    {
        $groups = collect();

        if (Gate::allows('view-installations')) {
            $groups->push($this->monitoring());
        }

        if (Gate::allows('manage-operations')) {
            $groups->push($this->operations($request, $schedule));
        }

        if (Gate::allows('manage-catalog')) {
            $groups->push($this->purchaseInbox(), $this->collections(), $this->payments(), $this->banking());
        }

        $groups = $groups->filter(fn (array $group) => $group['count'] > 0)->values();

        return response()->json([
            'total' => $groups->sum('count'),
            'groups' => $groups,
            'generated_at' => now()->toIso8601String(),
        ]);
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
    private function collections(): array
    {
        $query = SalesDocument::query()
            ->where('type', 'invoice')
            ->whereIn('status', ['issued', 'partial', 'overdue'])
            ->where(fn ($query) => $query->whereNull('due_date')->orWhere('due_date', '<=', now()->addDays(14)));

        $items = (clone $query)->with('customer:id,first_name,last_name,company_name')->orderBy('due_date')->limit(8)->get()
            ->map(fn (SalesDocument $document) => [
                'id' => 'sale-'.$document->id,
                'title' => $document->number.' · '.$this->customerName($document),
                'subtitle' => __('Collect :amount · due :date', [
                    'amount' => $this->money((float) $document->amount - (float) $document->paid_amount),
                    'date' => $document->due_date?->format('d/m/Y') ?: __('No due date'),
                ]),
                'href' => '/sales/'.$document->id,
                'severity' => $this->dateSeverity($document->due_date),
                'due_at' => $document->due_date?->toIso8601String(),
            ]);

        return $this->group(__('Collections'), (clone $query)->count(), $items);
    }

    /** @return array<string, mixed> */
    private function payments(): array
    {
        $purchasesQuery = PurchaseDocument::query()
            ->withSum('creditNotes as credited_total', 'amount')
            ->where('type', 'supplier_invoice')
            ->whereIn('status', ['registered', 'pending', 'partial', 'overdue'])
            ->where(fn ($query) => $query->whereNull('due_date')->orWhere('due_date', '<=', now()->addDays(14)));
        $expensesQuery = Expense::query()
            ->whereIn('status', ['pending', 'partial', 'overdue'])
            ->where(fn ($query) => $query->whereNull('due_date')->orWhere('due_date', '<=', now()->addDays(14)));

        $purchases = (clone $purchasesQuery)->with('supplier:id,legal_name,trade_name')->orderBy('due_date')->limit(6)->get()
            ->map(fn (PurchaseDocument $document) => [
                'id' => 'purchase-'.$document->id,
                'title' => $document->number.' · '.($document->supplier?->trade_name ?: $document->supplier?->legal_name ?: __('Supplier')),
                'subtitle' => __('Pay :amount · due :date', [
                    'amount' => $this->money((float) $document->amount + (float) $document->credited_total - (float) $document->paid_amount),
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
                    'amount' => $this->money((float) $expense->amount - (float) $expense->paid_amount),
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
