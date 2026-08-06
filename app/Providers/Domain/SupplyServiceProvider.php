<?php

namespace App\Providers\Domain;

use App\Contracts\PurchaseMailboxClient;
use App\Models\PurchaseDocument;
use App\Models\PurchaseDocumentLine;
use App\Models\Supplier;
use App\Observers\AuditableObserver;
use App\Observers\PartyLedgerObserver;
use App\Observers\SupplierPriceObserver;
use App\Services\Mailbox\NativeImapPurchaseMailboxClient;
use Illuminate\Support\ServiceProvider;

class SupplyServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(PurchaseMailboxClient::class, NativeImapPurchaseMailboxClient::class);
    }

    public function boot(): void
    {
        Supplier::observe(PartyLedgerObserver::class);
        PurchaseDocument::observe(AuditableObserver::class);
        PurchaseDocumentLine::observe(SupplierPriceObserver::class);
    }
}
