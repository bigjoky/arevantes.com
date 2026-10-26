<?php

namespace App\Services\AfterSales;

use App\Models\FinancialCategory;
use App\Models\FinancialForecastItem;
use App\Models\MaintenancePlan;
use App\Models\Opportunity;
use App\Models\User;
use App\Services\Crm\OpportunitySalesCycle;
use App\Services\Documents\OpportunityNumber;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class MaintenancePlanLifecycle
{
    public function __construct(private OpportunitySalesCycle $salesCycle) {}

    /** @param array<int, int> $assetIds */
    public function create(array $attributes, array $assetIds, ?User $actor): MaintenancePlan
    {
        return DB::transaction(function () use ($attributes, $assetIds, $actor): MaintenancePlan {
            $plan = MaintenancePlan::create([
                'service_level' => 'standard',
                'coverage_type' => 'preventive',
                'renewal_notice_days' => 30,
                ...$attributes,
                'version' => 1,
                'last_versioned_by' => $actor?->id,
                'is_active' => true,
                'generate_work_order' => $attributes['generate_work_order'] ?? true,
            ]);
            $plan->coveredAssets()->sync($assetIds);
            $this->recordVersion($plan, __('Initial service plan created.'), $actor);
            $this->syncForecast($plan, $actor);

            return $plan;
        });
    }

    /** @param array<int, int>|null $assetIds */
    public function revise(MaintenancePlan $plan, array $attributes, ?array $assetIds, string $changeNote, User $actor): MaintenancePlan
    {
        return DB::transaction(function () use ($plan, $attributes, $assetIds, $changeNote, $actor): MaintenancePlan {
            $locked = MaintenancePlan::query()->lockForUpdate()->findOrFail($plan->id);
            $locked->update([
                ...$attributes,
                'version' => $locked->version + 1,
                'last_versioned_by' => $actor->id,
            ]);
            if ($assetIds !== null) {
                $locked->coveredAssets()->sync($assetIds);
            }
            $this->recordVersion($locked, $changeNote, $actor);
            $this->syncForecast($locked, $actor);

            return $locked->refresh();
        });
    }

    public function prepareRenewal(MaintenancePlan $plan, User $actor): Opportunity
    {
        return DB::transaction(function () use ($plan, $actor): Opportunity {
            $locked = MaintenancePlan::query()->with(['installation', 'originOpportunity'])->lockForUpdate()->findOrFail($plan->id);
            if ($locked->renewal_opportunity_id) {
                return Opportunity::findOrFail($locked->renewal_opportunity_id);
            }
            if (! $locked->contract_ends_on) {
                throw ValidationException::withMessages(['renewal' => __('Set a contract end date before preparing its renewal.')]);
            }
            if (! $locked->installation->person_id) {
                throw ValidationException::withMessages(['renewal' => __('The installation must have a customer before preparing its renewal.')]);
            }

            $origin = $locked->originOpportunity && $locked->originOpportunity->stage === 'won'
                ? $locked->originOpportunity
                : $locked->installation->opportunities()->where('stage', 'won')->latest()->first();
            $frequency = max(1, (int) ($locked->billing_frequency_months ?: 12));
            $annualValue = (float) ($locked->billing_amount ?: 0) * (12 / $frequency);
            $ownerId = $locked->installation->commercial_user_id ?: $origin?->owner_user_id ?: $actor->id;
            $reference = $locked->installation->project_reference ?: $locked->installation->site_name ?: '#'.$locked->installation_id;
            $opportunity = Opportunity::create([
                'person_id' => $locked->installation->person_id,
                'installation_id' => $locked->installation_id,
                'owner_user_id' => $ownerId,
                'created_by' => $actor->id,
                'updated_by' => $actor->id,
                'parent_opportunity_id' => $origin?->id,
                'code' => 'PENDING',
                'title' => __('Renewal :plan · :installation', ['plan' => $locked->name, 'installation' => $reference]),
                'commercial_kind' => 'renewal',
                'renewal_due_on' => $locked->contract_ends_on,
                'stage' => 'new',
                'probability' => 10,
                'estimated_value' => round($annualValue, 2),
                'expected_close_on' => $locked->contract_ends_on,
                'source' => 'recurring_customer',
                'notes' => __('Prepared from service plan #:id version :version. Human review is required before contacting the customer.', ['id' => $locked->id, 'version' => $locked->version]),
            ]);
            $opportunity->update(['code' => app(OpportunityNumber::class)->next($opportunity->id)]);
            $this->salesCycle->ensureNextAction($opportunity, $actor, [
                'type' => 'task',
                'title' => __('Review renewal scope and prepare customer contact'),
                'priority' => 'normal',
                'due_at' => today()->addDay()->setTime(9, 0),
            ]);
            $this->salesCycle->refreshScore($opportunity);
            $locked->updateQuietly(['renewal_opportunity_id' => $opportunity->id]);

            return $opportunity;
        });
    }

    private function recordVersion(MaintenancePlan $plan, string $changeNote, ?User $actor): void
    {
        $plan->load(['coveredAssets:id', 'preventiveMaterials:id,maintenance_plan_id,product_id,quantity_per_visit,expected_usage_percent,minimum_stock,notes']);
        $plan->versions()->create([
            'version' => $plan->version,
            'snapshot' => [
                ...$plan->only([
                    'installation_id', 'work_order_template_id', 'assigned_user_id', 'name', 'version', 'service_level', 'coverage_type',
                    'included_visits', 'response_minutes', 'resolution_minutes', 'interval_months', 'lead_days', 'starts_on', 'contract_ends_on',
                    'renewal_notice_days', 'billing_frequency_months', 'billing_amount', 'next_billing_on', 'next_due_on', 'is_active',
                    'generate_work_order', 'scope',
                ]),
                'covered_asset_ids' => $plan->coveredAssets->pluck('id')->all(),
                'preventive_materials' => $plan->preventiveMaterials->map->only(['product_id', 'quantity_per_visit', 'expected_usage_percent', 'minimum_stock', 'notes'])->values()->all(),
            ],
            'change_note' => trim($changeNote),
            'created_by' => $actor?->id,
        ]);
    }

    private function syncForecast(MaintenancePlan $plan, ?User $actor): void
    {
        FinancialForecastItem::query()
            ->where('maintenance_plan_id', $plan->id)
            ->where('maintenance_plan_version', '!=', $plan->version)
            ->where('status', 'planned')
            ->update(['status' => 'cancelled']);
        $existing = FinancialForecastItem::where('maintenance_plan_id', $plan->id)
            ->where('maintenance_plan_version', $plan->version)
            ->whereDate('due_on', $plan->next_billing_on)
            ->first();
        if (! $plan->is_active || ! $plan->billing_amount || ! $plan->next_billing_on) {
            if ($existing && $existing->status === 'planned') {
                $existing->update(['status' => 'cancelled']);
            }

            return;
        }

        $category = FinancialCategory::where('code', 'customer_collections')->firstOrFail();
        $recurrence = match ((int) $plan->billing_frequency_months) {
            1 => 'monthly',
            3 => 'quarterly',
            12 => 'annual',
            default => null,
        };
        $attributes = [
            'direction' => 'in',
            'category' => $category->code,
            'financial_category_id' => $category->id,
            'description' => __('Recurring service plan: :name', ['name' => $plan->name]),
            'amount' => $plan->billing_amount,
            'baseline_amount' => $plan->billing_amount,
            'due_on' => $plan->next_billing_on,
            'baseline_due_on' => $plan->next_billing_on,
            'probability' => 100,
            'recurrence' => $recurrence,
            'status' => 'planned',
            'installation_id' => $plan->installation_id,
            'maintenance_plan_id' => $plan->id,
            'maintenance_plan_version' => $plan->version,
            'created_by' => $existing?->created_by ?: $actor?->id,
        ];
        $existing ? $existing->update($attributes) : FinancialForecastItem::create($attributes);
    }
}
