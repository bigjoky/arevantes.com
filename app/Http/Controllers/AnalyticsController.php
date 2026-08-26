<?php

namespace App\Http\Controllers;

use App\Models\AnalyticsAlert;
use App\Models\AnalyticsReportSubscription;
use App\Models\Installation;
use App\Models\User;
use App\Services\Analytics\AnalyticsAutomationService;
use App\Services\Analytics\AnalyticsExportService;
use App\Services\Analytics\AnalyticsFilter;
use App\Services\Analytics\AnalyticsService;
use App\Services\Analytics\BusinessLineMarginAnalysis;
use App\Services\Analytics\CommercialConversionAnalytics;
use App\Services\Analytics\RecurringServiceHealth;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\Response as HttpResponse;

class AnalyticsController extends Controller
{
    public function index(Request $request, AnalyticsService $analytics, CommercialConversionAnalytics $conversionAnalytics, BusinessLineMarginAnalysis $marginAnalysis, RecurringServiceHealth $recurringHealth): Response
    {
        $filter = AnalyticsFilter::fromRequest($request);
        $metricKey = $request->string('metric')->toString();
        $definitions = collect(config('analytics.metrics'));
        $selectedMetric = $metricKey !== '' && $definitions->has($metricKey)
            ? $analytics->metric($metricKey, $definitions[$metricKey], $filter)
            : null;
        $metrics = $analytics->metrics($filter);
        $recordLimit = 200;
        $selectedRecords = $selectedMetric ? $analytics->records($metricKey, $filter, $recordLimit + 1) : collect();

        return Inertia::render('analytics/index', [
            'filters' => $filter->toArray(),
            'generatedAt' => now()->toIso8601String(),
            'areas' => collect(AnalyticsFilter::allowedAreas($request->user()))->map(fn ($key) => ['key' => $key, 'name' => config("analytics.areas.{$key}")])->values(),
            'metrics' => $metrics,
            'dictionary' => $definitions->map(fn (array $definition, string $key) => ['key' => $key, ...$definition])->values(),
            'selectedMetric' => $selectedMetric,
            'records' => $selectedRecords->take($recordLimit)->values(),
            'recordsHasMore' => $selectedRecords->count() > $recordLimit,
            'commercials' => User::query()->whereIn('role', ['admin', 'commercial'])
                ->when($filter->restrictsToOwnCommercialScope(), fn ($query) => $query->whereKey($request->user()->id))
                ->orderBy('name')->get(['id', 'name']),
            'projects' => Installation::query()
                ->when($request->user()->hasRole('technician'), fn ($query) => $query->where('technician_user_id', $request->user()->id))
                ->when($filter->restrictsToOwnCommercialScope(), fn ($query) => $query->where('commercial_user_id', $request->user()->id))
                ->orderByDesc('created_at')->limit(500)->get(['id', 'project_reference', 'site_name']),
            'alerts' => $request->user()->analyticsAlerts()->latest()->get()->map(fn (AnalyticsAlert $alert) => [
                ...$alert->toArray(),
                'current_value' => $alert->last_value !== null ? (float) $alert->last_value : null,
                'triggered' => $alert->enabled && $alert->is_triggered,
            ]),
            'subscriptions' => $request->user()->analyticsReportSubscriptions()->get(),
            'commercialConversions' => $filter->area === 'sales' ? $conversionAnalytics->forAnalytics($filter) : null,
            'projectMargins' => $request->user()->can('view-finance') && in_array($filter->area, ['executive', 'finance'], true)
                ? $marginAnalysis->build($filter)
                : null,
            'recurringHealth' => in_array($filter->area, ['executive', 'sales', 'operations'], true)
                ? $recurringHealth->build($filter, $request->user()->can('view-finance'))
                : null,
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
            'channel' => ['nullable', Rule::in(['database', 'mail'])],
        ]);
        $request->user()->analyticsAlerts()->create([
            ...$data,
            'area' => $filter->area,
            'filters' => $filter->toArray(),
            'channel' => $data['channel'] ?? 'database',
        ]);

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

    public function storeSubscription(Request $request, AnalyticsAutomationService $automation): RedirectResponse
    {
        $filter = AnalyticsFilter::fromRequest($request);
        $data = $request->validate([
            'area' => ['required', Rule::in(AnalyticsFilter::allowedAreas($request->user()))],
            'frequency' => ['required', Rule::in(['daily', 'weekly', 'monthly'])],
            'format' => ['required', Rule::in(['excel', 'pdf'])],
        ]);
        AnalyticsReportSubscription::updateOrCreate(
            ['user_id' => $request->user()->id, 'area' => $data['area'], 'frequency' => $data['frequency'], 'format' => $data['format']],
            [
                ...$data,
                'filters' => $filter->toArray(),
                'timezone' => config('app.timezone', 'Europe/Madrid'),
                'enabled' => true,
                'next_send_at' => $automation->nextSendAt($data['frequency'], config('app.timezone', 'Europe/Madrid')),
            ],
        );

        return back()->with('toast', ['type' => 'success', 'message' => __('Suscripción guardada.')]);
    }

    public function destroySubscription(Request $request, AnalyticsReportSubscription $analyticsReportSubscription): RedirectResponse
    {
        abort_unless($analyticsReportSubscription->user_id === $request->user()->id, 403);
        $analyticsReportSubscription->delete();

        return back()->with('toast', ['type' => 'success', 'message' => __('Suscripción eliminada.')]);
    }

    public function export(Request $request, AnalyticsService $analytics, AnalyticsExportService $exports, string $format): HttpResponse
    {
        abort_unless(in_array($format, ['excel', 'pdf'], true), 404);
        $filter = AnalyticsFilter::fromRequest($request);
        $filename = 'analytics-'.$filter->area.'-'.$filter->from->toDateString().'-'.$filter->to->toDateString();
        $report = $exports->render($filter, $analytics, $format);

        return response($report['contents'], 200, [
            'Content-Type' => $report['mime'],
            'Content-Disposition' => "attachment; filename=\"{$filename}.{$report['extension']}\"",
        ]);
    }
}
