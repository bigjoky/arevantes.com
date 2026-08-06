<?php

namespace App\Services\Ai;

class AreviaResponseEvaluator
{
    public function score(string $response, array $sources): int
    {
        $score = trim($response) === '' ? 0 : 35;
        $score += min(20, (int) floor(mb_strlen($response) / 80));
        if ($sources === []) {
            return min($score + (str_contains(mb_strtolower($response), 'no ') ? 15 : 0), 70);
        }

        $cited = collect(array_keys($sources))->contains(fn (int $index) => str_contains($response, '[S'.($index + 1).']'));

        return min($score + ($cited ? 35 : 0), 100);
    }

    public function costMicros(array $usage): int
    {
        $input = (int) ($usage['prompt_tokens'] ?? $usage['input_tokens'] ?? 0);
        $output = (int) ($usage['completion_tokens'] ?? $usage['output_tokens'] ?? 0);

        return (int) round(($input * (float) config('arevia.cost_per_million_input', 0)
            + $output * (float) config('arevia.cost_per_million_output', 0)) / 1_000_000 * 1_000_000);
    }
}
