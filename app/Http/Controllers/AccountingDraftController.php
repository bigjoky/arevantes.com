<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\CompanyProfile;
use App\Models\FiscalYear;
use App\Models\JournalEntry;
use App\Models\LedgerAccount;
use App\Services\Accounting\AccountingPeriodManager;
use App\Services\Accounting\JournalPoster;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class AccountingDraftController extends Controller
{
    public function store(Request $request, JournalPoster $poster, AccountingPeriodManager $periods, ?JournalEntry $entry = null)
    {
        abort_if(CompanyProfile::count() > 1, 409, __('Accounting company separation is not configured.'));
        $data = $request->validate([
            'notes' => 'nullable|string|max:2000', 'reference' => 'nullable|string|max:255', 'entry_date' => 'required|date_format:Y-m-d', 'description' => 'required|string|max:255',
            'lines' => 'required|array|min:2|max:50', 'lines.*.account_code' => ['required', Rule::exists('ledger_accounts', 'code')->where('is_active', true)->where('is_postable', true)],
            'lines.*.description' => 'nullable|string|max:255', 'lines.*.debit' => 'required|numeric|min:0', 'lines.*.credit' => 'required|numeric|min:0',
        ]);
        $lines = $poster->normalizeLines($data['lines'], true);
        $draft = DB::transaction(function () use ($data, $lines, $entry, $request, $periods) {
            $year = FiscalYear::whereDate('starts_on', '<=', $data['entry_date'])->whereDate('ends_on', '>=', $data['entry_date'])->lockForUpdate()->first();
            if (! $year || $year->status !== 'open') {
                throw ValidationException::withMessages(['entry_date' => __('The entry date must belong to an open fiscal year.')]);
            }
            if ($entry && $entry->fiscal_year_id !== $year->id) {
                throw ValidationException::withMessages(['entry_date' => __('A draft must remain in its original fiscal year.')]);
            }
            $periods->assertOpen($data['entry_date']);
            $draft = $entry ? JournalEntry::whereKey($entry->id)->lockForUpdate()->firstOrFail() : new JournalEntry;
            if ($entry && ($draft->status !== 'draft' || $draft->source_type)) {
                throw ValidationException::withMessages(['entry' => __('Only manual drafts can be changed.')]);
            }
            $before = $draft->exists ? [...$draft->getAttributes(), 'lines' => $draft->lines()->get()->toArray()] : null;
            $draft->fill(['entry_date' => $data['entry_date'], 'description' => $data['description'], 'fiscal_year_id' => $year->id, 'entry_kind' => 'manual', 'event' => 'manual', 'status' => 'draft']);
            if (! $entry) {
                $draft->fill(['number' => 'D-'.Str::ulid(), 'created_by' => $request->user()->id]);
            }
            $draft->save();
            $draft->lines()->get()->each->delete();
            foreach ($lines as $line) {
                $account = LedgerAccount::where('code', $line['account_code'])->firstOrFail();
                $draft->lines()->create(['ledger_account_id' => $account->id, 'description' => $line['description'] ?? null, 'debit' => $line['debit'], 'credit' => $line['credit']]);
            }

            AuditLog::create(['user_id' => $request->user()->id, 'event' => 'journal_draft_saved', 'old_values' => $before, 'auditable_type' => $draft->getMorphClass(), 'auditable_id' => $draft->id, 'new_values' => ['description' => $draft->description, 'notes' => $data['notes'] ?? null, 'reference' => $data['reference'] ?? null, 'lines' => $lines]]);

            return $draft;
        });

        return to_route('accounting.entries.show', $draft);
    }

    public function post(Request $request, JournalEntry $entry, JournalPoster $poster)
    {
        abort_if(CompanyProfile::count() > 1, 409, __('Accounting company separation is not configured.'));
        // Load the editable snapshot inside a transaction; concurrent changes use the same year/entry locks.
        DB::transaction(function () use ($entry, $poster, $request) {
            FiscalYear::whereKey($entry->fiscal_year_id)->lockForUpdate()->firstOrFail();
            $draft = JournalEntry::whereKey($entry->id)->lockForUpdate()->firstOrFail();
            if ($draft->status !== 'draft' || $draft->source_type) {
                throw ValidationException::withMessages(['entry' => __('Only manual drafts can be changed.')]);
            }
            $lines = $draft->lines()->with('account')->get()->map(fn ($l) => ['account_code' => $l->account->code, 'description' => $l->description, 'debit' => $l->debit, 'credit' => $l->credit])->all();
            $poster->post($draft->entry_date->toDateString(), $draft->description, $lines, $request->user()->id, draft: $draft);
        });

        return back()->with('toast', ['type' => 'success', 'message' => __('Manual journal entry posted.')]);
    }
}
