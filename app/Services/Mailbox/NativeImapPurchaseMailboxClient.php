<?php

namespace App\Services\Mailbox;

use App\Contracts\PurchaseMailboxClient;
use App\Models\PurchaseMailbox;
use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;
use RuntimeException;

class NativeImapPurchaseMailboxClient implements PurchaseMailboxClient
{
    public function messages(PurchaseMailbox $mailbox, CarbonInterface $since): iterable
    {
        if (! function_exists('imap_open')) {
            throw new RuntimeException(__('The PHP IMAP extension is not installed.'));
        }

        imap_timeout(IMAP_OPENTIMEOUT, 15);
        imap_timeout(IMAP_READTIMEOUT, 30);
        $connection = @imap_open($this->mailboxPath($mailbox), $mailbox->username, $mailbox->password, OP_READONLY);
        if (! $connection) {
            throw new RuntimeException(__('The mailbox connection failed. Check the server, encryption and credentials.'));
        }

        try {
            $uids = imap_search($connection, 'SINCE "'.$since->format('d-M-Y').'"', SE_UID) ?: [];
            sort($uids, SORT_NUMERIC);
            foreach ($uids as $uid) {
                $overview = imap_fetch_overview($connection, (string) $uid, FT_UID)[0] ?? null;
                $messageNumber = imap_msgno($connection, (int) $uid);
                $header = $messageNumber ? imap_headerinfo($connection, $messageNumber) : null;
                $from = $header->from[0] ?? null;
                $structure = imap_fetchstructure($connection, (int) $uid, FT_UID);
                if (! $overview || ! $structure) {
                    continue;
                }

                yield [
                    'uid' => (string) $uid,
                    'message_id' => isset($overview->message_id) ? trim((string) $overview->message_id, '<>') : null,
                    'subject' => $this->decode((string) ($overview->subject ?? '')),
                    'from_name' => $this->decode((string) ($from->personal ?? '')),
                    'from_email' => $from ? strtolower(($from->mailbox ?? '').'@'.($from->host ?? '')) : null,
                    'received_at' => $this->date($overview->date ?? null),
                    'attachments' => $this->attachments($connection, (int) $uid, $structure),
                ];
            }
        } finally {
            imap_close($connection);
        }
    }

    private function mailboxPath(PurchaseMailbox $mailbox): string
    {
        $flags = '/imap';
        $flags .= match ($mailbox->encryption) {
            'ssl' => '/ssl',
            'tls' => '/tls',
            default => '/notls',
        };
        if (! $mailbox->validate_certificate && $mailbox->encryption !== 'none') {
            $flags .= '/novalidate-cert';
        }

        return sprintf('{%s:%d%s}%s', $mailbox->host, $mailbox->port, $flags, $mailbox->folder);
    }

    private function attachments($connection, int $uid, object $structure): array
    {
        $attachments = [];
        $this->walkParts($connection, $uid, $structure, '', $attachments);

        return $attachments;
    }

    private function walkParts($connection, int $uid, object $part, string $number, array &$attachments): void
    {
        if (! empty($part->parts)) {
            foreach ($part->parts as $index => $child) {
                $childNumber = $number === '' ? (string) ($index + 1) : $number.'.'.($index + 1);
                $this->walkParts($connection, $uid, $child, $childNumber, $attachments);
            }

            return;
        }

        $filename = $this->filename($part);
        if ($filename === '') {
            return;
        }
        $body = (string) imap_fetchbody($connection, $uid, $number ?: '1', FT_UID | FT_PEEK);
        $content = match ((int) ($part->encoding ?? 0)) {
            ENCBASE64 => base64_decode($body, true) ?: '',
            ENCQUOTEDPRINTABLE => quoted_printable_decode($body),
            default => $body,
        };
        $attachments[] = ['name' => $filename, 'mime_type' => $this->mimeType($part), 'content' => $content];
    }

    private function filename(object $part): string
    {
        foreach (array_merge($part->dparameters ?? [], $part->parameters ?? []) as $parameter) {
            if (in_array(strtolower((string) ($parameter->attribute ?? '')), ['filename', 'name'], true)) {
                return $this->decode((string) ($parameter->value ?? ''));
            }
        }

        return '';
    }

    private function mimeType(object $part): string
    {
        $types = ['text', 'multipart', 'message', 'application', 'audio', 'image', 'video', 'other'];

        return ($types[(int) ($part->type ?? 7)] ?? 'application').'/'.strtolower((string) ($part->subtype ?? 'octet-stream'));
    }

    private function decode(string $value): string
    {
        $parts = imap_mime_header_decode($value);
        if (! is_array($parts)) {
            return trim($value);
        }

        return trim(collect($parts)->map(fn ($part) => $part->text)->implode(''));
    }

    private function date(?string $value): ?Carbon
    {
        try {
            return $value ? Carbon::parse($value) : null;
        } catch (\Throwable) {
            return null;
        }
    }
}
