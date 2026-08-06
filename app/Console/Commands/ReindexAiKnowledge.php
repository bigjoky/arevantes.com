<?php

namespace App\Console\Commands;

use App\Models\KnowledgeDocument;
use App\Services\Ai\KnowledgeIndexer;
use App\Services\Ai\KnowledgeSourceLoader;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use Throwable;

class ReindexAiKnowledge extends Command
{
    protected $signature = 'ai:reindex
        {--stale : Only refresh indexed documents whose review date has passed}
        {--failed : Only retry documents whose last indexing attempt failed}';

    protected $description = 'Regenerate all local knowledge embeddings';

    public function handle(KnowledgeIndexer $indexer, KnowledgeSourceLoader $sourceLoader): int
    {
        $documents = KnowledgeDocument::query()
            ->when($this->option('stale'), fn ($query) => $query->where('status', 'indexed')->where('stale_at', '<=', now()))
            ->when($this->option('failed'), fn ($query) => $query->where('status', 'failed'))
            ->get();
        $failed = 0;
        foreach ($documents as $document) {
            if (! $document->source_url && ! is_file($document->source)) {
                $this->warn("Missing source: {$document->source}");
                $document->update(['status' => 'failed', 'failure_reason' => 'The stored source file is missing.']);
                $failed++;

                continue;
            }
            try {
                $document->update(['status' => 'processing', 'failure_reason' => null]);
                if ($document->source_url) {
                    $content = $sourceLoader->fromUrl($document->source_url);
                    File::put($document->source, $content);
                    $document->update(['file_size' => strlen($content)]);
                    $indexer->indexContent($document, $content, $document->title, true, filled($document->embedding_model));
                } else {
                    $indexer->index($document->source, true, filled($document->embedding_model));
                }
                $this->info("Reindexed {$document->title}.");
            } catch (Throwable $exception) {
                $document->update(['status' => 'failed', 'failure_reason' => Str::limit($exception->getMessage(), 1000)]);
                $this->error("Failed {$document->title}: {$exception->getMessage()}");
                $failed++;
            }
        }

        return $failed === 0 ? self::SUCCESS : self::FAILURE;
    }
}
