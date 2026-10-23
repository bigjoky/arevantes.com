<?php

namespace App\Services\Quotes;

use App\Models\DocumentDelivery;
use Illuminate\Support\Facades\Storage;

class QuoteDeliveryArchive
{
    private function path(DocumentDelivery $delivery): string
    {
        return 'quotes/'.$delivery->quote_id.'/deliveries/'.$delivery->id.'-'.$delivery->document_hash.'.pdf';
    }

    public function store(DocumentDelivery $delivery, string $bytes): void
    {
        if (! Storage::disk(config('filesystems.private'))->put($this->path($delivery), $bytes)) {
            throw new \RuntimeException('Could not archive the quote PDF.');
        }
    }

    public function exists(DocumentDelivery $delivery): bool
    {
        return $delivery->quote_id && $delivery->document_hash && Storage::disk(config('filesystems.private'))->exists($this->path($delivery));
    }

    public function bytes(DocumentDelivery $delivery): string
    {
        abort_unless($this->exists($delivery), 404, __('The original PDF was not archived for this delivery.'));

        return Storage::disk(config('filesystems.private'))->get($this->path($delivery));
    }
}
