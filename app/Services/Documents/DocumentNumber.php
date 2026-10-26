<?php

namespace App\Services\Documents;

use App\Models\DocumentSeries;
use App\Models\FiscalYear;
use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;
use Illuminate\Validation\ValidationException;

class DocumentNumber
{
    public function next(string $type, CarbonInterface|string $date, int $padding = 5): array
    {
        $date = $date instanceof CarbonInterface ? $date : Carbon::parse($date);
        $year = FiscalYear::whereDate('starts_on', '<=', $date)->whereDate('ends_on', '>=', $date)->lockForUpdate()->first();
        if (! $year) {
            throw ValidationException::withMessages(['issue_date' => __('No fiscal year exists for this date.')]);
        }
        if ($year->status !== 'open') {
            throw ValidationException::withMessages(['issue_date' => __('The fiscal year is closed.')]);
        }
        $series = DocumentSeries::where('document_type', $type)->where('fiscal_year_id', $year->id)->where('is_active', true)->lockForUpdate()->first();
        if (! $series) {
            throw ValidationException::withMessages(['issue_date' => __('No active document series exists for this fiscal year.')]);
        }
        if ($type === 'quote') {
            $reserved = app(CommercialNumberReservation::class)->consume('quote', (int) $year->year, $series->prefix, $padding);
            if ($reserved !== null) {
                $series->update(['last_issued_at' => now()]);

                return ['series' => $series, 'number' => $reserved, 'fiscalYear' => $year];
            }
        }

        $number = $series->prefix.'-'.$year->year.'-'.str_pad((string) $series->next_number, $padding, '0', STR_PAD_LEFT);
        $series->update(['next_number' => $series->next_number + 1, 'last_issued_at' => now()]);

        return ['series' => $series, 'number' => $number, 'fiscalYear' => $year];
    }
}
