<?php

namespace App\Services\Ai;

class KnowledgeSafetyScanner
{
    /** @return array<int, string> */
    public function findings(string $content): array
    {
        $patterns = [
            '/ignore (all |any )?(previous|prior|system) instructions?/iu' => 'instruction override',
            '/(reveal|show|print|exfiltrate).{0,40}(system prompt|secret|password|api key|token)/iu' => 'secret exfiltration',
            '/(act|behave|respond) as (the )?(system|developer|administrator)/iu' => 'role escalation',
            '/<\/?(system|assistant|developer|tool)[^>]*>/iu' => 'forged message boundary',
        ];

        return collect($patterns)
            ->filter(fn (string $label, string $pattern) => preg_match($pattern, $content) === 1)
            ->values()->all();
    }
}
