<?php

namespace App\Services\Mailbox;

use App\Contracts\PurchaseMailboxClient;
use App\Models\PurchaseDocumentAttachment;
use App\Models\PurchaseInboxDocument;
use App\Models\PurchaseMailbox;
use App\Models\PurchaseMailboxMessage;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Throwable;

class PurchaseMailboxSynchronizer
{
    private const ALLOWED_MIMES = ['application/pdf', 'image/jpeg', 'image/png', 'image/webp'];

    private const ALLOWED_EXTENSIONS = ['pdf', 'jpg', 'jpeg', 'png', 'webp'];

    public function __construct(private PurchaseMailboxClient $client) {}

    /** @return array{mailboxes: int, messages: int, documents: int, skipped: int} */
    public function sync(?PurchaseMailbox $only = null): array
    {
        $totals = ['mailboxes' => 0, 'messages' => 0, 'documents' => 0, 'skipped' => 0];
        $mailboxes = $only ? collect([$only]) : PurchaseMailbox::where('is_active', true)->get();
        foreach ($mailboxes as $mailbox) {
            $stats = $this->syncMailbox($mailbox);
            $totals['mailboxes']++;
            foreach (['messages', 'documents', 'skipped'] as $key) {
                $totals[$key] += $stats[$key];
            }
        }

        return $totals;
    }

    /** @return array{messages: int, documents: int, skipped: int} */
    public function syncMailbox(PurchaseMailbox $mailbox): array
    {
        $stats = ['messages' => 0, 'documents' => 0, 'skipped' => 0];
        $mailbox->update(['last_sync_attempt_at' => now()]);
        $since = $mailbox->last_synced_at?->subDay() ?? now()->subDays($mailbox->lookback_days);

        try {
            foreach ($this->client->messages($mailbox, $since) as $message) {
                if ($mailbox->messages()->where('message_uid', $message['uid'])->exists()) {
                    $stats['skipped']++;

                    continue;
                }
                $stats['messages']++;
                $record = $mailbox->messages()->create([
                    'message_uid' => $message['uid'],
                    'message_id' => $message['message_id'] ?? null,
                    'subject' => Str::limit($message['subject'] ?? '', 255, ''),
                    'from_name' => Str::limit($message['from_name'] ?? '', 255, ''),
                    'from_email' => Str::limit($message['from_email'] ?? '', 255, ''),
                    'received_at' => $message['received_at'] ?? null,
                ]);
                $this->importMessage($record, $message['attachments'] ?? [], $stats);
            }
            $mailbox->update(['last_synced_at' => now(), 'last_error' => null]);
        } catch (Throwable $exception) {
            report($exception);
            $mailbox->update(['last_error' => Str::limit($exception->getMessage(), 2000)]);
            throw $exception;
        }

        return $stats;
    }

    private function importMessage(PurchaseMailboxMessage $message, array $attachments, array &$stats): void
    {
        $imported = 0;
        try {
            foreach ($attachments as $attachment) {
                $content = (string) ($attachment['content'] ?? '');
                $name = basename(str_replace('\\', '/', (string) ($attachment['name'] ?? 'attachment')));
                $extension = strtolower(pathinfo($name, PATHINFO_EXTENSION));
                $mime = $this->normalizedMime((string) ($attachment['mime_type'] ?? ''), $extension);
                if (! in_array($mime, self::ALLOWED_MIMES, true) || ! in_array($extension, self::ALLOWED_EXTENSIONS, true) || $content === '' || strlen($content) > 15 * 1024 * 1024) {
                    continue;
                }
                $hash = hash('sha256', $content);
                if (PurchaseInboxDocument::where('file_hash', $hash)->exists() || PurchaseDocumentAttachment::where('file_hash', $hash)->exists()) {
                    $stats['skipped']++;

                    continue;
                }
                $path = 'purchase-inbox/email/'.now()->format('Y/m').'/'.Str::uuid().'.'.$extension;
                Storage::disk(config('filesystems.private'))->put($path, $content);
                try {
                    PurchaseInboxDocument::create([
                        'purchase_mailbox_message_id' => $message->id,
                        'source' => 'email',
                        'original_name' => Str::limit($name, 255, ''),
                        'path' => $path,
                        'mime_type' => $mime,
                        'size' => strlen($content),
                        'file_hash' => $hash,
                    ]);
                } catch (Throwable $exception) {
                    Storage::disk(config('filesystems.private'))->delete($path);
                    throw $exception;
                }
                $imported++;
                $stats['documents']++;
            }
            $message->update([
                'status' => $imported > 0 ? 'imported' : (count($attachments) > 0 ? 'duplicates_or_unsupported' : 'no_attachments'),
                'attachment_count' => count($attachments),
                'imported_count' => $imported,
                'processed_at' => now(),
            ]);
        } catch (Throwable $exception) {
            $message->update(['status' => 'failed', 'error' => Str::limit($exception->getMessage(), 2000), 'attachment_count' => count($attachments), 'imported_count' => $imported, 'processed_at' => now()]);
            report($exception);
        }
    }

    private function normalizedMime(string $mime, string $extension): string
    {
        $mime = strtolower(trim(strtok($mime, ';') ?: $mime));
        if (in_array($mime, ['application/octet-stream', 'application/x-pdf'], true)) {
            return match ($extension) {
                'pdf' => 'application/pdf',
                'jpg', 'jpeg' => 'image/jpeg',
                'png' => 'image/png',
                'webp' => 'image/webp',
                default => $mime,
            };
        }

        return $mime === 'image/jpg' ? 'image/jpeg' : $mime;
    }
}
