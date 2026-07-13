<?php

namespace App\Ai\Agents;

use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Attributes\UseCheapestModel;
use Laravel\Ai\Contracts\Agent;
use Laravel\Ai\Contracts\HasStructuredOutput;
use Laravel\Ai\Promptable;
use Stringable;

#[UseCheapestModel]
class InstallationAdvisor implements Agent, HasStructuredOutput
{
    use Promptable;

    public function schema(JsonSchema $schema): array
    {
        return [
            'summary' => $schema->string()->required(),
            'health_assessment' => $schema->string()->required(),
            'commercial_angle' => $schema->string()->required(),
            'missing_information' => $schema->array()->items($schema->string())->required(),
            'risks' => $schema->array()->items($schema->string())->required(),
            'next_actions' => $schema->array()->items($schema->string())->required(),
            'action_items' => $schema->array()->items($schema->object([
                'title' => $schema->string()->required(),
                'type' => $schema->string()->enum(['task', 'visit', 'reminder'])->required(),
                'priority' => $schema->string()->enum(['low', 'normal', 'high'])->required(),
                'reason' => $schema->string()->required(),
                'due_in_days' => $schema->integer()->min(0)->max(90)->required(),
            ]))->required(),
            'confidence' => $schema->number()->min(0)->max(1)->required(),
        ];
    }

    /**
     * Get the instructions that the agent should follow.
     */
    public function instructions(): Stringable|string
    {
        return <<<'PROMPT'
You are Arevia, the private operations assistant for Arevantes, a Spanish renewable energy and electrical installation company.
Write concise, practical briefings for solar PV, EV charger, HVAC/clima, and electrical installation records.
Focus on: current situation, missing information, technical/commercial risks, and next best actions.
When asked for action_items, generate CRM-ready activities that a commercial or technician can actually execute.
Treat only the CRM payload and private reference documentation as facts.
Never infer or introduce sensors, firmware, inspections, maintenance, regulations, equipment, faults, dates, costs, or risks that are not explicitly present in those sources.
Only identify a field as missing when it exists in the supplied CRM structure and its value is null or empty.
Recommendations must follow directly from confirmed data or explicitly missing CRM fields. If there is not enough evidence for a risk, say that no specific risk can be determined from the available data.
Follow the output length and format requested by the current prompt, even when it requests less detail than a full briefing.
Answer in the same language used by the user's CRM data when possible.
PROMPT;
    }
}
