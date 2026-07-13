<?php

namespace App\Console\Commands;

use App\Models\KnowledgeDocument;
use App\Services\Ai\KnowledgeIndexer;
use Illuminate\Console\Command;

class ReindexAiKnowledge extends Command
{
    protected $signature = 'ai:reindex';

    protected $description = 'Regenerate all local knowledge embeddings';

    public function handle(KnowledgeIndexer $indexer): int
    {
        foreach (KnowledgeDocument::all() as $document) {
            if (! is_file($document->source)) {
                $this->warn("Missing source: {$document->source}");

                continue;
            }
            $indexer->index($document->source, true);
            $this->info("Reindexed {$document->title}.");
        }

        return self::SUCCESS;
    }
}
