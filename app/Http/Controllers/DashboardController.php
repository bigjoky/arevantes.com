<?php

namespace App\Http\Controllers;

use App\Models\CrmActivity;
use App\Models\Installation;
use App\Models\Opportunity;
use App\Models\Person;
use App\Models\SalesDocument;
use App\Models\WorkOrder;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    /**
     * Show the dashboard.
     */
    public function __invoke(): Response
    {
        $canViewCustomers = Gate::allows('manage-customers');
        $canViewInstallations = Gate::allows('view-installations');
        $canViewOperations = Gate::allows('manage-operations');
        $canViewFinance = Gate::allows('view-finance');
        $canViewStaff = Gate::allows('manage-staff');

        return Inertia::render('dashboard', [
            'installations' => $canViewInstallations ? Installation::query()
                ->with('customer:id,first_name,last_name,company_name')
                ->latest()
                ->get()
                ->map(fn (Installation $installation) => [
                    'id' => $installation->id,
                    'customer_name' => $installation->customer
                        ? trim($installation->customer->first_name.' '.($installation->customer->last_name ?? ''))
                        : null,
                    'customer_company' => $installation->customer?->company_name,
                    'service_type' => $installation->service_type,
                    'deye_status' => $installation->deye_status,
                    'deye_current_power_kw' => $installation->deye_current_power_kw,
                    'deye_today_energy_kwh' => $installation->deye_today_energy_kwh,
                    'deye_status_message' => $installation->deye_status_message,
                    'huawei_status' => $installation->huawei_status,
                    'huawei_current_power_kw' => $installation->huawei_current_power_kw,
                    'huawei_today_energy_kwh' => $installation->huawei_today_energy_kwh,
                    'huawei_status_message' => $installation->huawei_status_message,
                    'monitoring' => $this->monitoringSummary($installation),
                    'status' => $installation->status,
                    'project_reference' => $installation->project_reference,
                    'site_name' => $installation->site_name,
                    'site_city' => $installation->site_city,
                    'site_locality' => $installation->site_locality,
                    'site_province_state' => $installation->site_province_state,
                    'site_postal_code' => $installation->site_postal_code,
                    'site_country' => $installation->site_country,
                    'site_latitude' => $installation->site_latitude,
                    'site_longitude' => $installation->site_longitude,
                    'scheduled_date' => $installation->scheduled_date?->toDateString(),
                    'system_power_kw' => $installation->system_power_kw,
                    'charger_power_kw' => $installation->charger_power_kw,
                    'created_at' => $installation->created_at?->toDateString(),
                ]) : [],
            'peopleSummary' => [
                'total' => ($canViewCustomers ? Person::where('category', 'customer')->count() : 0)
                    + ($canViewStaff ? Person::where('category', 'staff')->count() : 0),
                'customers' => $canViewCustomers ? Person::where('category', 'customer')->count() : 0,
                'staff' => $canViewStaff ? Person::where('category', 'staff')->count() : 0,
            ],
            'installationSummary' => [
                'total' => $canViewInstallations ? Installation::count() : 0,
                'active' => $canViewInstallations ? Installation::whereNotIn('status', ['completed', 'cancelled'])->count() : 0,
                'completed' => $canViewInstallations ? Installation::where('status', 'completed')->count() : 0,
            ],
            'businessSummary' => [
                'open_opportunities' => $canViewCustomers ? Opportunity::whereNotIn('stage', ['won', 'lost'])->count() : 0,
                'active_work_orders' => $canViewOperations ? WorkOrder::whereNotIn('status', ['completed', 'cancelled'])->count() : 0,
                'overdue_activities' => $canViewOperations ? CrmActivity::whereIn('status', ['pending', 'in_progress'])->whereNotNull('due_at')->where('due_at', '<', now())->count() : 0,
                'outstanding_amount' => $canViewFinance ? round((float) SalesDocument::where('type', 'invoice')->whereIn('status', ['issued', 'partial', 'overdue'])->selectRaw('coalesce(sum(amount - paid_amount), 0) as total')->value('total'), 2) : 0,
            ],
            'agenda' => $canViewOperations ? CrmActivity::query()
                ->with(['installation:id,project_reference,site_name', 'assignedUser:id,name'])
                ->whereIn('status', ['pending', 'in_progress'])
                ->where(fn ($query) => $query->whereNull('assigned_user_id')->orWhere('assigned_user_id', request()->user()->id))
                ->where(fn ($query) => $query->whereBetween('starts_at', [now()->startOfDay(), now()->addDays(7)->endOfDay()])->orWhereBetween('due_at', [now()->startOfDay(), now()->addDays(7)->endOfDay()]))
                ->orderByRaw('coalesce(starts_at, due_at)')
                ->limit(8)
                ->get()
                ->map(fn (CrmActivity $activity) => [
                    'id' => $activity->id,
                    'type' => $activity->type,
                    'title' => $activity->title,
                    'priority' => $activity->priority,
                    'when' => ($activity->starts_at ?? $activity->due_at)?->toIso8601String(),
                    'installation' => $activity->installation?->project_reference ?? $activity->installation?->site_name,
                ]) : [],
        ]);
    }

    /** @return array<string, mixed>|null */
    private function monitoringSummary(Installation $installation): ?array
    {
        $provider = filled($installation->huawei_station_code)
            ? 'huawei'
            : (filled($installation->deye_station_id) ? 'deye' : null);
        if (! $provider) {
            return null;
        }

        $status = $provider === 'huawei' ? $installation->huawei_status : $installation->deye_status;
        $providerMessage = $provider === 'huawei' ? $installation->huawei_status_message : $installation->deye_status_message;

        return [
            'provider' => $provider,
            'station_name' => $provider === 'huawei' ? $installation->huawei_station_name : $installation->deye_station_name,
            'status' => $status,
            'current_power_kw' => $provider === 'huawei' ? $installation->huawei_current_power_kw : $installation->deye_current_power_kw,
            'today_energy_kwh' => $provider === 'huawei' ? $installation->huawei_today_energy_kwh : $installation->deye_today_energy_kwh,
            'message' => match ($status) {
                'healthy' => __('Operating normally.'),
                'disconnected' => __('Monitoring is disconnected.'),
                'fault' => $providerMessage ?: __('Monitoring reports an active fault.'),
                default => __('Waiting for monitoring data.'),
            },
            'synced_at' => ($provider === 'huawei' ? $installation->huawei_synced_at : $installation->deye_synced_at)?->toIso8601String(),
        ];
    }
}
