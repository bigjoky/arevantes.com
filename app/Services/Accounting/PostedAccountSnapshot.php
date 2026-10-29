<?php

namespace App\Services\Accounting;

use App\Models\AccountingEntryCorrection;
use App\Models\AuditLog;
use App\Models\JournalEntry;
use App\Models\JournalLine;
use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\ValidationException;

/** Derive the effective ledger position exclusively from preserved posted evidence. */
class PostedAccountSnapshot
{
    public function lines(Model $source, ?string $event = null): array
    {
        $entries = JournalEntry::whereMorphedTo('source', $source)->where('status', 'posted')->when($event, fn ($q) => $q->where('event', $event))->get();
        if ($entries->isEmpty()) {
            return [];
        }
        $ids = $entries->pluck('id');
        $adjustments = AccountingEntryCorrection::whereIn('journal_entry_id', $ids)->whereNotNull('correction_entry_id')->pluck('correction_entry_id');
        $allIds = $ids->merge($adjustments)->merge(JournalEntry::whereIn('reverses_entry_id', $ids->merge($adjustments))->pluck('id'))->unique();
        $net = [];
        foreach (JournalEntry::whereIn('id', $allIds)->where('status', 'posted')->with('lines.account')->get() as $entry) {
            foreach ($entry->lines as $line) {
                $code = $line->account->code;
                $net[$code] = ($net[$code] ?? BigDecimal::zero())->plus($line->debit)->minus($line->credit);
            }
        }
        ksort($net);
        $rows = [];
        foreach ($net as $code => $amount) {
            if (! $amount->isZero()) {
                $rows[] = [(string) $code, $amount->isPositive() ? (string) $amount->toScale(2) : '0.00', $amount->isNegative() ? (string) $amount->abs()->toScale(2) : '0.00'];
            }
        }

        return $rows;
    }

    public static function equivalent(array $left, array $right): bool
    {
        $normalize = function (array $lines): array {
            $net = [];
            foreach ($lines as [$code,$debit,$credit]) {
                $net[(string) $code] = ($net[(string) $code] ?? BigDecimal::zero())->plus((string) $debit)->minus((string) $credit);
            }ksort($net);

            return array_map(fn ($v) => (string) $v->toScale(2, RoundingMode::HALF_UP), array_filter($net, fn ($v) => ! $v->isZero()));
        };

        return $normalize($left) === $normalize($right);
    }

    public function historicalChoice(Model $source, string $role, ?Model $specific = null): ?string
    {
        $prefix = AccountingAccountPolicy::ROLES[$role][0];
        $net = collect($this->lines($source))->filter(fn ($l) => str_starts_with($l[0], $prefix) && ! ($role === 'purchase' && $l[0] === '624'))->pluck(0)->unique();
        if ($net->count() === 1) {
            return $net->first();
        }
        if ($net->isEmpty()) {
            return null;
        }
        $entries = JournalEntry::whereMorphedTo('source', $source)->where('status', 'posted')->pluck('id');
        foreach (AuditLog::where('auditable_type', JournalEntry::class)->whereIn('auditable_id', $entries)->where('event', 'journal_accounts_resolved')->orderByDesc('id')->get() as $log) {
            foreach ($log->new_values['decisions'] ?? [] as $d) {
                if (($d['role'] ?? null) !== $role || ($d['specific_type'] ?? null) !== $specific?->getMorphClass() || ($d['specific_id'] ?? null) !== $specific?->id) {
                    continue;
                }
                $code = (string) $d['code'];
                $matching = JournalLine::whereIn('journal_entry_id', $entries)->whereHas('account', fn ($q) => $q->where('code', $code))->get();
                if ($matching->count() === 1) {
                    foreach (AccountingEntryCorrection::whereIn('journal_entry_id', $entries)->orderBy('id')->get() as $c) {
                        foreach ($c->line_changes as $change) {
                            if ((int) $change['id'] === $matching->first()->id) {
                                $code = $change['account_code'];
                            }
                        }
                    }
                }
                if ($net->contains($code)) {
                    return $code;
                }
            }
        }
        throw ValidationException::withMessages(['accounting' => __('The original document has several accounting accounts without sufficient line evidence. An explicit accounting review is required.')]);
    }

    public function control(Model $source, array $prefixes, ?string $event = null): ?string
    {
        $codes = collect($this->lines($source, $event))->filter(fn ($l) => collect($prefixes)->contains(fn ($p) => str_starts_with($l[0], $p)))->pluck(0)->unique();
        if ($codes->count() > 1) {
            throw ValidationException::withMessages(['accounting' => __('The posted control account is ambiguous. Review the linked journal corrections.')]);
        }

        return $codes->first();
    }
}
