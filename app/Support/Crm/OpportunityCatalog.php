<?php

namespace App\Support\Crm;

final class OpportunityCatalog
{
    /** Existing CRM stages; no production stage migration is performed by the dashboard. */
    public const STAGES = ['new' => 'Nuevo', 'qualified' => 'Cualificado', 'survey' => 'Visita técnica', 'proposal' => 'Propuesta', 'negotiation' => 'Negociación', 'won' => 'Ganada', 'lost' => 'Perdida'];

    /** @var array<string, string> */
    public const COMMERCIAL_KINDS = [
        'new_business' => 'Nueva venta',
        'renewal' => 'Renovación',
        'recurring' => 'Venta recurrente',
        'upsell' => 'Venta adicional',
    ];

    /** @var array<string, string> */
    public const RECURRENCE_INTERVALS = [
        'monthly' => 'Mensual',
        'quarterly' => 'Trimestral',
        'semiannual' => 'Semestral',
        'annual' => 'Anual',
    ];

    /** @var array<string, string> */
    public const SOURCES = [
        'website' => 'Web',
        'referral' => 'Referido',
        'campaign' => 'Campaña',
        'recurring_customer' => 'Cliente recurrente',
        'outbound' => 'Prospección comercial',
        'partner' => 'Colaborador',
        'other' => 'Otro',
    ];

    /** @var array<string, string> */
    public const LOST_REASONS = [
        'price' => 'Precio',
        'competitor' => 'Competencia',
        'timing' => 'Plazo o momento',
        'financing' => 'Financiación',
        'no_response' => 'Sin respuesta',
        'scope' => 'Alcance no adecuado',
        'duplicate' => 'Duplicada',
        'other' => 'Otro',
    ];
}
