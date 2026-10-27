<?php

namespace App\Services\Ai;

use App\Models\BankTransaction;
use App\Models\Installation;
use App\Models\MaintenancePlan;
use App\Models\Opportunity;
use App\Models\Person;
use App\Models\PurchaseDocument;
use App\Models\ReplenishmentTarget;
use App\Models\SalesDocument;
use App\Models\ServiceCycleCommitment;
use App\Models\User;
use App\Services\Operations\ProjectMarginPortfolio;
use App\Services\Supply\PreventiveDemandForecast;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;

class RelevantBusinessContext
{
    public function __construct(private ProjectMarginPortfolio $margins, private PreventiveDemandForecast $preventiveDemand) {}

    public function search(User $user, string $query, ?string $pageUrl = null): string
    {
        $tokens = collect(preg_split('/[^\pL\pN@._-]+/u', Str::lower($query)))->filter(fn ($token) => mb_strlen($token) >= 3)->unique()->take(8)->values();
        $sections = collect();
        $recurringRequested = preg_match('/after-sales|maintenance|service plan|renewal|sla|supply|stock|spare|posventa|mantenimiento|renovaci|repuesto|inventario/u', mb_strtolower($query.' '.($pageUrl ?? ''))) === 1;

        if (Gate::forUser($user)->allows('view-installations')) {
            $installationId = preg_match('#/installations/(\d+)#', (string) $pageUrl, $match) ? (int) $match[1] : null;
            $installations = Installation::with('customer:id,first_name,last_name,company_name')->when($installationId, fn ($builder) => $builder->whereKey($installationId), fn ($builder) => $this->whereTokens($builder, $tokens, ['project_reference', 'site_name', 'site_address_line_1', 'site_city', 'site_postal_code']))->limit(5)->get();
            if ($installations->isNotEmpty()) {
                $sections->push("Relevant installations:\n".$installations->map(fn ($item) => sprintf('- #%d %s · %s · %s · %.2f kW · customer %s · monitoring %s · today %.3f kWh', $item->id, $item->project_reference ?: $item->site_name ?: 'unnamed', $item->service_type, $item->status, (float) $item->system_power_kw, $item->customer?->company_name ?: trim(($item->customer?->first_name ?? '').' '.($item->customer?->last_name ?? '')) ?: 'unassigned', $item->huawei_status ?: $item->deye_status ?: 'not linked', (float) ($item->huawei_today_energy_kwh ?: $item->deye_today_energy_kwh)))->implode("\n"));
                if (Gate::forUser($user)->allows('view-finance')) {
                    $financials = $this->margins->analyze($installations)->keyBy('installation_id');
                    $sections->push("Authorized project margin context:\n".$installations->map(function ($item) use ($financials): string {
                        $margin = $financials->get($item->id);

                        return sprintf(
                            '- #%d %s · planned %.2f EUR · committed %.2f EUR · actual %.2f EUR · outstanding cash %.2f EUR · data quality %d%%',
                            $item->id,
                            $item->project_reference ?: $item->site_name ?: 'unnamed',
                            $margin['planned_margin'],
                            $margin['committed_margin'],
                            $margin['actual_margin'],
                            $margin['outstanding_cash'],
                            $margin['quality']['score'],
                        );
                    })->implode("\n"));
                }
            }
            if ($recurringRequested) {
                $plans = MaintenancePlan::query()
                    ->with('installation:id,project_reference,site_name,service_type')
                    ->where('is_active', true)
                    ->when($user->hasRole('technician'), fn ($builder) => $builder->where('assigned_user_id', $user->id))
                    ->orderBy('next_due_on')
                    ->limit(8)
                    ->get();
                if ($plans->isNotEmpty()) {
                    $sections->push("[CRM-SVC-1] Authorized recurring service plans:\n".$plans->map(fn (MaintenancePlan $plan) => sprintf(
                        '- plan #%d v%d %s · installation #%d %s · line %s · level %s · coverage %s · next %s · contract end %s · renewal %s · assigned %s',
                        $plan->id,
                        $plan->version,
                        $plan->name,
                        $plan->installation_id,
                        $plan->installation?->project_reference ?: $plan->installation?->site_name ?: 'unnamed',
                        $plan->installation?->service_type ?: 'unknown',
                        $plan->service_level,
                        $plan->coverage_type,
                        $plan->next_due_on?->format('Y-m-d') ?: 'not set',
                        $plan->contract_ends_on?->format('Y-m-d') ?: 'open-ended',
                        $plan->renewal_opportunity_id ? 'prepared' : 'not prepared',
                        $plan->assigned_user_id ? 'assigned' : 'unassigned',
                    ))->implode("\n"));
                }
                $commitments = ServiceCycleCommitment::query()
                    ->with('maintenancePlan:id,name,assigned_user_id')
                    ->where('status', 'approved')
                    ->when($user->hasRole('technician'), fn ($builder) => $builder->whereHas('maintenancePlan', fn ($planQuery) => $planQuery->where('assigned_user_id', $user->id)))
                    ->orderBy('period_starts_on')->limit(8)->get();
                if ($commitments->isNotEmpty()) {
                    $sections->push("[OPS-SVC-2] Human-approved recurring service capacity snapshots:\n".$commitments->map(fn (ServiceCycleCommitment $commitment) => sprintf(
                        '- plan #%d v%d %s · cycle %s to %s · %d minutes · approval recorded; no work order or customer date created by this approval',
                        $commitment->maintenance_plan_id,
                        $commitment->maintenance_plan_version,
                        $commitment->maintenancePlan?->name ?: 'service plan',
                        $commitment->period_starts_on->format('Y-m-d'),
                        $commitment->period_ends_on->format('Y-m-d'),
                        $commitment->planned_minutes,
                    ))->implode("\n"));
                }
            }
        }

        if (Gate::forUser($user)->allows('manage-customers')) {
            $customers = Person::where('category', 'customer')->tap(fn ($builder) => $this->whereTokens($builder, $tokens, ['first_name', 'last_name', 'company_name', 'email', 'phone', 'tax_id']))->limit(5)->get();
            if ($customers->isNotEmpty()) {
                $sections->push("Relevant customers:\n".$customers->map(fn ($person) => sprintf('- #%d %s · %s · %s · %s · %d installations', $person->id, $person->company_name ?: trim($person->first_name.' '.$person->last_name), $person->email ?: 'no email', $person->phone ?: 'no phone', collect([$person->city, $person->province_state])->filter()->implode(', ') ?: 'no location', $person->installations()->count()))->implode("\n"));
            }

            $opportunities = Opportunity::with(['customer:id,first_name,last_name,company_name', 'owner:id,name'])->tap(fn ($builder) => $this->whereTokens($builder, $tokens, ['code', 'title', 'stage', 'source', 'notes']))->latest()->limit(5)->get();
            if ($opportunities->isNotEmpty()) {
                $sections->push("Relevant sales opportunities:\n".$opportunities->map(fn ($item) => sprintf('- %s %s · %s · %.2f EUR · %d%% · close %s · customer %s · owner %s', $item->code, $item->title, $item->stage, (float) $item->estimated_value, $item->probability, $item->expected_close_on?->format('Y-m-d') ?: 'not set', $item->customer?->company_name ?: trim(($item->customer?->first_name ?? '').' '.($item->customer?->last_name ?? '')) ?: 'unassigned', $item->owner?->name ?: 'unassigned'))->implode("\n"));
            }
        }

        if (Gate::forUser($user)->allows('manage-catalog')) {
            if ($recurringRequested) {
                $forecast = $this->preventiveDemand->forecast(today(), 180);
                $riskRows = collect($forecast['rows'])->where('forecast_shortage', '>', 0)->take(8);
                $sections->push('[SUP-SVC-1] Preventive demand forecast (non-committing): '.sprintf(
                    '%d active plans, %d expected visits, %d products at risk. Forecast only; it has not reserved stock or created purchase documents.',
                    $forecast['summary']['plans'],
                    $forecast['summary']['visits'],
                    $forecast['summary']['products_at_risk'],
                ).($riskRows->isNotEmpty() ? "\n".$riskRows->map(fn (array $row) => sprintf(
                    '- product #%d %s · expected %.3f · free %.3f · inbound %.3f · forecast shortage %.3f · sources %d plans',
                    $row['product_id'],
                    $row['product'],
                    $row['expected_quantity'],
                    $row['free_stock'],
                    $row['inbound_before_horizon'],
                    $row['forecast_shortage'],
                    count($row['plans']),
                ))->implode("\n") : ''));
                $targets = ReplenishmentTarget::query()->with('product:id,name')->where('status', 'approved')->whereDate('period_ends_on', '>=', today())->orderBy('period_ends_on')->limit(8)->get();
                if ($targets->isNotEmpty()) {
                    $sections->push("[SUP-SVC-2] Human-approved replenishment objectives (still non-committing):\n".$targets->map(fn (ReplenishmentTarget $target) => sprintf(
                        '- product #%d %s · target %.3f through %s · forecast shortage snapshot %.3f · no reservation or purchase document exists from this objective',
                        $target->product_id,
                        $target->product?->name ?: 'product',
                        $target->target_quantity,
                        $target->period_ends_on->format('Y-m-d'),
                        $target->forecast_shortage,
                    ))->implode("\n"));
                }
                $sections->push('[GOV-SVC-1] Governance: any renewal contact, stock reservation, purchasing document, schedule commitment or customer communication must remain a draft until an authorized person reviews and confirms it.');
            }
            $sales = SalesDocument::with('customer:id,first_name,last_name,company_name')->tap(fn ($builder) => $this->whereTokens($builder, $tokens, ['number', 'status', 'notes']))->latest('issue_date')->limit(5)->get();
            if ($sales->isNotEmpty()) {
                $sections->push("Relevant sales documents:\n".$sales->map(fn ($doc) => sprintf('- %s %s · %s · %.2f EUR · paid %.2f EUR · due %s · customer %s', $doc->type, $doc->number, $doc->status, (float) $doc->amount, (float) $doc->paid_amount, $doc->due_date?->format('Y-m-d') ?: 'not set', $doc->customer?->company_name ?: trim(($doc->customer?->first_name ?? '').' '.($doc->customer?->last_name ?? '')) ?: 'unassigned'))->implode("\n"));
            }
            $purchases = PurchaseDocument::with(['supplier:id,legal_name,trade_name', 'attachment:id,purchase_document_id,extraction_status'])->tap(fn ($builder) => $this->whereTokens($builder, $tokens, ['number', 'supplier_number', 'status', 'notes']))->latest('issue_date')->limit(5)->get();
            if ($purchases->isNotEmpty()) {
                $sections->push("Relevant purchase documents:\n".$purchases->map(fn ($doc) => sprintf('- %s %s · %s · %.2f EUR · paid %.2f EUR · supplier %s · original document %s', $doc->type, $doc->number, $doc->status, (float) $doc->amount, (float) $doc->paid_amount, $doc->supplier?->trade_name ?: $doc->supplier?->legal_name ?: 'unassigned', $doc->attachment?->extraction_status ?: 'not attached'))->implode("\n"));
            }
            $movements = BankTransaction::with('account:id,name')->tap(fn ($builder) => $this->whereTokens($builder, $tokens, ['description', 'reference', 'status']))->latest('booked_on')->limit(5)->get();
            if ($movements->isNotEmpty()) {
                $sections->push("Relevant bank movements:\n".$movements->map(fn ($row) => sprintf('- %s · %s · %.2f %s · %s · %s', $row->booked_on->format('Y-m-d'), Str::limit($row->description, 100), (float) $row->amount, $row->currency, $row->status, $row->account?->name))->implode("\n"));
            }
        }

        return $sections->implode("\n\n") ?: 'No specific CRM records matched this request.';
    }

    private function whereTokens(Builder $builder, $tokens, array $columns): void
    {
        if ($tokens->isEmpty()) {
            $builder->whereRaw('1 = 0');

            return;
        }
        $builder->where(function ($query) use ($tokens, $columns) {
            foreach ($tokens as $token) {
                foreach ($columns as $column) {
                    $query->orWhereLike($column, '%'.$token.'%');
                }
            }
        });
    }
}
