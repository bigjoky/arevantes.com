<?php

namespace App\Services\Ai;

use App\Models\KnowledgeChunk;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Laravel\Ai\Embeddings;
use Throwable;

class KnowledgeRetriever
{
    public function search(string $query, int $limit = 4, ?User $user = null, ?string $area = null): array
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

            $results = $this->authorizedQuery($user, $area)->whereNotNull('embedding')->get()
                ->map(fn (KnowledgeChunk $chunk) => $this->result($chunk, $this->cosine($queryVector, $chunk->embedding)))
                ->sortByDesc('score')->take($limit)->values()->all();

            return $results === [] ? $this->keywordSearch($query, $limit, $user, $area) : $results;
        } catch (Throwable) {
            return $this->keywordSearch($query, $limit, $user, $area);
        }
    }

    private function keywordSearch(string $query, int $limit, ?User $user, ?string $area): array
    {
        $terms = collect(preg_split('/[^\pL\pN]+/u', mb_strtolower($query)))
            ->filter(fn (string $term) => mb_strlen($term) >= 4)
            ->unique()
            ->take(8);

        if ($terms->isEmpty()) {
            return [];
        }

        return $this->authorizedQuery($user, $area)
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
            'document_id' => $chunk->document->id,
            'title' => $chunk->document->title,
            'version' => $chunk->document->version,
            'source' => $chunk->document->source,
            'source_url' => $chunk->document->source_url,
            'area' => $chunk->document->area,
            'score' => $score === null ? null : round($score, 4),
            'content' => $chunk->content,
            'excerpt' => mb_substr(trim(preg_replace('/\s+/u', ' ', $chunk->content)), 0, 360),
        ];
    }

    private function authorizedQuery(?User $user, ?string $area): Builder
    {
        return KnowledgeChunk::query()->with('document')
            ->whereHas('document', function (Builder $query) use ($user, $area): void {
                $query->where('status', 'indexed')
                    ->where(fn (Builder $areas) => $areas->where('area', 'general')->when($area, fn (Builder $builder) => $builder->orWhere('area', $area)));
                if ($user && ! $user->hasRole('admin')) {
                    $query->where(fn (Builder $roles) => $roles->whereNull('allowed_roles')->orWhereJsonContains('allowed_roles', $user->role));
                }
            });
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
