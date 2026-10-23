<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FinancialForecastItem extends Model
{
    protected $fillable = ['operation_key', 'planning_scenario_request_id', 'direction', 'category', 'financial_category_id', 'description', 'amount', 'baseline_amount', 'due_on', 'baseline_due_on', 'probability', 'recurrence', 'status', 'actual_amount', 'settled_on', 'settlement_notes', 'installation_id', 'maintenance_plan_id', 'maintenance_plan_version', 'created_by', 'settled_by'];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'baseline_amount' => 'decimal:2',
            'due_on' => 'date',
            'baseline_due_on' => 'date',
            'probability' => 'integer',
            'maintenance_plan_version' => 'integer',
            'actual_amount' => 'decimal:2',
            'settled_on' => 'date',
        ];
    }

    public function installation(): BelongsTo
    {
        return $this->belongsTo(Installation::class);
    }

    public function financialCategory(): BelongsTo
    {
        return $this->belongsTo(FinancialCategory::class);
    }

    public function maintenancePlan(): BelongsTo
    {
        return $this->belongsTo(MaintenancePlan::class);
    }

    public function settler(): BelongsTo
    {
        return $this->belongsTo(User::class, 'settled_by');
    }
}
