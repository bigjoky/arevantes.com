<?php

namespace App\Services\Ai;

use App\Models\KnowledgeDocument;
use Illuminate\Support\Facades\File;
use Laravel\Ai\Embeddings;

class KnowledgeIndexer
{
    public function __construct(private KnowledgeSafetyScanner $safetyScanner) {}

    public function index(string $path, bool $force = false, bool $withEmbeddings = true): array
    {
        $content = trim(File::get($path));
        $document = KnowledgeDocument::firstOrNew(['source' => realpath($path)]);

        return $this->indexContent($document, $content, pathinfo($path, PATHINFO_FILENAME), $force, $withEmbeddings);
    }

    public function indexContent(KnowledgeDocument $document, string $content, string $title, bool $force = false, bool $withEmbeddings = true): array
    {
        $content = trim($content);
        $hash = hash('sha256', $content);

        if ($document->exists && $document->content_hash === $hash && ! $force && $document->indexed_at) {
            return ['document' => $document, 'changed' => false];
        }

        $changed = ! $document->exists || $document->content_hash !== $hash;
        if ($document->exists && $changed) {
            $document->version = ((int) $document->version) + 1;
        }

        $document->fill([
            'title' => $title,
            'version' => $document->version ?: 1,
            'content_hash' => $hash,
            'embedding_model' => $withEmbeddings ? config('ai.providers.ollama.models.embeddings.default') : null,
            'indexed_at' => null,
            'stale_at' => null,
            'status' => 'processing',
            'failure_reason' => null,
        ])->save();

        $findings = $this->safetyScanner->findings($content);
        if ($findings !== []) {
            $document->chunks()->delete();
            $document->update([
                'status' => 'quarantined',
                'failure_reason' => 'Potential malicious document instructions: '.implode(', ', $findings).'.',
            ]);
            $document->versions()->updateOrCreate(
                ['version' => $document->version],
                ['content_hash' => $hash, 'content' => $content, 'created_by' => $document->user_id],
            );

            return ['document' => $document, 'changed' => $changed, 'quarantined' => true];
        }

        $chunks = $this->chunks($content);
        $vectors = $withEmbeddings && $chunks !== []
            ? Embeddings::for($chunks)
                ->dimensions(config('ai.providers.ollama.models.embeddings.dimensions'))
                ->timeout(120)
                ->generate(config('ai.default_for_embeddings'))->embeddings
            : [];

        $document->chunks()->delete();
        foreach ($chunks as $position => $chunk) {
            $document->chunks()->create([
                'position' => $position,
                'content' => $chunk,
                'embedding' => $vectors[$position] ?? null,
            ]);
        }

        $document->update([
            'indexed_at' => now(),
            'stale_at' => now()->addDays((int) config('arevia.stale_after_days', 30)),
            'status' => 'indexed',
        ]);
        $document->versions()->updateOrCreate(
            ['version' => $document->version],
            ['content_hash' => $hash, 'content' => $content, 'created_by' => $document->user_id, 'indexed_at' => $document->indexed_at],
        );

        return ['document' => $document, 'changed' => true];
    }

    private function chunks(string $content): array
    {
        $chunks = [];
        for ($offset = 0; $offset < mb_strlen($content); $offset += 1300) {
            $chunks[] = mb_substr($content, $offset, 1500);
        }

        return $chunks;
    }
}
