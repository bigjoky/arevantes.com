<?php

namespace App\Ai\Evaluation;

use App\Support\BusinessLineCatalog;

class InstallationBriefingCases
{
    public static function all(): array
    {
        $services = BusinessLineCatalog::INSTALLATION_TYPES;
        $statuses = ['lead', 'survey_booked', 'quoted', 'scheduled', 'completed', 'on_hold', 'cancelled', 'in_progress'];

        return collect($services)->crossJoin($statuses)->map(fn (array $case, int $index) => [
            'name' => 'case-'.str_pad((string) ($index + 1), 2, '0', STR_PAD_LEFT),
            'prompt' => "Genera el briefing en espanol. Datos confirmados: service_type={$case[0]}, status={$case[1]}. No hay mas datos ni fuentes.",
        ])->all();
    }
}
