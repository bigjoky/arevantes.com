<?php

namespace App\Http\Controllers;

use App\Models\Expense;
use App\Models\LedgerAccount;
use App\Models\Person;
use App\Models\Supplier;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class ChartOfAccountsController extends Controller
{
    public function index(Request $request): Response
    {
        $search = trim($request->string('search')->toString());
        $accounts = LedgerAccount::query()->when($search, fn ($q) => $q->where(fn ($q) => $q->where('code', 'like', "%$search%")->orWhere('name', 'like', "%$search%")))->orderBy('code')->paginate(25)->withQueryString();

        return Inertia::render('accounting/accounts', ['accounts' => $accounts, 'search' => $search, 'parents' => LedgerAccount::where('is_active', true)->orderBy('code')->get(['code', 'name', 'type'])]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validated($request, true);
        LedgerAccount::create([...$data, 'level' => strlen($data['code']), 'source' => 'custom', 'source_version' => null]);

        return back()->with('toast', ['type' => 'success', 'message' => __('Accounting account created.')]);
    }

    public function update(Request $request, LedgerAccount $account): RedirectResponse
    {
        $data = $this->validated($request, false);
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

        return back()->with('toast', ['type' => 'success', 'message' => __('Accounting account updated.')]);
    }

    public function destroy(LedgerAccount $account): RedirectResponse
    {
        if (! in_array($account->source, ['custom', 'customized'], true) || $account->lines()->exists() || LedgerAccount::where('parent_code', $account->code)->exists()
            || Supplier::where('payable_account_code', $account->code)->orWhere('service_payable_account_code', $account->code)->exists()
            || Person::where('receivable_account_code', $account->code)->exists()
            || Expense::where('creditor_account_code', $account->code)->exists()) {
            throw ValidationException::withMessages(['account' => __('Only unused custom accounts without child accounts can be deleted.')]);
        }
        $account->delete();

        return back()->with('toast', ['type' => 'success', 'message' => __('Accounting account deleted.')]);
    }

    private function validated(Request $request, bool $creating): array
    {
        if ($request->input('parent_code') === 'none') {
            $request->merge(['parent_code' => null]);
        }

        return $request->validate([
            'code' => [$creating ? 'required' : 'prohibited', 'string', 'regex:/^[0-9]{3,20}$/', Rule::unique('ledger_accounts', 'code')],
            'name' => ['required', 'string', 'max:255'], 'type' => ['required', Rule::in(['asset', 'liability', 'equity', 'income', 'expense'])],
            'parent_code' => ['nullable', 'string', Rule::exists('ledger_accounts', 'code')], 'is_postable' => ['required', 'boolean'], 'is_active' => ['required', 'boolean'],
        ]);
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
