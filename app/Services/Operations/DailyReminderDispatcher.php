<?php

namespace App\Services\Operations;

use App\Models\ActionReminder;
use App\Models\BankTransaction;
use App\Models\CrmActivity;
use App\Models\Expense;
use App\Models\PaymentBatch;
use App\Models\PaymentBatchItem;
use App\Models\PurchaseDocument;
use App\Models\SalesDocument;
use App\Models\User;
use App\Models\WorkOrder;
use App\Notifications\ActionSummaryNotification;
use App\Services\Finance\FinancialAlertService;
use Illuminate\Support\Collection;

class DailyReminderDispatcher
{
    public function __construct(private FinancialAlertService $financialAlerts) {}

    public function dispatch(): int
    {
        $sent = 0;
        $operations = $this->operationCounts();

        User::query()->whereIn('role', ['admin', 'commercial', 'technician'])->each(function (User $user) use ($operations, &$sent) {
            $count = (int) ($operations[$user->id] ?? 0);
            if ($count > 0 && $this->notify($user, 'operations', $count, route('operations.index'))) {
                $sent++;
            }
        });

        $batchedInvoices = PaymentBatchItem::query()
            ->where('payable_type', 'invoice')
            ->whereHas('batch', fn ($query) => $query->whereIn('status', ['draft', 'approved', 'exported']))
            ->pluck('payable_id');
        $batchedExpenses = PaymentBatchItem::query()
            ->where('payable_type', 'expense')
            ->whereHas('batch', fn ($query) => $query->whereIn('status', ['draft', 'approved', 'exported']))
            ->pluck('payable_id');
        $budgetAlerts = $this->financialAlerts->forYear(now()->year)->where('status', '!=', 'resolved');

        $financial = [
            'collections' => [
                SalesDocument::query()
                    ->where('type', 'invoice')
                    ->whereIn('status', ['issued', 'partial', 'overdue'])
                    ->where(function ($query) {
                        $query->where(function ($query) {
                            $query->whereDoesntHave('collectionAction')
                                ->whereDate('due_date', '<=', today()->addDays(3));
                        })->orWhereHas('collectionAction', fn ($query) => $query
                            ->whereNot('status', 'resolved')
                            ->where(fn ($query) => $query
                                ->whereNull('next_action_on')
                                ->orWhereDate('next_action_on', '<=', today()->addDays(3))
                                ->orWhere(fn ($query) => $query->where('status', 'promised')->whereDate('promised_on', '<', today()))));
                    })
                    ->count(),
                route('finance.index'),
            ],
            'payments' => [
                PurchaseDocument::query()
                    ->where('type', 'supplier_invoice')
                    ->whereIn('status', ['registered', 'pending', 'partial', 'overdue'])
                    ->whereNotIn('id', $batchedInvoices)
                    ->whereDate('due_date', '<=', today()->addDays(3))
                    ->count()
                    + Expense::query()
                        ->whereIn('status', ['pending', 'partial', 'overdue'])
                        ->whereNotIn('id', $batchedExpenses)
                        ->whereDate('due_date', '<=', today()->addDays(3))
                        ->count()
                    + PaymentBatch::query()
                        ->where(fn ($query) => $query
                            ->where('status', 'draft')
                            ->orWhere(fn ($query) => $query->whereIn('status', ['approved', 'exported'])->whereDate('planned_on', '<=', today()->addDays(3))))
                        ->count(),
                route('finance.index'),
            ],
            'banking' => [
                BankTransaction::query()->where('status', 'pending')->whereDate('booked_on', '<=', today()->subDays(2))->count(),
                route('banking.index'),
            ],
            'financial_alerts' => [
                $budgetAlerts->count(),
                route('finance.index', ['year' => now()->year]).'#financial-alerts',
            ],
        ];

        User::query()->whereIn('role', ['admin', 'commercial'])->each(function (User $user) use ($financial, &$sent) {
            foreach ($financial as $category => [$count, $url]) {
                if ($count > 0 && $this->notify($user, $category, $count, $url)) {
                    $sent++;
                }
            }
        });

        return $sent;
    }

    /** @return Collection<int, int> */
    private function operationCounts(): Collection
    {
        $activityCounts = CrmActivity::query()
            ->whereNull('work_order_id')
            ->whereNotNull('assigned_user_id')
            ->whereIn('status', ['pending', 'in_progress'])
            ->whereDate('due_at', '<=', today()->addDay())
            ->selectRaw('assigned_user_id, count(*) as aggregate')
            ->groupBy('assigned_user_id')
            ->pluck('aggregate', 'assigned_user_id');

        $workOrderCounts = WorkOrder::query()
            ->whereNotNull('assigned_user_id')
            ->whereNotIn('status', ['completed', 'cancelled'])
            ->whereDate('scheduled_start', '<=', today()->addDay())
            ->selectRaw('assigned_user_id, count(*) as aggregate')
            ->groupBy('assigned_user_id')
            ->pluck('aggregate', 'assigned_user_id');

        $counts = $activityCounts->map(fn ($count) => (int) $count);
        $workOrderCounts->each(fn ($count, $userId) => $counts->put($userId, (int) $counts->get($userId, 0) + (int) $count));

        $unassigned = CrmActivity::query()
            ->whereNull('work_order_id')
            ->whereNull('assigned_user_id')
            ->whereIn('status', ['pending', 'in_progress'])
            ->whereDate('due_at', '<=', today()->addDay())
            ->count()
            + WorkOrder::query()
                ->whereNull('assigned_user_id')
                ->whereNotIn('status', ['completed', 'cancelled'])
                ->whereDate('scheduled_start', '<=', today()->addDay())
                ->count();

        if ($unassigned > 0) {
            User::query()->whereIn('role', ['admin', 'commercial'])->pluck('id')->each(
                fn ($userId) => $counts->put($userId, (int) $counts->get($userId, 0) + $unassigned),
            );
        }

        return $counts;
    }

    private function notify(User $user, string $category, int $count, string $url): bool
    {
        $reminder = ActionReminder::query()->firstOrCreate(
            ['user_id' => $user->id, 'category' => $category, 'reminder_on' => today()],
            ['item_count' => $count],
        );

        if (! $reminder->wasRecentlyCreated) {
            return false;
        }

        $user->notify(new ActionSummaryNotification($category, $count, $url));

        return true;
    }
}
