<?php

namespace App\Support;

final class BusinessLineCatalog
{
    public const INSTALLATION_TYPES = [
        'solar',
        'ev_charger',
        'hvac',
        'electrical',
        'security',
    ];

    public const SERVICE_INTERESTS = [
        'solar_photovoltaic',
        'battery_storage',
        'ev_charging',
        'hvac_climate',
        'electrical_installation',
        'security_systems',
        'maintenance',
        'energy_efficiency',
        'other',
    ];

    public const WEBSITE_SERVICES = [
        'solar' => 'Instalación fotovoltaica',
        'storage_charging' => 'Recarga y almacenamiento',
        'hvac' => 'Climatización',
        'electrical' => 'Instalaciones eléctricas',
        'security' => 'Alarmas y sistemas de seguridad',
        'maintenance' => 'Mantenimiento',
    ];

    public static function installationLabel(string $type): string
    {
        return match ($type) {
            'solar', 'solar_pv' => 'Instalación fotovoltaica',
            'ev_charger' => 'Punto de recarga EV',
            'hvac' => 'Climatización',
            'electrical' => 'Instalación eléctrica',
            'security' => 'Alarma y sistema de seguridad',
            default => str($type)->replace('_', ' ')->headline()->toString(),
        };
    }

    public static function normalizeInstallationType(string $type): string
    {
        return $type === 'solar_pv' ? 'solar' : $type;
    }
}
