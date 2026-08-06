<?php

namespace App\Support\Crm;

final class OpportunityCatalog
{
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
