<?php

namespace App\Http\Controllers;

use App\Models\CorporateTaxReturn;
use App\Models\FiscalYear;
use App\Services\Accounting\CorporateTaxCalculator;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class CorporateTaxController extends Controller
{
    public function index(Request $request, CorporateTaxCalculator $calculator): Response
    {
        $year = FiscalYear::where('year', $request->integer('year', now()->year))->first() ?? FiscalYear::orderByDesc('year')->firstOrFail();
        $taxReturn = CorporateTaxReturn::whereBelongsTo($year)->first();
        $defaults = ['regime' => 'general', 'prior_year_revenue' => 0, 'permanent_positive' => 0, 'permanent_negative' => 0, 'temporary_positive' => 0, 'temporary_negative' => 0, 'loss_carryforward' => 0, 'deductions' => 0, 'withholdings' => 0, 'instalment_payments' => 0];
        $input = $taxReturn ? $taxReturn->only(array_keys($defaults)) : $defaults;

        return Inertia::render('corporate-tax/index', ['fiscalYear' => $year, 'years' => FiscalYear::orderByDesc('year')->get(['id', 'year', 'status']), 'taxReturn' => $taxReturn, 'preview' => $calculator->calculate($year, $input)]);
    }

    public function prepare(Request $request, CorporateTaxCalculator $calculator): RedirectResponse
    {
        $data = $this->validated($request);
        $year = FiscalYear::findOrFail($data['fiscal_year_id']);
        $calculation = $calculator->calculate($year, $data);
        CorporateTaxReturn::updateOrCreate(['fiscal_year_id' => $year->id], [...$data, ...collect($calculation)->only(['accounting_result', 'taxable_base', 'gross_tax', 'net_tax'])->all(), 'status' => 'prepared', 'calculation' => $calculation, 'prepared_by' => $request->user()->id, 'prepared_at' => now(), 'filing_reference' => null, 'filed_on' => null, 'filed_by' => null, 'receipt_path' => null]);

        return back()->with('toast', ['type' => 'success', 'message' => __('Corporate tax calculation prepared.')]);
    }

    public function file(Request $request, CorporateTaxReturn $corporateTaxReturn): RedirectResponse
    {
        abort_unless($corporateTaxReturn->status === 'prepared', 422);
        $data = $request->validate(['filing_reference' => ['required', 'string', 'max:100'], 'filed_on' => ['required', 'date'], 'receipt' => ['required', 'file', 'mimes:pdf', 'max:10240']]);
        if ($corporateTaxReturn->receipt_path) {
            Storage::disk('local')->delete($corporateTaxReturn->receipt_path);
        }
        $corporateTaxReturn->update(['status' => 'filed', 'filing_reference' => $data['filing_reference'], 'filed_on' => $data['filed_on'], 'filed_by' => $request->user()->id, 'receipt_path' => $data['receipt']->store('tax/corporate', 'local')]);

        return back()->with('toast', ['type' => 'success', 'message' => __('Corporate tax filing recorded.')]);
    }

    public function receipt(CorporateTaxReturn $corporateTaxReturn): BinaryFileResponse
    {
        abort_unless($corporateTaxReturn->receipt_path && Storage::disk('local')->exists($corporateTaxReturn->receipt_path), 404);

        return response()->download(Storage::disk('local')->path($corporateTaxReturn->receipt_path));
    }

    private function validated(Request $request): array
    {
        return $request->validate(['fiscal_year_id' => ['required', 'exists:fiscal_years,id'], 'regime' => ['required', Rule::in(['general', 'micro', 'reduced', 'new_entity'])], 'prior_year_revenue' => ['required', 'numeric', 'min:0'], 'permanent_positive' => ['required', 'numeric', 'min:0'], 'permanent_negative' => ['required', 'numeric', 'min:0'], 'temporary_positive' => ['required', 'numeric', 'min:0'], 'temporary_negative' => ['required', 'numeric', 'min:0'], 'loss_carryforward' => ['required', 'numeric', 'min:0'], 'deductions' => ['required', 'numeric', 'min:0'], 'withholdings' => ['required', 'numeric', 'min:0'], 'instalment_payments' => ['required', 'numeric', 'min:0']]);
    }
}
