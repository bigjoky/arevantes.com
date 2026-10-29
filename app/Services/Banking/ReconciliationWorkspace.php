<?php

namespace App\Services\Banking;

use App\Models\AuditLog;
use App\Models\BankTransaction;
use App\Models\SalesDocumentPayment;
use App\Models\SupplierPayment;
use App\Models\TaxReturnPayment;
use App\Services\Finance\FinanceWorkspace;
use Brick\Math\BigDecimal;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class ReconciliationWorkspace
{
    public function __construct(private ControlledReconciliation $control) {}

    private ?Collection $customerPool = null;

    private ?Collection $supplierPool = null;

    private array $bankBalances = [];

    private array $paymentBalances = [];

    private array $periodChecks = [];

    public const CATEGORIES = ['no_erp_operation', 'no_bank_movement', 'amount_difference', 'reference_difference', 'possible_duplicate', 'voided_operation', 'already_reconciled', 'closed_period', 'bank_fee'];

    public function prepare(Collection $banks): void
    {
        if ($banks->isEmpty()) {
            return;
        }
        $from = $banks->min('booked_on')->copy()->subDays(14)->toDateString();
        $to = $banks->max('booked_on')->copy()->addDays(14)->toDateString();
        $this->customerPool = SalesDocumentPayment::with(['document', 'reconciliations.transaction'])->whereBetween('paid_at', [$from, $to])->latest('id')->limit(1000)->get();
        $this->supplierPool = SupplierPayment::with(['expense', 'purchaseDocument', 'reconciliations.transaction'])->whereBetween('paid_at', [$from, $to])->latest('id')->limit(1000)->get();
        (new \Illuminate\Database\Eloquent\Collection($banks->all()))->load(['reconciliations.transaction', 'account']);
        foreach ($banks as $bank) {
            $sum = $bank->reconciliations->reduce(fn ($sum, $r) => $sum->plus($r->effectiveAmount()), BigDecimal::zero());
            $this->bankBalances[$bank->id] = BigDecimal::of($bank->amount)->abs()->minus($sum);
        }
    }

    public function candidates(BankTransaction $bank, Request $request): array
    {
        $kind = $request->input('kind', (float) $bank->amount > 0 ? 'customer' : 'supplier');
        if ($kind === 'transfer') {
            $q = BankTransaction::with('account')->where('bank_account_id', '!=', $bank->bank_account_id)->where('currency', $bank->currency)->where('amount', (string) BigDecimal::of($bank->amount)->negated());
            if ($term = trim($request->string('search')->toString())) {
                $q->where(fn ($q) => $q->where('reference', 'like', '%'.$term.'%')->orWhere('description', 'like', '%'.$term.'%'));
            }
            $page = $q->latest('booked_on')->paginate(15)->withQueryString();
            $page->through(fn ($other) => ['value' => 'transfer:'.$other->id, 'number' => $other->account->name, 'reference' => $other->reference, 'date' => $other->booked_on->toDateString(), 'amount' => (string) BigDecimal::of($other->amount)->abs()->toScale(2), 'remaining' => (string) $this->control->bankRemaining($other)->toScale(2), 'difference' => '0.00', 'account_match' => true, 'signals' => ['opposite_transfer', 'exact_amount'], 'score' => 80, 'blocked' => $other->status === 'pending' ? null : 'already_reconciled', 'href' => '/banking?account='.$other->bank_account_id.'&tab=reconciliation']);

            return $page->toArray();
        }

        $class = match ($kind) {
            'customer' => SalesDocumentPayment::class,'tax' => TaxReturnPayment::class,default => SupplierPayment::class
        };
        $query = $class::query()->with(match ($kind) {
            'customer' => ['document'],'tax' => ['taxReturn'],default => ['expense', 'purchaseDocument']
        });
        if ($request->boolean('include_voided')) {
            $query->withoutGlobalScope($kind === 'customer' ? 'active_customer_payment' : 'active_supplier_payment');
        }
        if ($term = trim($request->string('search')->toString())) {
            $query->where(fn ($q) => $q->where('reference', 'like', '%'.$term.'%')->orWhere('id', ctype_digit($term) ? (int) $term : 0)->when($kind !== 'tax', fn ($q) => $q->orWhereHas($kind === 'customer' ? 'document' : 'purchaseDocument', fn ($d) => $d->where('number', 'like', '%'.$term.'%')))->when($kind === 'supplier', fn ($q) => $q->orWhereHas('expense', fn ($d) => $d->where('number', 'like', '%'.$term.'%'))));
        }
        $page = $query->orderByDesc($kind === 'tax' ? 'paid_on' : 'paid_at')->orderByDesc('id')->paginate(15)->withQueryString();
        $page->through(fn ($payment) => $this->candidate($bank, $payment, $kind));

        return $page->toArray();
    }

    private function normalized(?string $value): string
    {
        return preg_replace('/[^a-z0-9]/', '', Str::lower(Str::ascii($value ?? '')));
    }

    public function candidate(BankTransaction $bank, $payment, string $type): array
    {
        $document = $type === 'customer' ? $payment->document : ($type === 'tax' ? $payment->taxReturn : ($payment->expense ?? $payment->purchaseDocument));
        $paidDate = $type === 'tax' ? $payment->paid_on : $payment->paid_at;
        $remaining = $this->paymentBalances[$type.':'.$payment->id] ??= ($payment->relationLoaded('reconciliations') ? BigDecimal::of($payment->amount)->minus($payment->reconciliations->reduce(fn ($sum, $r) => $sum->plus($r->effectiveAmount()), BigDecimal::zero())) : $this->control->paymentRemaining($payment));
        $bankRemaining = $this->bankBalances[$bank->id] ??= $this->control->bankRemaining($bank);
        $signals = [];
        $score = 0;
        if ($remaining->isEqualTo($bankRemaining)) {
            $signals[] = 'exact_amount';
            $score += 40;
        }
        $reference = $this->normalized($bank->reference);
        $bankConcept = $this->normalized($bank->description);
        $paymentReference = $this->normalized($payment->reference);
        $documentNumber = $this->normalized($document?->number);
        if ($reference !== '' && $reference === $paymentReference) {
            $signals[] = 'exact_reference';
            $score += 35;
        }
        if ($documentNumber !== '' && (str_contains($reference, $documentNumber) || str_contains($bankConcept, $documentNumber))) {
            $signals[] = 'document_reference';
            $score += 25;
        }
        if ($paymentReference !== '' && str_contains($bankConcept, $paymentReference)) {
            $signals[] = 'concept_reference';
            $score += 10;
        }
        $days = (int) abs($bank->booked_on->diffInDays($paidDate));
        if ($days <= 7) {
            $signals[] = 'near_date';
            $score += 10;
        }
        $knownAccount = $type === 'supplier' && $payment->funding_source_type === 'bank_account' ? (int) $payment->funding_source_id : null;
        $sameAccount = $knownAccount === null ? null : $knownAccount === $bank->bank_account_id;
        if ($sameAccount) {
            $signals[] = 'same_account';
            $score += 15;
        }
        $blocked = null;
        if ($payment->voided_at) {
            $blocked = 'voided_operation';
        } elseif (! $remaining->isPositive()) {
            $blocked = 'already_reconciled';
        } elseif ($sameAccount === false || ($type === 'customer' || ($type === 'tax' && (float) $payment->taxReturn->result < 0)) !== ((float) $bank->amount > 0) || $payment->method === 'cash' || ($payment->origin_bank_transaction_id && (int) $payment->origin_bank_transaction_id !== $bank->id) || $bank->currency !== 'EUR' || ($type === 'supplier' && $payment->expense && $payment->expense->currency_code !== 'EUR')) {
            $blocked = 'incompatible_account_or_method';
        } else {
            try {
                $this->checkPeriod($bank->booked_on->toDateString());
                $this->checkPeriod($paidDate->toDateString());
            } catch (ValidationException) {
                $blocked = 'closed_period';
            }
        }

        return ['value' => $type.':'.$payment->id, 'type' => $type, 'id' => $payment->id, 'number' => $type === 'tax' ? ('VAT '.$document->year.' Q'.$document->quarter) : $document?->number, 'reference' => $payment->reference, 'date' => $paidDate->toDateString(), 'amount' => (string) $payment->amount, 'remaining' => (string) $remaining->toScale(2), 'difference' => (string) $bankRemaining->minus($remaining)->toScale(2), 'account_match' => $sameAccount, 'signals' => $signals, 'score' => min(100, $score), 'blocked' => $blocked, 'href' => $type === 'tax' ? '/tax' : ($type === 'customer' ? '/sales/'.$payment->sales_document_id : ($payment->expense_id ? '/expenses/'.$payment->expense_id : '/purchases/'.$payment->purchase_document_id))];
    }

    public function transaction(BankTransaction $bank): array
    {
        $type = (float) $bank->amount > 0 ? 'customer' : 'supplier';
        $class = $type === 'customer' ? SalesDocumentPayment::class : SupplierPayment::class;
        $pool = $type === 'customer' ? $this->customerPool : $this->supplierPool;
        $from = $bank->booked_on->copy()->subDays(14);
        $to = $bank->booked_on->copy()->addDays(14);
        $payments = $pool !== null ? $pool->filter(fn ($p) => $p->paid_at->betweenIncluded($from, $to))->take(100) : $class::with($type === 'customer' ? ['document', 'reconciliations.transaction'] : ['expense', 'purchaseDocument', 'reconciliations.transaction'])->whereBetween('paid_at', [$from->toDateString(), $to->toDateString()])->latest('id')->limit(100)->get();
        $candidates = $payments->map(fn ($p) => $this->candidate($bank, $p, $type))->whereNull('blocked')->filter(fn ($c) => $c['score'] >= 40)->sortByDesc('score')->take(5)->values();
        $flags = [];
        if ($candidates->isEmpty() && in_array($bank->status, ['pending', 'partial'])) {
            $flags[] = 'no_erp_operation';
        }
        if ($candidates->count() > 1 && $candidates[0]['score'] === $candidates[1]['score']) {
            $flags[] = 'ambiguous_reference';
        }
        if ($bank->account->transactions()->whereKeyNot($bank->id)->whereDate('booked_on', $bank->booked_on)->where('amount', $bank->amount)->where('reference', $bank->reference)->exists()) {
            $flags[] = 'possible_duplicate';
        }
        try {
            $this->checkPeriod($bank->booked_on->toDateString());
        } catch (ValidationException) {
            $flags[] = 'closed_period';
        }
        if (preg_match('/comisi[oó]n|commission|fee/i', $bank->description)) {
            $flags[] = 'bank_fee';
        }
        if ($bank->status === 'reconciled') {
            $flags[] = 'already_reconciled';
        }
        if ($candidates->isNotEmpty() && $candidates[0]['difference'] !== '0.00') {
            $flags[] = 'amount_difference';
        }
        if ($candidates->isNotEmpty() && ! array_intersect(['exact_reference', 'document_reference', 'concept_reference'], $candidates[0]['signals'])) {
            $flags[] = 'reference_difference';
        }
        $history = $bank->reconciliations()->withoutGlobalScope('active_bank_reconciliation')->with(['customerPayment.document:id,number', 'supplierPayment.expense:id,number', 'supplierPayment.purchaseDocument:id,number', 'reversedBy:id,name', 'reconciledBy:id,name'])->orderByDesc('id')->get()->map(fn ($m) => ['id' => $m->id, 'amount' => $m->effectiveAmount(), 'reversed_at' => $m->reversed_at?->toISOString(), 'reversal_reason' => $m->reversal_reason, 'reason' => $m->reason, 'reconciled_at' => $m->confirmed_at_utc?->toISOString(), 'legacy_reconciled_date' => substr((string) $m->getRawOriginal('reconciled_at'), 0, 10), 'reversed_by' => $m->reversedBy?->name, 'reconciled_by' => $m->reconciledBy?->name, 'payment' => $m->sales_document_payment_id ? 'customer:'.$m->sales_document_payment_id : ($m->supplier_payment_id ? 'supplier:'.$m->supplier_payment_id : 'tax:'.$m->tax_return_payment_id), 'journal_entry_id' => $m->journal_entry_id, 'reversal_entry_id' => $m->reversal_entry_id, 'generated_operation' => (bool) ($m->customerPayment?->origin_bank_transaction_id ?? $m->supplierPayment?->origin_bank_transaction_id), 'voided_operation' => (bool) ($m->customerPayment?->voided_at ?? $m->supplierPayment?->voided_at)]);
        if ($history->contains(fn ($item) => ! $item['reversed_at'] && $item['voided_operation'])) {
            $flags[] = 'voided_operation';
        }
        $reviews = AuditLog::where('auditable_type', BankTransaction::class)->where('auditable_id', $bank->id)->whereIn('event', ['bank_discrepancy_review', 'bank_match_confirmed', 'bank_match_corrected'])->with('user:id,name')->latest('id')->limit(20)->get()->map(fn ($a) => ['id' => $a->id, 'event' => $a->event, 'actor' => $a->user?->name, 'date' => $a->new_values['recorded_at_utc'] ?? $a->created_at->toISOString(), 'values' => $a->new_values]);

        return ['revision' => $this->control->revision($bank), 'remaining' => (string) $this->control->bankRemaining($bank)->toScale(2), 'suggestions' => $candidates, 'flags' => $flags, 'history' => $history, 'reviews' => $reviews, 'suggestion_scan_limit' => 100, 'automatic_confirmation' => false];
    }

    private function checkPeriod(string $date): void
    {
        if (! array_key_exists($date, $this->periodChecks)) {
            try {
                $this->control->assertPeriods([$date], false);
                $this->periodChecks[$date] = true;
            } catch (ValidationException) {
                $this->periodChecks[$date] = false;
            }
        }
        if (! $this->periodChecks[$date]) {
            throw ValidationException::withMessages(['period' => __('The bank operation belongs to a closed or unavailable fiscal year.')]);
        }
    }

    public function unmatched(Request $request, ?int $accountId = null): array
    {
        $query = app(FinanceWorkspace::class)->unmatchedPayments()->where('method', '<>', 'cash');
        $accountId ??= $request->filled('account') ? $request->integer('account') : null;
        if ($accountId) {
            $query->where(fn ($q) => $q->whereNull('bank_account_id')->orWhere('bank_account_id', $accountId));
        }
        if ($request->filled('from')) {
            $query->whereDate('date', '>=', $request->input('from'));
        }
        if ($request->filled('to')) {
            $query->whereDate('date', '<=', $request->input('to'));
        }
        if ($search = trim($request->string('erp_search')->toString())) {
            $query->where(fn ($q) => $q->where('reference', 'like', '%'.$search.'%')->orWhere('description', 'like', '%'.$search.'%'));
        }

        return $query->orderByDesc('date')->orderBy('source')->orderBy('id')->paginate(15, ['*'], 'erp_page')->withQueryString()->toArray();
    }
}
