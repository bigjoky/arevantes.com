<?php

namespace App\Observers;

use App\Models\PurchaseDocumentLine;
use App\Services\Supply\SupplierCatalogRecorder;

class SupplierPriceObserver
{
    public function created(PurchaseDocumentLine $line): void
    {
        app(SupplierCatalogRecorder::class)->recordLine($line);
    }

    public function updated(PurchaseDocumentLine $line): void
    {
        if ($line->wasChanged('unit_price')) {
            app(SupplierCatalogRecorder::class)->recordLine($line);
        }
    }
}
