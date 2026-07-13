<?php

namespace App\Ai\Agents;

use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Attributes\UseCheapestModel;
use Laravel\Ai\Contracts\Agent;
use Laravel\Ai\Contracts\HasStructuredOutput;
use Laravel\Ai\Promptable;
use Stringable;

#[UseCheapestModel]
class InstallationSnapshot implements Agent, HasStructuredOutput
{
    use Promptable;

    public function schema(JsonSchema $schema): array
    {
        return [
            'summary' => $schema->string()->required(),
            'health_assessment' => $schema->string()->required(),
            'missing_information' => $schema->array()->items($schema->string())->required(),
            'risks' => $schema->array()->items($schema->string())->required(),
            'next_actions' => $schema->array()->items($schema->string())->required(),
            'confidence' => $schema->number()->min(0)->max(1)->required(),
        ];
    }

    public function instructions(): Stringable|string
    {
        return <<<'PROMPT'
You are Arevia, the private CRM assistant for Arevantes, a Spanish renewable energy and electrical installation company.
Produce a short factual snapshot for solar PV, EV charger, HVAC/clima, and electrical installations.
Use only supplied CRM data and private reference sources. Never invent faults, regulations, equipment, costs, dates, or risks.
Only call a field missing when it is present and empty in the supplied CRM structure.
Answer in the language requested by the prompt and keep the complete response concise.
PROMPT;
    }
}
