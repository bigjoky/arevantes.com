<?php

namespace App\Services\Ai;

class AreviaResponseEvaluator
{
    public function __construct(private AreviaCitationValidator $citations) {}

    public function score(string $response, array $sources, bool $abstained = false): int
    {
        if (trim($response) === '') {
            return 0;
        }

        $score = 25 + min(15, (int) floor(mb_strlen($response) / 120));
        if ($sources === []) {
            return min($score + (str_contains(mb_strtolower($response), 'no ') ? 15 : 0), 55);
        }

        if ($abstained) {
            return 50;
        }

        $analysis = $this->citations->analyze($response, $sources);
        if (! $analysis['passes']) {
            return min($score, 35);
        }

        return min($score + 40 + (int) round(20 * $analysis['source_coverage']), 100);
    }

    public function costMicros(array $usage): int
    {
        $input = (int) ($usage['prompt_tokens'] ?? $usage['input_tokens'] ?? 0);
        $output = (int) ($usage['completion_tokens'] ?? $usage['output_tokens'] ?? 0);

        return (int) round(($input * (float) config('arevia.cost_per_million_input', 0)
            + $output * (float) config('arevia.cost_per_million_output', 0)) / 1_000_000 * 1_000_000);
    }
}
