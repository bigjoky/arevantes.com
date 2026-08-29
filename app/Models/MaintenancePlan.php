<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class MaintenancePlan extends Model
{
    protected $fillable = ['installation_id', 'work_order_template_id', 'assigned_user_id', 'name', 'version', 'service_level', 'coverage_type', 'included_visits', 'response_minutes', 'resolution_minutes', 'interval_months', 'lead_days', 'starts_on', 'contract_ends_on', 'renewal_notice_days', 'billing_frequency_months', 'billing_amount', 'next_billing_on', 'origin_opportunity_id', 'renewal_opportunity_id', 'last_versioned_by', 'next_due_on', 'last_completed_on', 'is_active', 'generate_work_order', 'last_generated_at', 'scope'];

    protected function casts(): array
    {
        return ['version' => 'integer', 'included_visits' => 'integer', 'response_minutes' => 'integer', 'resolution_minutes' => 'integer', 'starts_on' => 'date', 'contract_ends_on' => 'date', 'next_billing_on' => 'date', 'billing_amount' => 'decimal:2', 'next_due_on' => 'date', 'last_completed_on' => 'date', 'is_active' => 'boolean', 'generate_work_order' => 'boolean', 'last_generated_at' => 'datetime'];
    }

    public function installation(): BelongsTo
    {
        return $this->belongsTo(Installation::class);
    }

    public function tickets(): HasMany
    {
        return $this->hasMany(ServiceTicket::class);
    }

    public function template(): BelongsTo
    {
        return $this->belongsTo(WorkOrderTemplate::class, 'work_order_template_id');
    }

    public function assignedUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_user_id');
    }

    public function versions(): HasMany
    {
        return $this->hasMany(MaintenancePlanVersion::class)->latest('version');
    }

    public function coveredAssets(): BelongsToMany
    {
        return $this->belongsToMany(InstallationAsset::class, 'maintenance_plan_assets')->withTimestamps();
    }

    public function preventiveMaterials(): HasMany
    {
        return $this->hasMany(MaintenancePlanMaterial::class);
    }

    public function cycleCommitments(): HasMany
    {
        return $this->hasMany(ServiceCycleCommitment::class);
    }

    public function originOpportunity(): BelongsTo
    {
        return $this->belongsTo(Opportunity::class, 'origin_opportunity_id');
    }

    public function renewalOpportunity(): BelongsTo
    {
        return $this->belongsTo(Opportunity::class, 'renewal_opportunity_id');
    }

    public function financialForecast(): HasOne
    {
        return $this->hasOne(FinancialForecastItem::class);
    }

    public function correctivePlans(): HasMany
    {
        return $this->hasMany(ServiceCorrectivePlan::class);
    }
}
