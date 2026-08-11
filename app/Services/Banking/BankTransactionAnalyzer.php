<?php

namespace App\Services\Banking;

use App\Models\BankTransaction;
use Illuminate\Support\Collection;

class BankTransactionAnalyzer
{
    public function medianAbsoluteAmount(Collection $amounts): float
    {
        $values = $amounts->map(fn ($amount) => abs((float) $amount))->filter(fn (float $amount) => $amount > 0)->sort()->values();
        $count = $values->count();

        if ($count === 0) {
            return 0;
        }

        $middle = intdiv($count, 2);

        return $count % 2 === 0
            ? round(((float) $values[$middle - 1] + (float) $values[$middle]) / 2, 2)
            : round((float) $values[$middle], 2);
    }

    /** @param array<int, array<string, mixed>> $candidates */
    public function analyze(BankTransaction $transaction, array $candidates, float $medianAmount): array
    {
        if ($transaction->status !== 'pending') {
            return ['match_quality' => 'completed', 'recommended_candidate' => null, 'risk_flags' => []];
        }

        $top = $candidates[0] ?? null;
        $second = $candidates[1] ?? null;
        $confidenceGap = $top ? (int) $top['confidence'] - (int) ($second['confidence'] ?? 0) : 0;
        $matchQuality = ! $top
            ? 'unmatched'
            : ((int) $top['confidence'] >= 95 && (! $second || $confidenceGap >= 10) ? 'ready' : 'review');
        $flags = [];

        if ($transaction->booked_on->isBefore(today()->subDays(7))) {
            $flags[] = ['code' => 'stale', 'label' => __('Pending for more than 7 days'), 'severity' => 'high'];
        }
        if ($medianAmount > 0 && abs((float) $transaction->amount) >= max(1000, $medianAmount * 3)) {
            $flags[] = ['code' => 'unusual_amount', 'label' => __('Unusual amount for this account'), 'severity' => 'high'];
        }
        if (blank($transaction->reference)) {
            $flags[] = ['code' => 'missing_reference', 'label' => __('Missing bank reference'), 'severity' => 'medium'];
        }

        return [
            'match_quality' => $matchQuality,
            'recommended_candidate' => $top,
            'risk_flags' => $flags,
        ];
    }

    /** @param array<int, array<string, mixed>> $candidates */
    public function automaticCandidate(array $candidates, int $minimumConfidence): ?array
    {
        $top = $candidates[0] ?? null;
        if (! $top || (int) $top['confidence'] < $minimumConfidence) {
            return null;
        }

        $second = $candidates[1] ?? null;
        if ($second && (int) $top['confidence'] - (int) $second['confidence'] < 10) {
            return null;
        }

        return $top;
    }
}
