<?php

namespace App\Console\Commands;

use App\Services\Ai\KnowledgeIndexer;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use SplFileInfo;

class IngestAiKnowledge extends Command
{
    protected $signature = 'ai:ingest {path : Markdown or text file, or a directory containing them} {--force} {--no-embeddings}';

    protected $description = 'Import private CRM documentation into the local AI knowledge base';

    public function handle(KnowledgeIndexer $indexer): int
    {
        $path = realpath($this->argument('path'));

        if (! $path) {
            $this->error('The knowledge path does not exist.');

            return self::FAILURE;
        }

        $files = is_dir($path)
            ? collect(File::allFiles($path))->filter(fn (SplFileInfo $file) => in_array(strtolower($file->getExtension()), ['md', 'txt'], true))
            : collect([new SplFileInfo($path)]);

        foreach ($files as $file) {
            if (trim(File::get($file->getRealPath())) === '') {
                continue;
            }
            $result = $indexer->index($file->getRealPath(), (bool) $this->option('force'), ! $this->option('no-embeddings'));
            $this->info(($result['changed'] ? 'Indexed ' : 'Unchanged ').$file->getFilename()." (v{$result['document']->version}).");
        }

        return self::SUCCESS;
    }
}
