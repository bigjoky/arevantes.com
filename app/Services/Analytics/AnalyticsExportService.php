<?php

namespace App\Services\Analytics;

use Dompdf\Dompdf;
use Dompdf\Options;

class AnalyticsExportService
{
    private const RECORD_LIMIT_PER_METRIC = 10000;

    /** @return array{contents: string, extension: string, mime: string} */
    public function render(AnalyticsFilter $filter, AnalyticsService $analytics, string $format): array
    {
        $metrics = $analytics->metrics($filter);
        $rawRecords = $metrics->mapWithKeys(fn (array $metric) => [$metric['key'] => $analytics->records($metric['key'], $filter, self::RECORD_LIMIT_PER_METRIC + 1)]);
        $truncated = $rawRecords->contains(fn ($metricRecords) => $metricRecords->count() > self::RECORD_LIMIT_PER_METRIC);
        $records = $rawRecords->map(fn ($metricRecords) => $metricRecords->take(self::RECORD_LIMIT_PER_METRIC)->values());

        if ($format === 'pdf') {
            $options = new Options;
            $options->set('defaultFont', 'DejaVu Sans');
            $pdf = new Dompdf($options);
            $pdf->loadHtml(view('pdf.analytics-report', compact('filter', 'metrics', 'records', 'truncated'))->render());
            $pdf->setPaper('A4', 'landscape');
            $pdf->render();

            return ['contents' => $pdf->output(), 'extension' => 'pdf', 'mime' => 'application/pdf'];
        }

        return [
            'contents' => "\xEF\xBB\xBF".view('exports.analytics-excel', compact('filter', 'metrics', 'records', 'truncated'))->render(),
            'extension' => 'xls',
            'mime' => 'application/vnd.ms-excel; charset=UTF-8',
        ];
    }
}
