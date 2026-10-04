<?php

namespace App\Services\Tax;

use App\Models\CompanyCensusVersion;
use App\Models\CompanyProfile;
use App\Models\FiscalYear;
use App\Models\TaxReturn;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class CompanyCensus
{
    public const OPTIONS = [
        'model303' => ['unknown', 'yes', 'no'],
        'vat_regime' => ['unknown', 'general', 'simplified', 'mixed'],
        'periodicity' => ['unknown', 'quarterly', 'monthly'],
        'territory' => ['unknown', 'common', 'foral', 'mixed'],
        'cash_accounting' => ['unknown', 'yes', 'no'],
        'cash_recipient' => ['unknown', 'yes', 'no'],
        'prorrata' => ['unknown', 'none', 'general', 'special'],
        'redeme' => ['unknown', 'yes', 'no'],
        'sii' => ['unknown', 'yes', 'no'],
    ];

    public function identity(CompanyProfile $company): array
    {
        return $company->only(['legal_name', 'tax_id', 'address', 'postal_code', 'city', 'province', 'country']);
    }

    public function fingerprint(CompanyProfile $company): string
    {
        return hash('sha256', json_encode($this->identity($company), JSON_THROW_ON_ERROR));
    }

    public function latest(CompanyProfile $company, int $year): ?CompanyCensusVersion
    {
        return CompanyCensusVersion::where('company_profile_id', $company->id)->where('year', $year)->latest('id')->first();
    }

    public function revision(CompanyProfile $company, int $year): string
    {
        return hash('sha256', $year.':'.$this->fingerprint($company).':'.($this->latest($company, $year)?->id ?? 0));
    }

    public function check(CompanyProfile $company, int $year): array
    {
        $version = $this->latest($company, $year);
        $current = $version && $version->status === 'reviewed' && hash_equals($version->company_fingerprint, $this->fingerprint($company));
        $supportedSettings = [
            'model303' => 'yes', 'vat_regime' => 'general', 'periodicity' => 'quarterly', 'territory' => 'common',
            'cash_accounting' => 'no', 'cash_recipient' => 'no', 'prorrata' => 'none', 'redeme' => 'no', 'sii' => 'no',
        ];
        $supported = $current && count($version->settings) === count($supportedSettings)
            && array_diff_assoc($supportedSettings, $version->settings) === [];

        return ['current' => (bool) $current, 'supported' => (bool) $supported,
            'version_id' => $version?->id, 'status' => $version?->status, 'author' => $version?->author_name,
            'settings' => $version?->settings, 'source_reference' => $version?->source_reference];
    }

    public function protectionIssues(int $year): array
    {
        $issues = [];
        if (! FiscalYear::where('year', $year)->where('status', 'open')->exists()) {
            $issues[] = 'The fiscal year is not open.';
        }
        if (TaxReturn::where('tax_type', 'vat')->where('year', $year)->whereIn('status', ['prepared', 'filed'])->exists()) {
            $issues[] = 'A prepared or filed VAT return protects this census year.';
        }

        return $issues;
    }

    public function save(array $data, Request $request): void
    {
        abort_unless($request->user()->hasRole('admin') && $request->user()->can('manage-tax'), 403);
        abort_unless(in_array($data['action'], ['draft', 'review'], true), 422);
        DB::transaction(function () use ($data, $request) {
            $company = CompanyProfile::lockForUpdate()->firstOrFail();
            if (! hash_equals($this->revision($company, $data['year']), $data['revision'])) {
                $this->fail('The company identity or census version has changed. Reload before saving.');
            }
            // Serialize against closing and VAT settlement posting without taking a return lock afterwards.
            $year = FiscalYear::where('year', $data['year'])->lockForUpdate()->first();
            if (! $year || $year->status !== 'open') {
                $this->fail('The fiscal year is not open.');
            }
            if (TaxReturn::where('tax_type', 'vat')->where('year', $data['year'])->whereIn('status', ['prepared', 'filed'])->exists()) {
                $this->fail('A prepared or filed VAT return protects this census year.');
            }
            $settings = [];
            foreach (self::OPTIONS as $field => $options) {
                $value = $data['settings'][$field] ?? 'unknown';
                if (! in_array($value, $options, true) || ($data['action'] === 'review' && $value === 'unknown')) {
                    $this->fail('Complete every census selection before marking the profile as reviewed.');
                }
                $settings[$field] = $value;
            }
            if ($data['action'] === 'review' && (! ($data['confirmed'] ?? false) || ! filled($data['source_reference'] ?? null)
                || collect($this->identity($company))->contains(fn ($value) => ! filled($value)))) {
                $this->fail('Review requires complete company identity, a census source and confirmation for the entire fiscal year.');
            }
            CompanyCensusVersion::create(['company_profile_id' => $company->id, 'year' => $data['year'],
                'status' => $data['action'] === 'review' ? 'reviewed' : 'draft', 'settings' => $settings,
                'company_snapshot' => $this->identity($company), 'company_fingerprint' => $this->fingerprint($company),
                'created_by' => $request->user()->id, 'author_name' => $request->user()->name,
                'source_reference' => $data['source_reference'] ?? null, 'reason' => $data['reason']]);
        });
    }

    private function fail(string $message): never
    {
        throw ValidationException::withMessages(['settings' => __($message)]);
    }
}
