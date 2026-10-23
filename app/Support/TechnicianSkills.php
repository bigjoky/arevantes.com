<?php

namespace App\Support;

use App\Models\FieldServiceProfile;
use App\Models\WorkOrder;
use App\Models\WorkOrderTemplate;
use Illuminate\Support\Str;

final class TechnicianSkills
{
    private const LABELS = [
        'solar' => 'Instalaciones solares',
        'electrical' => 'Electricidad',
        'ev_charger' => 'Puntos de recarga de vehículos eléctricos',
        'hvac' => 'Climatización',
        'refrigeration' => 'Refrigeración',
        'commissioning' => 'Puesta en marcha',
        'customer_training' => 'Formación al cliente',
        'electrical_survey' => 'Visitas técnicas de electricidad',
        'electrical_installation' => 'Instalaciones eléctricas',
        'electrical_safety' => 'Seguridad eléctrica',
        'electrical_testing' => 'Pruebas y mediciones eléctricas',
        'security_systems' => 'Sistemas de seguridad',
        'alarm_installation' => 'Instalación de alarmas',
        'cctv' => 'Videovigilancia',
        'security_survey' => 'Visitas técnicas de seguridad',
    ];

    public static function label(string $key): string
    {
        return self::LABELS[$key] ?? Str::headline($key);
    }

    public static function options(): array
    {
        $keys = collect(array_keys(self::LABELS));
        foreach ([[FieldServiceProfile::class, 'skills'], [WorkOrderTemplate::class, 'required_skills'], [WorkOrder::class, 'required_skills']] as [$model, $column]) {
            $keys = $keys->merge($model::whereNotNull($column)->pluck($column)->flatten());
        }

        return $keys->filter(fn ($key) => is_string($key) && $key !== '')->unique()->map(fn ($key) => ['value' => $key, 'label' => self::label($key)])->sortBy('label')->values()->all();
    }
}
