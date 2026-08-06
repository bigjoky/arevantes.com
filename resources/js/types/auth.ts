export type User = {
    id: number;
    name: string;
    email: string;
    role: 'admin' | 'commercial' | 'technician';
    avatar?: string;
    email_verified_at: string | null;
    two_factor_enabled?: boolean;
    created_at: string;
    updated_at: string;
    [key: string]: unknown;
};

export type PlatformAbilities = {
    manageCustomers: boolean;
    viewInstallations: boolean;
    manageInstallations: boolean;
    syncInstallations: boolean;
    useAi: boolean;
    manageOperations: boolean;
    manageCatalog: boolean;
    viewFinance: boolean;
    manageTreasury: boolean;
    manageAccounting: boolean;
    approvePayments: boolean;
    approveBudgets: boolean;
    manageTax: boolean;
    viewPurchases: boolean;
    managePurchases: boolean;
    receivePurchases: boolean;
    approvePurchaseInvoices: boolean;
    managePurchasePayments: boolean;
    manageStaff: boolean;
};

export type Auth = {
    user: User;
    abilities: PlatformAbilities;
};

/* @chisel-passkeys */
export type Passkey = {
    id: number;
    name: string;
    authenticator: string | null;
    created_at_diff: string;
    last_used_at_diff: string | null;
};
/* @end-chisel-passkeys */

export type TwoFactorSetupData = {
    svg: string;
    url: string;
};

export type TwoFactorSecretKey = {
    secretKey: string;
};
