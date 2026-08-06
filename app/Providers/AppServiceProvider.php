<?php

namespace App\Providers;

use App\Contracts\PurchaseMailboxClient;
use App\Models\CompanyProfile;
use App\Models\CrmActivity;
use App\Models\DocumentSeries;
use App\Models\FiscalYear;
use App\Models\Installation;
use App\Models\Opportunity;
use App\Models\Person;
use App\Models\Supplier;
use App\Models\User;
use App\Models\WorkOrder;
use App\Observers\AuditableObserver;
use App\Observers\PartyLedgerObserver;
use App\Observers\WorkOrderObserver;
use App\Policies\CrmActivityPolicy;
use App\Services\Mailbox\NativeImapPurchaseMailboxClient;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->bind(PurchaseMailboxClient::class, NativeImapPurchaseMailboxClient::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->configureDefaults();
        $this->configureAuthorization();
        Person::observe(AuditableObserver::class);
        Person::observe(PartyLedgerObserver::class);
        Supplier::observe(PartyLedgerObserver::class);
        Installation::observe(AuditableObserver::class);
        WorkOrder::observe(WorkOrderObserver::class);
        Opportunity::observe(AuditableObserver::class);
        CompanyProfile::observe(AuditableObserver::class);
        FiscalYear::observe(AuditableObserver::class);
        DocumentSeries::observe(AuditableObserver::class);
    }

    private function configureAuthorization(): void
    {
        Gate::policy(CrmActivity::class, CrmActivityPolicy::class);
        Gate::before(fn (User $user) => $user->hasRole('admin') ? true : null);
        Gate::define('manage-customers', fn (User $user) => $user->hasRole('commercial'));
        Gate::define('view-installations', fn (User $user) => $user->hasRole('commercial', 'technician'));
        Gate::define('manage-installations', fn (User $user) => $user->hasRole('commercial'));
        Gate::define('sync-installations', fn (User $user) => $user->hasRole('technician'));
        Gate::define('use-ai', fn (User $user) => $user->hasRole('commercial', 'technician'));
        Gate::define('manage-operations', fn (User $user) => $user->hasRole('commercial', 'technician'));
        Gate::define('manage-catalog', fn (User $user) => $user->hasRole('commercial'));
        Gate::define('manage-staff', fn (User $user) => false);
    }

    /**
     * Configure default behaviors for production-ready applications.
     */
    protected function configureDefaults(): void
    {
        Date::use(CarbonImmutable::class);

        DB::prohibitDestructiveCommands(
            app()->isProduction(),
        );

        Password::defaults(fn (): ?Password => app()->isProduction()
            ? Password::min(12)
                ->mixedCase()
                ->letters()
                ->numbers()
                ->symbols()
                ->uncompromised()
            : null,
        );
    }
}
