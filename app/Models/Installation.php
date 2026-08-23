<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Installation extends Model
{
    protected static function booted(): void
    {
        static::saved(function (Installation $installation): void {
            if (! $installation->person_id) {
                $installation->people()->wherePivot('is_primary', true)->get()->each(
                    fn (Person $person) => $installation->people()->updateExistingPivot($person->id, ['is_primary' => false, 'role' => 'co_owner']),
                );

                return;
            }

            $installation->people()->wherePivot('is_primary', true)->get()->each(
                fn (Person $person) => $installation->people()->updateExistingPivot($person->id, ['is_primary' => false, 'role' => 'co_owner']),
            );
            $installation->people()->syncWithoutDetaching([
                $installation->person_id => ['role' => 'owner', 'is_primary' => true],
            ]);
        });
    }

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'person_id',
        'commercial_user_id',
        'technician_user_id',
        'service_type',
        'deye_station_id',
        'deye_station_name',
        'deye_status',
        'deye_current_power_kw',
        'deye_today_energy_kwh',
        'deye_status_message',
        'deye_data',
        'deye_synced_at',
        'huawei_station_code', 'huawei_station_name', 'huawei_status', 'huawei_current_power_kw', 'huawei_today_energy_kwh', 'huawei_status_message', 'huawei_data', 'huawei_synced_at',
        'status',
        'financial_status',
        'financially_cleared_at',
        'project_reference',
        'site_name',
        'site_address_line_1',
        'site_street_number',
        'site_address_line_2',
        'site_city',
        'site_locality',
        'site_province_state',
        'site_postal_code',
        'site_country',
        'site_latitude',
        'site_longitude',
        'site_geocoding_provider',
        'site_geocoding_place_id',
        'site_geocoding_osm_type',
        'site_geocoding_osm_id',
        'site_geocoding_display_name',
        'site_geocoding_data',
        'site_geocoded_at',
        'survey_date',
        'scheduled_date',
        'completed_date',
        'estimated_budget',
        'system_power_kw',
        'panel_count',
        'inverter_brand',
        'battery_capacity_kwh',
        'charger_power_kw',
        'charger_connector_type',
        'hvac_system_type',
        'electrical_scope',
        'site_notes',
        'technical_notes',
        'extra_details',
    ];

    /**
     * Get the customer for this installation.
     */
    public function customer(): BelongsTo
    {
        return $this->belongsTo(Person::class, 'person_id')->withTrashed();
    }

    public function people(): BelongsToMany
    {
        return $this->belongsToMany(Person::class, 'installation_person')
            ->withPivot(['role', 'ownership_percentage', 'is_primary'])
            ->withTimestamps()
            ->orderByPivot('is_primary', 'desc')
            ->orderBy('people.first_name');
    }

    public function remoteStation(): HasOne
    {
        return $this->hasOne(RemoteStation::class);
    }

    public function monitoringReadings(): HasMany
    {
        return $this->hasMany(MonitoringReading::class);
    }

    public function activities(): HasMany
    {
        return $this->hasMany(CrmActivity::class);
    }

    public function opportunities(): HasMany
    {
        return $this->hasMany(Opportunity::class);
    }

    public function workOrders(): HasMany
    {
        return $this->hasMany(WorkOrder::class);
    }

    public function assets(): HasMany
    {
        return $this->hasMany(InstallationAsset::class);
    }

    public function maintenancePlans(): HasMany
    {
        return $this->hasMany(MaintenancePlan::class);
    }

    public function serviceTickets(): HasMany
    {
        return $this->hasMany(ServiceTicket::class);
    }

    public function materialRequirements(): HasMany
    {
        return $this->hasMany(InstallationMaterialRequirement::class);
    }

    public function purchaseDocuments(): HasMany
    {
        return $this->hasMany(PurchaseDocument::class);
    }

    public function quotes(): HasMany
    {
        return $this->hasMany(Quote::class);
    }

    public function monitoringAlerts(): HasMany
    {
        return $this->hasMany(MonitoringAlert::class);
    }

    public function aiGenerations(): HasMany
    {
        return $this->hasMany(AiGeneration::class);
    }

    public function commercial(): BelongsTo
    {
        return $this->belongsTo(User::class, 'commercial_user_id');
    }

    public function technician(): BelongsTo
    {
        return $this->belongsTo(User::class, 'technician_user_id');
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'survey_date' => 'date',
            'scheduled_date' => 'date',
            'completed_date' => 'date',
            'estimated_budget' => 'decimal:2',
            'site_latitude' => 'decimal:7',
            'site_longitude' => 'decimal:7',
            'site_geocoding_data' => 'array',
            'site_geocoded_at' => 'datetime',
            'deye_current_power_kw' => 'decimal:3',
            'deye_today_energy_kwh' => 'decimal:3',
            'deye_data' => 'array',
            'deye_synced_at' => 'datetime',
            'huawei_current_power_kw' => 'decimal:3', 'huawei_today_energy_kwh' => 'decimal:3', 'huawei_data' => 'array', 'huawei_synced_at' => 'datetime',
            'system_power_kw' => 'decimal:2',
            'battery_capacity_kwh' => 'decimal:2',
            'charger_power_kw' => 'decimal:2',
            'extra_details' => 'array',
            'financially_cleared_at' => 'datetime',
        ];
    }
}
