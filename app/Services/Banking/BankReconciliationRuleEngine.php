<?php

namespace App\Services\Banking;

use App\Models\BankAccount;
use App\Models\BankReconciliationRule;
use App\Models\BankReconciliationRun;
use App\Models\BankReconciliationRunItem;
use App\Models\BankTransaction;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class BankReconciliationRuleEngine
{
    public function __construct(private BankTransactionPoster $poster) {}

    public function rules(BankAccount $account): Collection
    {
        return BankReconciliationRule::with(['ledgerAccount:id,code,name,is_active,is_postable', 'latestVersion'])
            ->whereBelongsTo($account, 'account')
            ->where('is_active', true)
            ->orderBy('priority')
            ->orderBy('id')
            ->get();
    }

    public function suggest(BankTransaction $transaction, Collection $rules): ?array
    {
        $matching = $rules->filter(fn (BankReconciliationRule $rule) => $this->matches($transaction, $rule))->values();
        if ($matching->isEmpty()) {
            return null;
        }
        $rule = $matching->first();
        $alternatives = $matching->skip(1)->map(fn (BankReconciliationRule $alternative) => [
            'rule_id' => $alternative->id,
            'rule_name' => $alternative->name,
            'confidence' => $alternative->confidence,
            'account_code' => $alternative->ledgerAccount->code,
            'account_name' => $alternative->ledgerAccount->name,
        ])->values()->all();

        return [
            'rule_id' => $rule->id,
            'rule_name' => $rule->name,
            'rule_version' => $rule->current_version,
            'confidence' => $rule->confidence,
            'ledger_account' => [
                'id' => $rule->ledgerAccount->id,
                'code' => $rule->ledgerAccount->code,
                'name' => $rule->ledgerAccount->name,
            ],
            'conflict' => $matching->pluck('ledger_account_id')->unique()->count() > 1,
            'alternatives' => $alternatives,
            'explanation' => $this->explanation($transaction, $rule),
        ];
    }

    public function preview(BankAccount $account, ?Collection $rules = null): array
    {
        $rules ??= $this->rules($account);
        $pendingCount = $account->transactions()->where('status', 'pending')->count();
        $scanned = $account->transactions()->where('status', 'pending')->oldest('booked_on')->limit(1000)->get();
        $suggestions = $scanned->map(fn (BankTransaction $transaction) => ['transaction' => $transaction, 'suggestion' => $this->suggest($transaction, $rules)])
            ->filter(fn (array $item) => $item['suggestion']);
        $safe = $suggestions->reject(fn (array $item) => $item['suggestion']['conflict']);

        return [
            'pending' => $pendingCount,
            'scanned' => $scanned->count(),
            'truncated' => $pendingCount > $scanned->count(),
            'matched' => $safe->count(),
            'conflicts' => $suggestions->filter(fn (array $item) => $item['suggestion']['conflict'])->count(),
            'eligibleByConfidence' => collect([80, 90, 95, 98, 100])->mapWithKeys(fn (int $confidence) => [(string) $confidence => $safe->filter(fn (array $item) => $item['suggestion']['confidence'] >= $confidence)->count()]),
            'byRule' => $safe->groupBy(fn (array $item) => $item['suggestion']['rule_id'])->map(fn ($items) => ['rule_id' => $items->first()['suggestion']['rule_id'], 'name' => $items->first()['suggestion']['rule_name'], 'matched' => $items->count()])->values(),
            'details' => $suggestions->take(50)->map(fn (array $item) => [
                'transaction_id' => $item['transaction']->id,
                'booked_on' => $item['transaction']->booked_on->toDateString(),
                'description' => $item['transaction']->description,
                'reference' => $item['transaction']->reference,
                'amount' => (float) $item['transaction']->amount,
                ...$item['suggestion'],
            ])->values(),
        ];
    }

    public function apply(BankAccount $account, int $userId, int $minimumConfidence): BankReconciliationRun
    {
        $rules = $this->rules($account);
        $run = BankReconciliationRun::create(['bank_account_id' => $account->id, 'triggered_by' => $userId, 'status' => 'running', 'minimum_confidence' => $minimumConfidence, 'started_at' => now()]);
        $matched = $skipped = $failed = $conflicts = 0;

        $account->transactions()->where('status', 'pending')->oldest('booked_on')->limit(1000)->get()->each(function (BankTransaction $transaction) use ($rules, $run, $userId, $minimumConfidence, &$matched, &$skipped, &$failed, &$conflicts) {
            $suggestion = $this->suggest($transaction, $rules);
            if (! $suggestion || $suggestion['confidence'] < $minimumConfidence) {
                $skipped++;

                return;
            }
            $rule = $rules->firstWhere('id', $suggestion['rule_id']);
            if ($suggestion['conflict']) {
                BankReconciliationRunItem::create(['bank_reconciliation_run_id' => $run->id, 'bank_reconciliation_rule_id' => $rule->id, 'bank_reconciliation_rule_version_id' => $rule->latestVersion?->id, 'bank_transaction_id' => $transaction->id, 'confidence' => $suggestion['confidence'], 'outcome' => 'conflict', 'explanation' => __('Multiple rules matched different ledger accounts; no posting was created.')]);
                $conflicts++;
                $skipped++;

                return;
            }
            try {
                $posting = $this->poster->post($transaction, $rule->ledgerAccount, $userId, $rule, $run);
                BankReconciliationRunItem::create(['bank_reconciliation_run_id' => $run->id, 'bank_reconciliation_rule_id' => $rule->id, 'bank_reconciliation_rule_version_id' => $rule->latestVersion?->id, 'bank_transaction_id' => $transaction->id, 'bank_transaction_posting_id' => $posting->id, 'confidence' => $suggestion['confidence'], 'outcome' => 'applied', 'explanation' => $suggestion['explanation']]);
                $rule->increment('application_count');
                $rule->update(['last_applied_at' => now()]);
                $matched++;
            } catch (\Throwable $exception) {
                report($exception);
                BankReconciliationRunItem::create(['bank_reconciliation_run_id' => $run->id, 'bank_reconciliation_rule_id' => $rule->id, 'bank_reconciliation_rule_version_id' => $rule->latestVersion?->id, 'bank_transaction_id' => $transaction->id, 'confidence' => $suggestion['confidence'], 'outcome' => 'failed', 'explanation' => $suggestion['explanation'], 'error' => Str::limit($exception->getMessage(), 2000)]);
                $failed++;
            }
        });

        $run->update(['status' => $failed > 0 ? 'completed_with_errors' : 'completed', 'matched_count' => $matched, 'skipped_count' => $skipped, 'failed_count' => $failed, 'summary' => ['rules' => $rules->count(), 'limit' => 1000, 'conflicts' => $conflicts], 'completed_at' => now()]);

        return $run;
    }

    public function reverse(BankReconciliationRun $run, int $userId, string $reason): BankReconciliationRun
    {
        return DB::transaction(function () use ($run, $userId, $reason) {
            $run = BankReconciliationRun::lockForUpdate()->findOrFail($run->id);
            if ($run->reversed_at) {
                throw ValidationException::withMessages(['reason' => __('This rule run has already been reversed.')]);
            }
            $items = $run->items()->where('outcome', 'applied')->with(['posting.transaction'])->get();
            if ($items->isEmpty() || $items->contains(fn (BankReconciliationRunItem $item) => ! $item->posting)) {
                throw ValidationException::withMessages(['reason' => __('The applied postings are incomplete and the run cannot be reversed as a unit.')]);
            }
            foreach ($items as $item) {
                $posting = $item->posting;
                if ($posting->reversed_at) {
                    if ($posting->transaction->status !== 'pending') {
                        throw ValidationException::withMessages(['reason' => __('A previously reversed movement has since been processed again. Reverse it manually before closing this run.')]);
                    }

                    continue;
                }
                if ($posting->transaction->status !== 'accounted') {
                    throw ValidationException::withMessages(['reason' => __('A movement in this run is no longer in its original accounted state.')]);
                }
                $this->poster->reverse($posting, $userId);
            }
            $run->update([
                'status' => 'reversed',
                'reversed_by' => $userId,
                'reversed_at' => now(),
                'reversal_reason' => $reason,
                'summary' => [...($run->summary ?? []), 'reversed_postings' => $items->count()],
            ]);

            return $run->fresh();
        });
    }

    private function matches(BankTransaction $transaction, BankReconciliationRule $rule): bool
    {
        $amount = abs((float) $transaction->amount);
        if (($rule->direction === 'in' && (float) $transaction->amount <= 0) || ($rule->direction === 'out' && (float) $transaction->amount >= 0)) {
            return false;
        }
        if ($rule->min_amount !== null && $amount < (float) $rule->min_amount) {
            return false;
        }
        if ($rule->max_amount !== null && $amount > (float) $rule->max_amount) {
            return false;
        }
        $value = match ($rule->match_field) {
            'description' => (string) $transaction->description,
            'reference' => (string) $transaction->reference,
            default => trim((string) $transaction->reference.' '.(string) $transaction->description),
        };
        $value = Str::lower(Str::ascii($value));
        $pattern = Str::lower(Str::ascii(trim($rule->pattern)));

        return match ($rule->match_operator) {
            'exact' => $value === $pattern,
            'starts_with' => str_starts_with($value, $pattern),
            default => str_contains($value, $pattern),
        };
    }

    private function explanation(BankTransaction $transaction, BankReconciliationRule $rule): string
    {
        $direction = (float) $transaction->amount > 0 ? __('incoming') : __('outgoing');
        $range = $rule->min_amount !== null || $rule->max_amount !== null
            ? __(' and amount is within the configured range')
            : '';

        return __('Rule ":rule" matched :direction :field with :operator ":pattern": post to :account:range.', ['rule' => $rule->name, 'direction' => $direction, 'field' => $rule->match_field, 'operator' => $rule->match_operator, 'pattern' => $rule->pattern, 'account' => $rule->ledgerAccount->code, 'range' => $range]);
    }
}
