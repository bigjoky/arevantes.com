<?php

namespace App\Http\Controllers;

use App\Models\CompanyProfile;
use App\Models\CorporateTaxReturn;
use App\Models\TaxReturn;
use App\Services\Tax\FiscalWorkspace;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class FiscalDeclarationController extends Controller
{
    public function show(Request $request, string $model, int $id, FiscalWorkspace $workspace): Response
    {
        abort_if(CompanyProfile::count() > 1, 409, __('Fiscal company separation is not configured.'));
        $record = $model === '303' ? TaxReturn::where('tax_type', 'vat')->findOrFail($id) : CorporateTaxReturn::with('fiscalYear')->findOrFail($id);
        $year = $model === '303' ? $record->year : $record->fiscalYear->year;
        $row = $workspace->declarations($year)->where('model', $model)->where('id', $id)->first();
        $declaration = $workspace->declarationRows([$row])[0];
        $snapshot = $model === '303' ? $record->snapshot : $record->calculation;

        return Inertia::render('fiscal/declaration', ['declaration' => $declaration, 'year' => $year, 'summary' => ['outputVat' => $model === '303' ? FiscalWorkspace::decimal($record->output_vat) : null, 'inputVat' => $model === '303' ? FiscalWorkspace::decimal($record->input_vat) : null, 'paid' => $model === '303' ? FiscalWorkspace::decimal($record->paid_amount) : null, 'calculationVersion' => $snapshot['version'] ?? $snapshot['tax_rule_version'] ?? $snapshot['rules_version'] ?? null, 'hasSnapshot' => ! empty($snapshot)], 'capabilities' => ['manageTax' => Gate::allows('manage-tax')], 'manageHref' => $model === '303' ? '/tax?year='.$year.'&quarter='.$record->quarter : '/corporate-tax?year='.$year]);
    }
}
