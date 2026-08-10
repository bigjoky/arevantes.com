<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CrmSetting extends Model
{
    public const WEIGHT_FIELDS = [
        'customer_contactable_weight',
        'source_identified_weight',
        'owner_assigned_weight',
        'expected_close_set_weight',
        'next_action_scheduled_weight',
        'estimated_value_set_weight',
        'advanced_stage_weight',
    ];

    protected $fillable = [
        'stale_after_days',
        ...self::WEIGHT_FIELDS,
    ];

    protected function casts(): array
    {
        return collect($this->fillable)->mapWithKeys(fn (string $field) => [$field => 'integer'])->all();
    }

    public static function current(): self
    {
        return static::query()->firstOrCreate(['id' => 1]);
    }

    /** @return array<string, int> */
    public function scoringWeights(): array
    {
        return collect(self::WEIGHT_FIELDS)
            ->mapWithKeys(fn (string $field) => [str($field)->beforeLast('_weight')->toString() => (int) $this->{$field}])
            ->all();
    }
}
