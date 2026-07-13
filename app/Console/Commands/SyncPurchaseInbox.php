<?php

namespace App\Console\Commands;

use App\Models\PurchaseMailbox;
use App\Services\Mailbox\PurchaseMailboxSynchronizer;
use Illuminate\Console\Command;
use Throwable;

class SyncPurchaseInbox extends Command
{
    protected $signature = 'purchase-inbox:sync {mailbox?}';

    protected $description = 'Import supplier documents from configured IMAP mailboxes';

    public function handle(PurchaseMailboxSynchronizer $synchronizer): int
    {
        $mailbox = $this->argument('mailbox') ? PurchaseMailbox::findOrFail($this->argument('mailbox')) : null;
        try {
            $stats = $synchronizer->sync($mailbox);
            $this->info("Synced {$stats['mailboxes']} mailbox(es), read {$stats['messages']} new message(s), imported {$stats['documents']} document(s).");

            return self::SUCCESS;
        } catch (Throwable $exception) {
            $this->error($exception->getMessage());

            return self::FAILURE;
        }
    }
}
