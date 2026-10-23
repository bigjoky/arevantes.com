<?php

namespace App\Http\Controllers;

use App\Models\CompanyCensusVersion;
use App\Models\CompanyProfile;
use App\Services\Tax\CompanyCensus;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class CompanyCensusController extends Controller
{
    public function index(Request $request, CompanyCensus $census): Response
    {
        abort_unless($request->user()->hasRole('admin'), 403);
        $data = $request->validate(['year' => ['nullable', 'integer', 'between:2020,2100']]);
        $year = (int) ($data['year'] ?? now()->year);
        $company = CompanyProfile::firstOrFail();

        return Inertia::render('tax/company-census', ['year' => $year, 'company' => $census->identity($company),
            'revision' => $census->revision($company, $year), 'check' => $census->check($company, $year),
            'protection' => $census->protectionIssues($year),
            'history' => CompanyCensusVersion::where('company_profile_id', $company->id)->where('year', $year)->latest('id')->limit(30)->get()]);
    }

    public function store(Request $request, CompanyCensus $census): RedirectResponse
    {
        $rules = ['year' => ['required', 'integer', 'between:2020,2100'], 'revision' => ['required', 'string', 'size:64'],
            'action' => ['required', Rule::in(['draft', 'review'])], 'settings' => ['required', 'array:'.implode(',', array_keys(CompanyCensus::OPTIONS))],
            'reason' => ['required', 'string', 'max:1000'], 'source_reference' => ['nullable', 'string', 'max:1000'], 'confirmed' => ['nullable', 'boolean']];
        foreach (CompanyCensus::OPTIONS as $field => $options) {
            $rules['settings.'.$field] = ['required', Rule::in($options)];
        }
        $census->save($request->validate($rules), $request);

        return back()->with('toast', ['type' => 'success', 'message' => __('Census profile version saved. No tax return has been submitted.')]);
    }
}
