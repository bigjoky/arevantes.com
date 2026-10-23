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

export type Auth = {
    user: User;
    abilities: AuthAbilities;
    navigation: WorkspaceNavItem[];
    shortcuts: { favorites: WorkspaceNavItem[]; recent: WorkspaceNavItem[] };
};

export type AuthAbilities = {
    manageAdmin: boolean;
    manageAccess: boolean;
    exportAudit: boolean;
    viewSystemHealth: boolean;
    viewAnalytics: boolean;
    manageCustomers: boolean;
    manageQuotes: boolean;
    approveQuotes: boolean;
    viewInstallations: boolean;
    manageInstallations: boolean;
    useAi: boolean;
    manageOperations: boolean;
    publishCustomerDocuments: boolean;
    manageCatalog: boolean;
    viewFinance: boolean;
    manageTreasury: boolean;
    manageAccounting: boolean;
    approvePayments: boolean;
    manageTax: boolean;
    managePurchases: boolean;
    viewPurchases: boolean;
    manageStaff: boolean;
    receivePurchases: boolean;
    approvePurchaseInvoices: boolean;
    approvePurchaseOrders: boolean;
    managePurchasePayments: boolean;
    managePurchaseSettings: boolean;
};

export type WorkspaceNavItem = {
    id?: string;
    links?: Array<{ title: string; href: string }>;
    pending?: string[];
    navigable?: boolean;
    title: string;
    code?: string;
    href: string;
    icon?: string;
    children?: WorkspaceNavItem[];
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
