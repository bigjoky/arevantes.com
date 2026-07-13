<?php

namespace App\Services\Ai;

use App\Models\KnowledgeChunk;
use Laravel\Ai\Embeddings;
use Throwable;

class KnowledgeRetriever
{
    public function search(string $query, int $limit = 4): array
    {
        if (blank($query)) {
            return [];
        }

        if (! KnowledgeChunk::query()->exists()) {
            return [];
        }

        try {
            $queryVector = Embeddings::for([$query])
                ->dimensions(config('ai.providers.ollama.models.embeddings.dimensions'))
                ->timeout(30)
                ->generate(config('ai.default_for_embeddings'))->first();

            return KnowledgeChunk::query()->with('document')->whereNotNull('embedding')->get()
                ->map(fn (KnowledgeChunk $chunk) => $this->result($chunk, $this->cosine($queryVector, $chunk->embedding)))
                ->sortByDesc('score')->take($limit)->values()->all();
        } catch (Throwable) {
            return $this->keywordSearch($query, $limit);
        }
    }

    private function keywordSearch(string $query, int $limit): array
    {
        $terms = collect(preg_split('/[^\pL\pN]+/u', mb_strtolower($query)))
            ->filter(fn (string $term) => mb_strlen($term) >= 4)
            ->unique()
            ->take(8);

        if ($terms->isEmpty()) {
            return [];
        }

        return KnowledgeChunk::query()->with('document')
            ->where(function ($builder) use ($terms): void {
                foreach ($terms as $term) {
                    $builder->orWhere('content', 'like', "%{$term}%");
                }
            })
            ->limit($limit)
            ->get()->map(fn (KnowledgeChunk $chunk) => $this->result($chunk, null))->all();
    }

    private function result(KnowledgeChunk $chunk, ?float $score): array
    {
        return [
            'chunk_id' => $chunk->id,
            'title' => $chunk->document->title,
            'version' => $chunk->document->version,
            'source' => $chunk->document->source,
            'score' => $score === null ? null : round($score, 4),
            'content' => $chunk->content,
        ];
    }

    private function cosine(array $left, array $right): float
    {
        $dot = $leftNorm = $rightNorm = 0.0;
        foreach ($left as $index => $value) {
            $other = $right[$index] ?? 0.0;
            $dot += $value * $other;
            $leftNorm += $value * $value;
            $rightNorm += $other * $other;
        }

        return $dot / max(sqrt($leftNorm) * sqrt($rightNorm), PHP_FLOAT_EPSILON);
    }
}
