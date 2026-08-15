<?php

namespace App\Services\Ai;

class AreviaCitationValidator
{
    public function analyze(string $response, array $sources): array
    {
        preg_match_all('/\[S(\d+)]/i', $response, $matches);
        $markers = collect($matches[1] ?? [])->map(fn (string $value) => (int) $value)->unique()->values();
        $valid = $markers->filter(fn (int $marker) => $marker >= 1 && $marker <= count($sources))->values();
        $invalid = $markers->diff($valid)->values();
        $uncitedRecommendations = collect(preg_split('/\R{2,}/u', trim($response)))
            ->filter(fn (string $paragraph) => preg_match('/\b(recomend|deber[ií]a|conviene|es necesario|siguiente paso|debe(?:n)?|propon|recommend|should|must|next step|suggest)/iu', $paragraph) === 1)
            ->reject(fn (string $paragraph) => preg_match('/\[S\d+]/i', $paragraph) === 1)
            ->values();

        return [
            'passes' => $sources === [] || ($valid->isNotEmpty() && $invalid->isEmpty() && $uncitedRecommendations->isEmpty()),
            'valid_markers' => $valid->all(),
            'invalid_markers' => $invalid->all(),
            'uncited_recommendations' => $uncitedRecommendations->count(),
            'source_coverage' => $sources === [] ? 1.0 : round($valid->count() / count($sources), 4),
        ];
    }

    public function repairPrompt(string $prompt, string $draft, array $sources): string
    {
        $markers = collect(array_keys($sources))->map(fn (int $index) => '[S'.($index + 1).']')->implode(', ');

        return $prompt."\n\nYour previous draft did not meet the mandatory traceability rules. Rewrite it now. "
            ."Use only valid source markers ({$markers}). Put a source marker in the same paragraph as every material factual claim and every recommendation. "
            ."Never invent a marker and never cite a source that does not support the paragraph. If the sources are insufficient, say so instead of making the claim.\n\nPrevious draft:\n{$draft}";
    }

    public function safeAbstention(array $sources): string
    {
        $markers = collect(array_keys($sources))->map(fn (int $index) => '[S'.($index + 1).']')->implode(', ');

        return "No he podido generar una respuesta con trazabilidad suficiente, así que no presentaré afirmaciones ni recomendaciones sin verificar. Puedes consultar las fuentes recuperadas: {$markers}.";
    }
}
