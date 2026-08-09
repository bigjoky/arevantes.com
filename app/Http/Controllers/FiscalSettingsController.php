<?php

namespace App\Http\Controllers;

use App\Models\AccountingPeriod;
use App\Models\CompanyProfile;
use App\Models\DocumentSeries;
use App\Models\FiscalYear;
use App\Models\JournalEntry;
use App\Models\PurchaseDocument;
use App\Models\Quote;
use App\Models\SalesDocument;
use App\Services\Accounting\FiscalYearClosing;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class FiscalSettingsController extends Controller
{
    public function index(): Response
    {
        $years = FiscalYear::with(['series' => fn ($query) => $query->orderBy('document_type')])
            ->orderByDesc('year')
            ->get()
            ->map(fn (FiscalYear $year) => [
                ...$year->toArray(),
                'starts_on' => $year->starts_on->toDateString(),
                'ends_on' => $year->ends_on->toDateString(),
                'has_opening_entry' => JournalEntry::whereMorphedTo('source', $year)->where('entry_kind', 'opening')->whereDoesntHave('reversal')->exists(),
                'closed_periods_count' => AccountingPeriod::where('fiscal_year_id', $year->id)->where('status', 'closed')->count(),
            ]);

        return Inertia::render('fiscal-settings/index', ['company' => CompanyProfile::firstOrFail(), 'fiscalYears' => $years]);
    }

    public function updateCompany(Request $request): RedirectResponse
    {
        $data = $request->validate(['legal_name' => ['required', 'string', 'max:255'], 'trade_name' => ['nullable', 'string', 'max:255'], 'tax_id' => ['required', 'string', 'max:30'], 'address' => ['required', 'string', 'max:255'], 'postal_code' => ['required', 'string', 'max:20'], 'city' => ['required', 'string', 'max:255'], 'province' => ['required', 'string', 'max:255'], 'country' => ['required', 'string', 'max:255'], 'email' => ['required', 'email'], 'phone' => ['nullable', 'string', 'max:40'], 'website' => ['nullable', 'url'], 'iban' => ['nullable', 'string', 'max:34'], 'bic' => ['nullable', 'string', 'max:11'], 'commercial_registry' => ['nullable', 'string', 'max:255'], 'invoice_footer' => ['nullable', 'string', 'max:2000'], 'logo' => ['nullable', 'image', 'mimes:png,jpg,jpeg,webp', 'max:2048']]);
        $profile = CompanyProfile::firstOrFail();
        $logo = $data['logo'] ?? null;
        unset($data['logo']);
        if ($logo) {
            if ($profile->logo_path) {
                Storage::disk('local')->delete($profile->logo_path);
            } $data['logo_path'] = $logo->store('company', 'local');
        }
        $profile->update($data);

        return back()->with('toast', ['type' => 'success', 'message' => __('Company details updated.')]);
    }

    public function logo(): StreamedResponse
    {
        $profile = CompanyProfile::firstOrFail();
        abort_unless($profile->logo_path && Storage::disk('local')->exists($profile->logo_path), 404);

        return Storage::disk('local')->download($profile->logo_path, 'company-logo', ['Content-Disposition' => 'inline']);
    }

    public function storeYear(Request $request): RedirectResponse
    {
        $data = $request->validate(['year' => ['required', 'integer', 'between:2020,2100', 'unique:fiscal_years']]);
        DB::transaction(function () use ($data) {
            $year = FiscalYear::create(['year' => $data['year'], 'starts_on' => "{$data['year']}-01-01", 'ends_on' => "{$data['year']}-12-31", 'status' => 'open']);
            $templates = DocumentSeries::whereNotNull('fiscal_year_id')->orderByDesc('fiscal_year')->get()->unique('document_type');
            foreach ($templates as $template) {
                $year->series()->create(['document_type' => $template->document_type, 'name' => $template->prefix.' '.$data['year'], 'prefix' => $template->prefix, 'next_number' => 1, 'fiscal_year' => $data['year'], 'is_active' => true]);
            }
        });

        return back()->with('toast', ['type' => 'success', 'message' => __('Fiscal year created.')]);
    }

    public function closeYear(Request $request, FiscalYear $fiscalYear, FiscalYearClosing $closing): RedirectResponse
    {
        $request->validate(['confirmation' => ['required', Rule::in([(string) $fiscalYear->year])]]);
        abort_unless($fiscalYear->status === 'open', 422);
        $hasDrafts = Quote::whereYear('issue_date', $fiscalYear->year)->where('status', 'draft')->exists() || SalesDocument::whereYear('issue_date', $fiscalYear->year)->where('status', 'draft')->exists() || PurchaseDocument::whereYear('issue_date', $fiscalYear->year)->where('status', 'draft')->exists();
        abort_if($hasDrafts, 422, __('Resolve draft documents before closing the fiscal year.'));
        $closing->close($fiscalYear, $request->user()->id);

        return back()->with('toast', ['type' => 'success', 'message' => __('Fiscal year closed.')]);
    }

    public function reopenYear(Request $request, FiscalYear $fiscalYear, FiscalYearClosing $closing): RedirectResponse
    {
        $data = $request->validate([
            'confirmation' => ['required', Rule::in([(string) $fiscalYear->year])],
            'reopen_reason' => ['required', 'string', 'min:10', 'max:2000'],
        ]);
        $closing->reopen($fiscalYear, $request->user()->id, $data['reopen_reason']);

        return back()->with('toast', ['type' => 'success', 'message' => __('Fiscal year reopened with audited reversal entries.')]);
    }

    public function openNextYear(Request $request, FiscalYear $fiscalYear, FiscalYearClosing $closing): RedirectResponse
    {
        $closing->openNext($fiscalYear, $request->user()->id);

        return back()->with('toast', ['type' => 'success', 'message' => __('Opening entry generated.')]);
    }

    public function updateSeries(Request $request, DocumentSeries $series): RedirectResponse
    {
        abort_unless($series->fiscalYear?->status === 'open', 422, __('The fiscal year is closed.'));
        $data = $request->validate(['name' => ['required', 'string', 'max:255'], 'prefix' => ['required', 'string', 'max:20'], 'next_number' => ['required', 'integer', 'min:1'], 'is_active' => ['required', 'boolean']]);
        abort_if($series->next_number > 1 && $data['prefix'] !== $series->prefix, 422, __('The prefix of a used series cannot be changed.'));
        abort_if($data['next_number'] < $series->next_number, 422, __('The next number cannot be decreased.'));
        $series->update($data);

        return back()->with('toast', ['type' => 'success', 'message' => __('Document series updated.')]);
    }
}
