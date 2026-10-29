<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\LedgerAccount;
use App\Models\Person;
use App\Models\Supplier;
use App\Services\Accounting\AccountingAccountPolicy;
use App\Services\Accounting\AccountingWorkspace;
use App\Services\Accounting\AccountUsage;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class ChartOfAccountsController extends Controller
{
    public function index(Request $request): Response
    {
        if ($request->input('mode') !== 'manage') {
            return app(AccountingWorkspaceController::class)->index($request, app(AccountingWorkspace::class));
        }
        $search = trim($request->string('search')->toString());
        $accounts = LedgerAccount::query()->withCount('lines')->when($search, function ($q) use ($search) {
            $q->where(function ($q) use ($search) {
                $q->where('code', 'like', "%$search%")->orWhere('name', 'like', "%$search%")
                    ->orWhereIn('code', Person::where(fn ($p) => $p->where('company_name', 'like', "%$search%")->orWhere('first_name', 'like', "%$search%")->orWhere('last_name', 'like', "%$search%"))->select('receivable_account_code'))
                    ->orWhereIn('code', Supplier::where('legal_name', 'like', "%$search%")->select('payable_account_code'));
            });
        })->when($request->input('usage') === 'used', fn ($q) => $q->has('lines'))
            ->when($request->input('usage') === 'unused', fn ($q) => $q->doesntHave('lines'))
            ->when($request->filled('active'), fn ($q) => $q->where('is_active', $request->boolean('active')))
            ->orderBy('code')->paginate(25)->withQueryString()->through(fn ($a) => [...$a->toArray(), 'effective_type' => AccountingAccountPolicy::nature($a)]);

        return Inertia::render('accounting/accounts', ['accounts' => $accounts, 'search' => $search, 'canConfigure' => $request->user()->can('manage-ledger-accounts'), 'parents' => LedgerAccount::where('is_active', true)->orderBy('code')->get(['code', 'name', 'type'])]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validated($request, true);
        try {
            DB::transaction(function () use ($data, $request) {
                $account = LedgerAccount::create([...$data, 'level' => strlen($data['code']), 'source' => 'custom', 'source_version' => null]);
                AuditLog::create(['user_id' => $request->user()->id, 'event' => 'ledger_account_created', 'auditable_type' => $account->getMorphClass(), 'auditable_id' => $account->id, 'new_values' => $account->toArray()]);
            });
        } catch (UniqueConstraintViolationException) {
            throw ValidationException::withMessages(['code' => __('This accounting code already exists. Select the existing account.')]);
        }

        return back()->with('toast', ['type' => 'success', 'message' => __('Accounting account created.')]);
    }

    public function update(Request $request, LedgerAccount $account): RedirectResponse
    {
        return DB::transaction(function () use ($request, $account) {
            $account = LedgerAccount::whereKey($account->id)->lockForUpdate()->firstOrFail();
            if ($account->lines()->exists() && ($request->input('type') !== $account->type || $request->input('parent_code') !== $account->parent_code)) {
                throw ValidationException::withMessages(['account' => __('The nature and hierarchy of an account with journal movements cannot be changed.')]);
            }
            $data = $this->validated($request, false);
            $before = $account->getAttributes();
            if ($account->lines()->exists() && ($data['type'] !== $account->type || $data['parent_code'] !== $account->parent_code)) {
                throw ValidationException::withMessages(['account' => __('The nature and hierarchy of an account with journal movements cannot be changed.')]);
            }
            if ($data['parent_code'] === $account->code || $this->isDescendant($data['parent_code'], $account->code)) {
                throw ValidationException::withMessages(['parent_code' => __('An account cannot be placed below itself or one of its children.')]);
            }
            if (! $data['is_postable'] && $account->lines()->exists()) {
                throw ValidationException::withMessages(['is_postable' => __('An account with journal movements must remain postable.')]);
            }
            $account->update([...$data, 'source' => $account->source === 'pgc' ? 'customized' : $account->source]);

            AuditLog::create(['user_id' => $request->user()->id, 'event' => 'ledger_account_updated', 'auditable_type' => $account->getMorphClass(), 'auditable_id' => $account->id, 'old_values' => $before, 'new_values' => $account->getAttributes()]);

            return back()->with('toast', ['type' => 'success', 'message' => __('Accounting account updated.')]);
        });
    }

    public function destroy(LedgerAccount $account): RedirectResponse
    {
        return DB::transaction(function () use ($account) {
            $account = LedgerAccount::whereKey($account->id)->lockForUpdate()->firstOrFail();
            if (! in_array($account->source, ['custom', 'customized'], true) || app(AccountUsage::class)->forAccount($account) || LedgerAccount::where('parent_code', $account->code)->exists()) {
                throw ValidationException::withMessages(['account' => __('Only unused custom accounts without child accounts can be deleted.')]);
            }
            AuditLog::create(['user_id' => auth()->id(), 'event' => 'ledger_account_deleted', 'auditable_type' => $account->getMorphClass(), 'auditable_id' => $account->id, 'old_values' => $account->getAttributes()]);
            $account->delete();

            return back()->with('toast', ['type' => 'success', 'message' => __('Accounting account deleted.')]);
        });
    }

    private function validated(Request $request, bool $creating): array
    {
        if ($request->input('parent_code') === 'none') {
            $request->merge(['parent_code' => null]);
        }

        $data = $request->validate([
            'code' => [$creating ? 'required' : 'prohibited', 'string', 'regex:/^[0-9]{3,20}$/', Rule::unique('ledger_accounts', 'code')],
            'name' => ['required', 'string', 'max:255'], 'type' => ['required', Rule::in(['asset', 'liability', 'equity', 'income', 'expense'])],
            'parent_code' => ['nullable', 'string', Rule::exists('ledger_accounts', 'code')], 'is_postable' => ['required', 'boolean'], 'is_active' => ['required', 'boolean'],
        ]);
        if ($data['parent_code']) {
            $parent = LedgerAccount::where('code', $data['parent_code'])->firstOrFail();
            $code = $creating ? $data['code'] : $request->route('account')->code;
            if (! str_starts_with($code, $parent->code) || strlen($code) <= strlen($parent->code) || $data['type'] !== AccountingAccountPolicy::nature($parent)) {
                throw ValidationException::withMessages(['parent_code' => __('The account must extend its parent code and keep its accounting nature.')]);
            }
        }

        return $data;
    }

    private function isDescendant(?string $parentCode, string $accountCode): bool
    {
        while ($parentCode) {
            if ($parentCode === $accountCode) {
                return true;
            }
            $parentCode = LedgerAccount::where('code', $parentCode)->value('parent_code');
        }

        return false;
    }
}
