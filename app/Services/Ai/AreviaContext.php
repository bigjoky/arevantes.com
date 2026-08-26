<?php

namespace App\Services\Ai;

class AreviaContext
{
    public static function module(?string $pageUrl, string $query = ''): string
    {
        $value = mb_strtolower(($pageUrl ?? '').' '.$query);

        return match (true) {
            preg_match('/finance|account|bank|invoice|treasury|tax|factur|contab|tesorer/u', $value) === 1 => 'finance',
            preg_match('/supply|purchase|inventory|stock|repuesto|suministro|compra|inventario/u', $value) === 1 => 'operations',
            preg_match('/installation|monitoring|solar|huawei|deye|instalaci|electr|alarm|security|seguridad|cctv|videovigilancia/u', $value) === 1 => 'installations',
            preg_match('/operation|work-order|after-sales|field-service|maintenance|operaci|orden de trabajo|mantenimiento|posventa|sla/u', $value) === 1 => 'operations',
            preg_match('/customer|opportunit|quote|crm|client|oferta/u', $value) === 1 => 'crm',
            default => 'general',
        };
    }
}
