<?php

namespace App\Http\Controllers;

use App\Models\AccountingTemplate;
use App\Models\LedgerAccount;
use App\Services\Accounting\JournalPoster;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class AccountingTemplateController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('accounting/templates', ['templates' => AccountingTemplate::with('lines.account:code,name')->orderByDesc('is_system')->orderBy('name')->get(), 'accounts' => LedgerAccount::where('is_active', true)->where('is_postable', true)->orderBy('code')->get(['code', 'name'])]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validated($request);
        $template = DB::transaction(function () use ($data, $request) {
            $template = AccountingTemplate::create(['name' => $data['name'], 'description' => $data['description'] ?? null, 'created_by' => $request->user()->id]);
            $this->replaceLines($template, $data['lines']);

            return $template;
        });

        return back()->with('toast', ['type' => 'success', 'message' => __('Accounting template created.')]);
    }

    public function update(Request $request, AccountingTemplate $template): RedirectResponse
    {
        abort_if($template->is_system, 403);
        $data = $this->validated($request);
        DB::transaction(function () use ($template, $data) {
            $template->update(['name' => $data['name'], 'description' => $data['description'] ?? null, 'is_active' => $data['is_active']]);
            $template->lines()->delete();
            $this->replaceLines($template, $data['lines']);
        });

        return back()->with('toast', ['type' => 'success', 'message' => __('Accounting template updated.')]);
    }

    public function destroy(AccountingTemplate $template): RedirectResponse
    {
        abort_if($template->is_system, 403);
        $template->delete();

        return back()->with('toast', ['type' => 'success', 'message' => __('Accounting template deleted.')]);
    }

    public function execute(Request $request, AccountingTemplate $template, JournalPoster $poster): RedirectResponse
    {
        abort_unless($template->is_active, 422);
        $data = $request->validate(['entry_date' => ['required', 'date'], 'amount' => ['required', 'numeric', 'min:.01'], 'description' => ['required', 'string', 'max:255']]);
        $template->load('lines');
        $lines = $template->lines->map(fn ($line) => ['account_code' => $line->account_code, 'description' => $line->description, 'debit' => $line->side === 'debit' ? round($data['amount'] * $line->percentage / 100, 2) : 0, 'credit' => $line->side === 'credit' ? round($data['amount'] * $line->percentage / 100, 2) : 0])->all();
        foreach (['debit', 'credit'] as $side) {
            $difference = round((float) $data['amount'] - collect($lines)->sum($side), 2);
            $index = collect($lines)->search(fn ($line) => $line[$side] > 0);
            if ($index !== false) {
                $lines[$index][$side] = round($lines[$index][$side] + $difference, 2);
            }
        }
        $poster->post($data['entry_date'], $data['description'], $lines, $request->user()->id, 'template', null, 'template');

        return redirect()->route('accounting.index', ['year' => substr($data['entry_date'], 0, 4)])->with('toast', ['type' => 'success', 'message' => __('Template posted as a journal entry.')]);
    }

    private function validated(Request $request): array
    {
        $data = $request->validate(['name' => ['required', 'string', 'max:100'], 'description' => ['nullable', 'string', 'max:255'], 'is_active' => ['sometimes', 'boolean'], 'lines' => ['required', 'array', 'min:2', 'max:20'], 'lines.*.account_code' => ['required', Rule::exists('ledger_accounts', 'code')->where(fn ($q) => $q->where('is_active', true)->where('is_postable', true))], 'lines.*.side' => ['required', Rule::in(['debit', 'credit'])], 'lines.*.percentage' => ['required', 'numeric', 'gt:0', 'max:100'], 'lines.*.description' => ['nullable', 'string', 'max:255']]);
        $data['is_active'] ??= true;
        foreach (['debit', 'credit'] as $side) {
            if (abs(collect($data['lines'])->where('side', $side)->sum('percentage') - 100) > .001) {
                throw ValidationException::withMessages(['lines' => __('Debit and credit percentages must each total 100%.')]);
            }
        }

        return $data;
    }

    private function replaceLines(AccountingTemplate $template, array $lines): void
    {
        foreach ($lines as $position => $line) {
            $template->lines()->create([...$line, 'position' => $position]);
        }
    }
}
