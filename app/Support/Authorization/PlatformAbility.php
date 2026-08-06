<?php

namespace App\Support\Authorization;

use App\Models\User;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;

enum PlatformAbility: string
{
    case ManageCustomers = 'manage-customers';
    case ViewInstallations = 'view-installations';
    case ManageInstallations = 'manage-installations';
    case SyncInstallations = 'sync-installations';
    case UseAi = 'use-ai';
    case ManageOperations = 'manage-operations';
    case ManageCatalog = 'manage-catalog';
    case ViewFinance = 'view-finance';
    case ManageTreasury = 'manage-treasury';
    case ManageAccounting = 'manage-accounting';
    case ApprovePayments = 'approve-payments';
    case ApproveBudgets = 'approve-budgets';
    case ManageTax = 'manage-tax';
    case ViewPurchases = 'view-purchases';
    case ManagePurchases = 'manage-purchases';
    case ReceivePurchases = 'receive-purchases';
    case ApprovePurchaseInvoices = 'approve-purchase-invoices';
    case ManagePurchasePayments = 'manage-purchase-payments';
    case ManageStaff = 'manage-staff';

    /** @return list<string> */
    public function roles(): array
    {
        return match ($this) {
            self::ManageCustomers,
            self::ManageInstallations,
            self::ManageCatalog,
            self::ViewFinance,
            self::ManageTreasury,
            self::ManageAccounting,
            self::ManagePurchases,
            self::ApprovePurchaseInvoices,
            self::ManagePurchasePayments => ['commercial'],
            self::ViewInstallations,
            self::UseAi,
            self::ManageOperations,
            self::ViewPurchases,
            self::ReceivePurchases => ['commercial', 'technician'],
            self::SyncInstallations => ['technician'],
            self::ApprovePayments,
            self::ApproveBudgets,
            self::ManageTax,
            self::ManageStaff => [],
        };
    }

    public function allows(User $user): bool
    {
        return in_array($user->role, $this->roles(), true);
    }

    public function frontendKey(): string
    {
        return Str::camel($this->value);
    }

    /** @return array<string, bool> */
    public static function frontendMap(): array
    {
        return collect(self::cases())
            ->mapWithKeys(fn (self $ability) => [$ability->frontendKey() => Gate::allows($ability->value)])
            ->all();
    }
}
