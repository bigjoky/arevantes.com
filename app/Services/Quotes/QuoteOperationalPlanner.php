<?php

namespace App\Services\Quotes;

use App\Models\CrmActivity;
use App\Models\Installation;
use App\Models\MaintenancePlan;
use App\Models\Quote;
use App\Models\User;
use App\Services\AfterSales\MaintenancePlanLifecycle;

class QuoteOperationalPlanner
{
    public function __construct(private MaintenancePlanLifecycle $plans) {}

    public function sync(Quote $quote, Installation $installation, ?int $actorId = null): void
    {
        $quote->loadMissing('lines.components');

        foreach ($quote->lines->where('is_selected', true)->flatMap->components as $component) {
            $marker = __('Generated from quote :number, component :id.', ['number' => $quote->number, 'id' => $component->id]);

            if (in_array($component->fulfillment_kind, ['activity', 'task', 'legalization'], true)) {
                CrmActivity::firstOrCreate(
                    [
                        'installation_id' => $installation->id,
                        'person_id' => $quote->person_id,
                        'type' => 'task',
                        'title' => $component->description,
                        'notes' => $marker,
                    ],
                    [
                        'opportunity_id' => $quote->opportunity_id,
                        'assigned_user_id' => $installation->technician_user_id,
                        'created_by' => $actorId,
                        'required_for_closure' => $component->fulfillment_kind === 'legalization',
                        'priority' => 'normal',
                        'status' => 'pending',
                        'due_at' => now()->addDays(14),
                    ],
                );
            }

            if (in_array($component->fulfillment_kind, ['recurring', 'maintenance'], true)) {
                $months = $component->interval_months ?: 12;
                if (! MaintenancePlan::where('installation_id', $installation->id)->where('name', $component->description)->exists()) {
                    $this->plans->create([
                        'installation_id' => $installation->id,
                        'name' => $component->description,
                        'assigned_user_id' => $installation->technician_user_id,
                        'interval_months' => $months,
                        'lead_days' => 14,
                        'starts_on' => today()->addMonths($months),
                        'next_due_on' => today()->addMonths($months),
                        'is_active' => true,
                        'generate_work_order' => true,
                        'scope' => $marker,
                        'origin_opportunity_id' => $quote->opportunity_id,
                    ], [], $actorId ? User::find($actorId) : null);
                }
            }
        }
    }
}
