<?php

namespace App\Services\Analytics;

use Dompdf\Dompdf;
use Dompdf\Options;

class AnalyticsExportService
{
    /** @return array{contents: string, extension: string, mime: string} */
    public function render(AnalyticsFilter $filter, AnalyticsService $analytics, string $format): array
    {
        $metrics = $analytics->metrics($filter);
        $records = $metrics->mapWithKeys(fn (array $metric) => [$metric['key'] => $analytics->records($metric['key'], $filter)]);

        if ($format === 'pdf') {
            $options = new Options;
            $options->set('defaultFont', 'DejaVu Sans');
            $pdf = new Dompdf($options);
            $pdf->loadHtml(view('pdf.analytics-report', compact('filter', 'metrics', 'records'))->render());
            $pdf->setPaper('A4', 'landscape');
            $pdf->render();

            return ['contents' => $pdf->output(), 'extension' => 'pdf', 'mime' => 'application/pdf'];
        }

        return [
            'contents' => "\xEF\xBB\xBF".view('exports.analytics-excel', compact('filter', 'metrics', 'records'))->render(),
            'extension' => 'xls',
            'mime' => 'application/vnd.ms-excel; charset=UTF-8',
        ];
    }
}
