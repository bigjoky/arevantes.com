<?php

namespace App\Providers\Domain;

use App\Models\Installation;
use App\Models\Opportunity;
use App\Models\Person;
use App\Observers\AuditableObserver;
use App\Observers\OpportunityStageObserver;
use App\Observers\PartyLedgerObserver;
use Illuminate\Support\ServiceProvider;

class CrmServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        Person::observe(AuditableObserver::class);
        Person::observe(PartyLedgerObserver::class);
        Opportunity::observe(AuditableObserver::class);
        Opportunity::observe(OpportunityStageObserver::class);
        Installation::observe(AuditableObserver::class);
    }
}
