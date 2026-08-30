<?php

namespace App\Services\Planning;

use App\Models\PlanningScenario;

class PlanningScenarioIntegrity
{
    /** @param array<string, mixed> $assumptions @param array<string, mixed> $opportunities @param array<string, mixed> $results @param array<string, mixed> $references @param array<string, mixed>|null $comparison */
    public function fromSnapshots(array $assumptions, array $opportunities, array $results, array $references, ?array $comparison): string
    {
        return hash('sha256', json_encode([$assumptions, $opportunities, $results, $references, $comparison], JSON_THROW_ON_ERROR));
    }

    public function for(PlanningScenario $scenario): string
    {
        return $this->fromSnapshots(
            $scenario->assumptions,
            $scenario->opportunity_snapshot,
            $scenario->result_snapshot,
            $scenario->reference_snapshot ?? [],
            $scenario->comparison_snapshot,
        );
    }

    public function matches(PlanningScenario $scenario): bool
    {
        return hash_equals($scenario->fingerprint, $this->for($scenario));
    }
}
