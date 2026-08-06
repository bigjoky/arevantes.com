<?php

namespace App\Http\Controllers;

use App\Models\AnalyticsAlert;
use App\Models\AnalyticsReportSubscription;
use App\Models\Installation;
use App\Models\User;
use App\Services\Analytics\AnalyticsFilter;
use App\Services\Analytics\AnalyticsService;
use Dompdf\Dompdf;
use Dompdf\Options;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\Response as HttpResponse;

class AnalyticsController extends Controller
{
    public function index(Request $request, AnalyticsService $analytics): Response
    {
        $filter = AnalyticsFilter::fromRequest($request);
        $metricKey = $request->string('metric')->toString();
        $definitions = collect(config('analytics.metrics'));
        $selectedMetric = $metricKey !== '' && $definitions->has($metricKey)
            ? $analytics->metric($metricKey, $definitions[$metricKey], $filter)
            : null;
        $metrics = $analytics->metrics($filter);
        $currentValues = $metrics->keyBy('key');

        return Inertia::render('analytics/index', [
            'filters' => $filter->toArray(),
            'areas' => collect(AnalyticsFilter::allowedAreas($request->user()))->map(fn ($key) => ['key' => $key, 'name' => config("analytics.areas.{$key}")])->values(),
            'metrics' => $metrics,
            'dictionary' => $definitions->map(fn (array $definition, string $key) => ['key' => $key, ...$definition])->values(),
            'selectedMetric' => $selectedMetric,
            'records' => $selectedMetric ? $analytics->records($metricKey, $filter) : [],
            'commercials' => User::query()->whereIn('role', ['admin', 'commercial'])->orderBy('name')->get(['id', 'name']),
            'projects' => Installation::query()->when($request->user()->hasRole('technician'), fn ($query) => $query->where('technician_user_id', $request->user()->id))->orderByDesc('created_at')->limit(500)->get(['id', 'project_reference', 'site_name']),
            'alerts' => $request->user()->analyticsAlerts()->latest()->get()->map(function (AnalyticsAlert $alert) use ($currentValues) {
                $current = $currentValues->get($alert->metric_key)['value'] ?? null;

                return [
                    ...$alert->toArray(),
                    'current_value' => $current,
                    'triggered' => $alert->enabled && $current !== null && match ($alert->operator) {
                        '>' => $current > (float) $alert->threshold,
                        '>=' => $current >= (float) $alert->threshold,
                        '<' => $current < (float) $alert->threshold,
                        '<=' => $current <= (float) $alert->threshold,
                        '=' => $current === (float) $alert->threshold,
                        default => false,
                    },
                ];
            }),
            'subscriptions' => $request->user()->analyticsReportSubscriptions()->get(),
        ]);
    }

    public function storeAlert(Request $request): RedirectResponse
    {
        $filter = AnalyticsFilter::fromRequest($request);
        $definition = config('analytics.metrics.'.$request->input('metric_key'));
        abort_unless($definition && in_array($filter->area, $definition['areas'], true), 403);
        $data = $request->validate([
            'metric_key' => ['required', Rule::in(array_keys(config('analytics.metrics')))],
            'operator' => ['required', Rule::in(['>', '>=', '<', '<=', '='])],
            'threshold' => ['required', 'numeric', 'between:-99999999999999,99999999999999'],
        ]);
        $request->user()->analyticsAlerts()->create($data);

        return back()->with('toast', ['type' => 'success', 'message' => __('Alerta de KPI creada.')]);
    }

    public function updateAlert(Request $request, AnalyticsAlert $analyticsAlert): RedirectResponse
    {
        abort_unless($analyticsAlert->user_id === $request->user()->id, 403);
        $data = $request->validate(['enabled' => ['required', 'boolean']]);
        $analyticsAlert->update($data);

        return back()->with('toast', ['type' => 'success', 'message' => __('Alerta actualizada.')]);
    }

    public function destroyAlert(Request $request, AnalyticsAlert $analyticsAlert): RedirectResponse
    {
        abort_unless($analyticsAlert->user_id === $request->user()->id, 403);
        $analyticsAlert->delete();

        return back()->with('toast', ['type' => 'success', 'message' => __('Alerta eliminada.')]);
    }

    public function storeSubscription(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'area' => ['required', Rule::in(AnalyticsFilter::allowedAreas($request->user()))],
            'frequency' => ['required', Rule::in(['daily', 'weekly', 'monthly'])],
            'format' => ['required', Rule::in(['excel', 'pdf'])],
        ]);
        AnalyticsReportSubscription::updateOrCreate(
            ['user_id' => $request->user()->id, 'area' => $data['area']],
            [...$data, 'enabled' => true],
        );

        return back()->with('toast', ['type' => 'success', 'message' => __('Suscripción guardada.')]);
    }

    public function destroySubscription(Request $request, AnalyticsReportSubscription $analyticsReportSubscription): RedirectResponse
    {
        abort_unless($analyticsReportSubscription->user_id === $request->user()->id, 403);
        $analyticsReportSubscription->delete();

        return back()->with('toast', ['type' => 'success', 'message' => __('Suscripción eliminada.')]);
    }

    public function export(Request $request, AnalyticsService $analytics, string $format): HttpResponse
    {
        abort_unless(in_array($format, ['excel', 'pdf'], true), 404);
        $filter = AnalyticsFilter::fromRequest($request);
        $metrics = $analytics->metrics($filter);
        $records = $metrics->mapWithKeys(fn (array $metric) => [$metric['key'] => $analytics->records($metric['key'], $filter)]);
        $filename = 'analytics-'.$filter->area.'-'.$filter->from->toDateString().'-'.$filter->to->toDateString();

        if ($format === 'pdf') {
            $options = new Options;
            $options->set('defaultFont', 'DejaVu Sans');
            $pdf = new Dompdf($options);
            $pdf->loadHtml(view('pdf.analytics-report', compact('filter', 'metrics', 'records'))->render());
            $pdf->setPaper('A4', 'landscape');
            $pdf->render();

            return response($pdf->output(), 200, ['Content-Type' => 'application/pdf', 'Content-Disposition' => "attachment; filename=\"{$filename}.pdf\""]);
        }

        $xml = view('exports.analytics-excel', compact('filter', 'metrics', 'records'))->render();

        return response("\xEF\xBB\xBF".$xml, 200, [
            'Content-Type' => 'application/vnd.ms-excel; charset=UTF-8',
            'Content-Disposition' => "attachment; filename=\"{$filename}.xls\"",
        ]);
    }
}
