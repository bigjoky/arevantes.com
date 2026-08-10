<?php

namespace App\Services\Banking;

use App\Models\BankAccount;
use App\Models\BankReconciliationRule;
use App\Models\BankReconciliationRun;
use App\Models\BankReconciliationRunItem;
use App\Models\BankTransaction;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

class BankReconciliationRuleEngine
{
    public function __construct(private BankTransactionPoster $poster) {}

    public function rules(BankAccount $account): Collection
    {
        return BankReconciliationRule::with('ledgerAccount:id,code,name,is_active,is_postable')
            ->whereBelongsTo($account, 'account')
            ->where('is_active', true)
            ->orderBy('priority')
            ->orderBy('id')
            ->get();
    }

    public function suggest(BankTransaction $transaction, Collection $rules): ?array
    {
        foreach ($rules as $rule) {
            if ($this->matches($transaction, $rule)) {
                return [
                    'rule_id' => $rule->id,
                    'rule_name' => $rule->name,
                    'confidence' => $rule->confidence,
                    'ledger_account' => [
                        'id' => $rule->ledgerAccount->id,
                        'code' => $rule->ledgerAccount->code,
                        'name' => $rule->ledgerAccount->name,
                    ],
                    'explanation' => $this->explanation($transaction, $rule),
                ];
            }
        }

        return null;
    }

    public function preview(BankAccount $account, ?Collection $rules = null): array
    {
        $rules ??= $this->rules($account);
        $pendingCount = $account->transactions()->where('status', 'pending')->count();
        $scanned = $account->transactions()->where('status', 'pending')->oldest('booked_on')->limit(1000)->get();
        $suggestions = $scanned->map(fn (BankTransaction $transaction) => $this->suggest($transaction, $rules))->filter();

        return [
            'pending' => $pendingCount,
            'scanned' => $scanned->count(),
            'truncated' => $pendingCount > $scanned->count(),
            'matched' => $suggestions->count(),
            'byRule' => $suggestions->groupBy('rule_id')->map(fn ($items) => ['rule_id' => $items->first()['rule_id'], 'name' => $items->first()['rule_name'], 'matched' => $items->count()])->values(),
        ];
    }

    public function apply(BankAccount $account, int $userId, int $minimumConfidence): BankReconciliationRun
    {
        $rules = $this->rules($account);
        $run = BankReconciliationRun::create(['bank_account_id' => $account->id, 'triggered_by' => $userId, 'status' => 'running', 'minimum_confidence' => $minimumConfidence, 'started_at' => now()]);
        $matched = $skipped = $failed = 0;

        $account->transactions()->where('status', 'pending')->oldest('booked_on')->limit(1000)->get()->each(function (BankTransaction $transaction) use ($rules, $run, $userId, $minimumConfidence, &$matched, &$skipped, &$failed) {
            $suggestion = $this->suggest($transaction, $rules);
            if (! $suggestion || $suggestion['confidence'] < $minimumConfidence) {
                $skipped++;

                return;
            }
            $rule = $rules->firstWhere('id', $suggestion['rule_id']);
            try {
                $posting = $this->poster->post($transaction, $rule->ledgerAccount, $userId, $rule, $run);
                BankReconciliationRunItem::create(['bank_reconciliation_run_id' => $run->id, 'bank_reconciliation_rule_id' => $rule->id, 'bank_transaction_id' => $transaction->id, 'bank_transaction_posting_id' => $posting->id, 'confidence' => $suggestion['confidence'], 'outcome' => 'applied', 'explanation' => $suggestion['explanation']]);
                $rule->increment('application_count');
                $rule->update(['last_applied_at' => now()]);
                $matched++;
            } catch (\Throwable $exception) {
                report($exception);
                BankReconciliationRunItem::create(['bank_reconciliation_run_id' => $run->id, 'bank_reconciliation_rule_id' => $rule->id, 'bank_transaction_id' => $transaction->id, 'confidence' => $suggestion['confidence'], 'outcome' => 'failed', 'explanation' => $suggestion['explanation'], 'error' => Str::limit($exception->getMessage(), 2000)]);
                $failed++;
            }
        });

        $run->update(['status' => $failed > 0 ? 'completed_with_errors' : 'completed', 'matched_count' => $matched, 'skipped_count' => $skipped, 'failed_count' => $failed, 'summary' => ['rules' => $rules->count(), 'limit' => 1000], 'completed_at' => now()]);

        return $run;
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
