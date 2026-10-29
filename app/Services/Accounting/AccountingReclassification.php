<?php

namespace App\Services\Accounting;

use App\Models\AccountingAccountAssignment;
use App\Models\AccountingReclassificationBatch;
use App\Models\AuditLog;
use App\Models\CompanyProfile;
use App\Models\Expense;
use App\Models\FiscalYear;
use App\Models\JournalEntry;
use App\Models\JournalLine;
use App\Models\LedgerAccount;
use App\Models\Person;
use App\Models\PurchaseDocument;
use App\Models\SalesDocument;
use App\Models\SalesDocumentPayment;
use App\Models\Supplier;
use App\Models\SupplierPayment;
use App\Models\User;
use App\Support\Time\BusinessTime;
use Brick\Math\BigDecimal;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class AccountingReclassification
{
    public function __construct(private JournalCorrection $corrections, private PartyLedger $ledger) {}

    private function fail(string $message): never
    {
        throw ValidationException::withMessages(['reclassification' => __($message)]);
    }

    private function key(string $batch, int $entry): string
    {
        $h = hash('sha256', $batch.':'.$entry);

        return substr($h, 0, 8).'-'.substr($h, 8, 4).'-4'.substr($h, 13, 3).'-a'.substr($h, 17, 3).'-'.substr($h, 20, 12);
    }

    public function preview(array $data, User $user): array
    {
        abort_unless($user->can('correct-journal-entries'), 403);
        abort_if(CompanyProfile::count() > 1, 409, __('Accounting company separation is not configured.'));
        $groups = [];
        $blocked = [];
        $criteria = [];
        $items = $data['items'];
        usort($items, fn ($a, $b) => $a['line_id'] <=> $b['line_id']);
        $lines = JournalLine::with('entry', 'account')->whereIn('id', array_column($items, 'line_id'))->get()->keyBy('id');
        $parties = $this->ledger->evidence()->whereIn('entry_id', $lines->pluck('journal_entry_id')->unique())->get()->keyBy('entry_id');
        foreach ($items as $item) {
            $line = $lines->get($item['line_id']);
            if (! $line) {
                $blocked[] = ['line_id' => $item['line_id'], 'reason' => __('The selected journal line is unavailable.')];

                continue;
            }$entry = $line->entry;
            try {
                if ($entry->source_type && ! is_subclass_of($entry->source_type, Model::class)) {
                    $this->fail('The source document is unavailable. Review it before correcting this entry.');
                }
                if ($data['entry_date'] < $entry->entry_date->toDateString()) {
                    $this->fail('The reclassification date cannot precede the original entry.');
                }
                $single = ['entry_date' => $data['entry_date'], 'revision' => $this->corrections->revision($entry), 'lines' => [['id' => $line->id, 'account_code' => $item['account_code'], 'description' => $this->corrections->currentLines($entry)[$line->id]['description']]]];
                $plan = $this->corrections->preview($entry, $single);
                if (in_array($entry->entry_kind, ['invoice_correction', 'expense_correction', 'closing_reversal', 'opening_reversal'], true)) {
                    $this->fail('Use the protected document, tax or treasury workflow.');
                }
                if (! $plan['changes_accounts']) {
                    $this->fail('Select a different account for this line.');
                }
                $old = $plan['posting_lines'][0]['account_code'];
                if ((preg_match('/^(57|52|46|47|129|120)/', $old) || preg_match('/^(57|52|46|47|129|120)/', $item['account_code']))) {
                    $this->fail('Treasury, employee, tax and closing lines require their specific accounting workflow.');
                }
                $party = $parties->get($entry->id);
                if ($party && strlen($item['account_code']) > 3) {
                    $assigned = AccountingAccountAssignment::where('entity_type', $party->party_kind === 'customer' ? Person::class : Supplier::class)->where('entity_id', '<>', $party->party_id)->whereIn('ledger_account_id', LedgerAccount::where('code', $item['account_code'])->select('id'));
                    $legacy = $party->party_kind === 'customer' ? Person::where('category', 'customer')->whereKeyNot($party->party_id)->where('receivable_account_code', $item['account_code']) : Supplier::whereKeyNot($party->party_id)->where(fn ($q) => $q->where('payable_account_code', $item['account_code'])->orWhere('service_payable_account_code', $item['account_code']));
                    if ($assigned->exists() || $legacy->exists()) {
                        $this->fail('The proposed subaccount is explicitly assigned to another third party. Review the assignment first.');
                    }
                }
                $partyName = null;
                if ($party) {
                    $model = $this->ledger->party($party->party_kind, $party->party_id);
                    $partyName = $party->party_kind === 'customer' ? ($model->company_name ?: trim($model->first_name.' '.$model->last_name)) : $model->legal_name;
                }
                $criterion = $entry->fiscal_year_id.':'.($party ? $party->party_kind.':'.$party->party_id : ($entry->source_type ? 'unidentified:'.$entry->id : 'manual')).':'.(in_array(substr($old, 0, 3), ['430', '400', '410', '438', '407', '460', '465'], true) ? substr($old, 0, 3) : substr($old, 0, 1));
                $criteria[$criterion] = true;
                if (! isset($groups[$entry->id])) {
                    $groups[$entry->id] = ['entry_id' => $entry->id, 'number' => $entry->number, 'original_date' => $entry->entry_date->toDateString(), 'revision' => $single['revision'], 'party' => $party ? ['kind' => $party->party_kind, 'id' => $party->party_id, 'name' => $partyName] : null, 'lines' => [], 'effects' => []];
                }
                $groups[$entry->id]['lines'][] = $single['lines'][0];
                $groups[$entry->id]['effects'][] = ['line_id' => $line->id, 'from_account' => $old, 'to_account' => $item['account_code'], 'debit' => $line->debit, 'credit' => $line->credit, 'party_balance_effect' => $party ? '0.00' : null];
            } catch (ValidationException $e) {
                $blocked[] = ['line_id' => $line->id, 'entry_id' => $entry->id, 'entry_revision' => $this->corrections->revision($entry), 'reason' => implode(' ', array_merge(...array_values($e->errors())))];
            }
        }
        if (count($criteria) > 1) {
            $this->fail('Split this selection by third party, fiscal year and accounting criterion before confirming.');
        }
        ksort($groups);
        $plans = [];
        foreach ($groups as $id => $group) {
            $entry = $lines->firstWhere('journal_entry_id', $id)->entry;
            $body = ['entry_date' => $data['entry_date'], 'reason' => $data['reason'], 'revision' => $group['revision'], 'operation_key' => $this->key($data['operation_key'], $id), 'lines' => $group['lines']];
            $plan = $this->corrections->preview($entry, $body);
            $plans[] = [...$group, 'request' => $body, 'posting_lines' => $plan['posting_lines']];
        }
        $totals = [];
        foreach ($plans as $group) {
            foreach ($group['posting_lines'] as $line) {
                $code = $line['account_code'];
                $totals[$code] ??= ['account_code' => $code, 'debit' => '0.00', 'credit' => '0.00'];
                foreach (['debit', 'credit'] as $side) {
                    $totals[$code][$side] = (string) BigDecimal::of($totals[$code][$side])->plus($line[$side])->toScale(2);
                }
            }
        }ksort($totals);
        $result = ['account_totals' => array_values($totals), 'entry_date' => $data['entry_date'], 'reason' => $data['reason'], 'selected_count' => count($items), 'eligible_count' => array_sum(array_map(fn ($g) => count($g['lines']), $plans)), 'adjustment_count' => count($plans), 'entries' => $plans, 'blocked' => $blocked, 'originals_preserved' => true];
        $result['preview_revision'] = hash_hmac('sha256', json_encode($result, JSON_THROW_ON_ERROR), config('app.key'));

        return $result;
    }

    /** Lock all sources before fiscal years, matching ACC-01 and cancellation lock order. */
    private function lockSources(array $ids): void
    {
        $locks = [];
        foreach (JournalEntry::whereIn('id', $ids)->with('source')->orderBy('id')->get() as $e) {
            $s = $e->source;
            if ($s instanceof SalesDocumentPayment) {
                $locks[SalesDocument::class][] = $s->sales_document_id;
                $locks[SalesDocumentPayment::class][] = $s->id;
            } elseif ($s instanceof SupplierPayment) {
                if ($s->purchase_document_id) {
                    $locks[PurchaseDocument::class][] = $s->purchase_document_id;
                }if ($s->expense_id) {
                    $locks[Expense::class][] = $s->expense_id;
                }$locks[SupplierPayment::class][] = $s->id;
            } elseif ($s instanceof SalesDocument || $s instanceof PurchaseDocument || $s instanceof Expense) {
                $locks[$s::class][] = $s->id;
            }
        }
        foreach ([SalesDocument::class, PurchaseDocument::class, Expense::class, SalesDocumentPayment::class, SupplierPayment::class] as $class) {
            if (isset($locks[$class])) {
                $class::withoutGlobalScopes()->whereIn('id', array_unique($locks[$class]))->orderBy('id')->lockForUpdate()->get();
            }
        }
    }

    public function execute(array $data, User $actor): AccountingReclassificationBatch
    {
        abort_unless($actor->can('correct-journal-entries'), 403);
        $hash = hash('sha256', json_encode($data, JSON_THROW_ON_ERROR));

        try {
            return DB::transaction(function () use ($data, $actor, $hash) {
                if (DB::getDriverName() === 'pgsql') {
                    DB::statement("SET LOCAL lock_timeout='3s'");
                    DB::select('SELECT pg_advisory_xact_lock(90291030)');
                } else {
                    DB::table('ledger_accounts')->orderBy('id')->lockForUpdate()->first();
                }
                if ($existing = AccountingReclassificationBatch::find($data['operation_key'])) {
                    if ($existing->created_by !== $actor->id || ! hash_equals($existing->request_hash, $hash)) {
                        $this->fail('The batch key was already used for a different request.');
                    }

                    return $existing;
                }
                $ids = JournalLine::whereIn('id', array_column($data['items'], 'line_id'))->pluck('journal_entry_id')->unique()->all();
                $this->lockSources($ids);
                $years = JournalEntry::whereIn('id', $ids)->pluck('fiscal_year_id')->merge(FiscalYear::whereDate('starts_on', '<=', $data['entry_date'])->whereDate('ends_on', '>=', $data['entry_date'])->pluck('id'))->unique()->sort();
                FiscalYear::whereIn('id', $years)->orderBy('starts_on')->orderBy('id')->lockForUpdate()->get();
                JournalEntry::whereIn('id', $ids)->orderBy('id')->lockForUpdate()->get();
                LedgerAccount::whereIn('code', array_column($data['items'], 'account_code'))->orderBy('id')->lockForUpdate()->get();
                $plan = $this->preview($data, $actor);
                if (! hash_equals($plan['preview_revision'], $data['preview_revision'])) {
                    $this->fail('The selection changed after preview. Review the batch again.');
                }if (! $plan['eligible_count']) {
                    $this->fail('There are no eligible lines to reclassify.');
                }if ($plan['blocked'] && empty($data['confirm_partial'])) {
                    $this->fail('Confirm explicitly that blocked lines will remain unchanged.');
                }
                $results = ['account_totals' => $plan['account_totals'], 'entries' => [], 'corrections' => [], 'blocked' => $plan['blocked']];
                foreach ($plan['entries'] as $group) {
                    $c = $this->corrections->correct(JournalEntry::findOrFail($group['entry_id']), $group['request'], $actor);
                    $results['entries'][] = $group['entry_id'];
                    $results['corrections'][] = ['entry_id' => $group['entry_id'], 'correction_id' => $c->id, 'adjustment_entry_id' => $c->correction_entry_id, 'effects' => $group['effects']];
                }
                $batch = AccountingReclassificationBatch::create(['id' => $data['operation_key'], 'created_by' => $actor->id, 'request_hash' => $hash, 'preview_revision' => $data['preview_revision'], 'correction_date' => $data['entry_date'], 'reason' => $data['reason'], 'selection' => $data['items'], 'results' => $results, 'recorded_at_utc' => BusinessTime::now()]);
                AuditLog::create(['user_id' => $actor->id, 'event' => 'accounting_reclassification_batch', 'auditable_type' => JournalEntry::class, 'auditable_id' => $plan['entries'][0]['entry_id'], 'new_values' => ['batch_id' => $batch->id, 'correction_ids' => array_column($results['corrections'], 'correction_id'), 'reason' => $data['reason']]]);

                return $batch;
            });
        } catch (QueryException $e) {
            if (in_array($e->errorInfo[0] ?? (string) $e->getCode(), ['55P03', '40P01', '40001'], true)) {
                $this->fail('Another accounting operation is in progress. Review the batch and retry safely.');
            }
            throw $e;
        }
    }
}
