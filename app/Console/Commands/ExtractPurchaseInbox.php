<?php

namespace App\Console\Commands;

use App\Models\PurchaseInboxDocument;
use App\Services\Documents\PurchaseInboxAutomation;
use Illuminate\Console\Command;

class ExtractPurchaseInbox extends Command
{
    protected $signature = 'purchase-inbox:extract {--limit=3}';

    protected $description = 'Extract and classify pending supplier documents imported by email';

    public function handle(PurchaseInboxAutomation $automation): int
    {
        $documents = PurchaseInboxDocument::where('source', 'email')
            ->where('extraction_status', 'pending')
            ->whereHas('mailboxMessage.mailbox', fn ($query) => $query->where('auto_extract', true)->where('is_active', true))
            ->oldest()
            ->limit(max(1, min(20, (int) $this->option('limit'))))
            ->get();
        $success = 0;
        foreach ($documents as $document) {
            $success += $automation->extractAndClassify($document) ? 1 : 0;
        }
        $this->info("Extracted {$success} of {$documents->count()} pending email document(s).");

        return self::SUCCESS;
    }
}
