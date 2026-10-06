export type FinancialOverview = {
    year: number;
    as_of: string;
    today: string;
    metrics: { income: number; expenses: number; result: number };
    comparison: {
        income: number | null;
        expenses: number | null;
        result: number | null;
    };
    vat_payable: number | null;
    monthly: Array<{
        month: number;
        income: number | null;
        expenses: number | null;
        result: number | null;
    }>;
    balances: {
        treasury: number;
        customers: number;
        suppliers: number;
        vat: number;
    };
    fiscal_status: string | null;
    elapsed_months: number;
    posted_entries: number;
    pending_entries: number;
    pending_documents: number | null;
    entries: Array<{
        id: number;
        number: string;
        date: string;
        description: string;
        debit: number;
        credit: number;
        status: string;
        href: string;
    }>;
    documents_available: boolean;
    documents: Array<{
        id: string;
        date: string;
        type: string;
        number: string | null;
        party: string | null;
        currency?: string;
        amount: number;
        status: string;
        href: string;
    }>;
    tasks: Array<{
        id: string;
        title: string;
        count: number;
        due_on: string | null;
        href: string;
    }>;
    activity: Array<{
        id: string;
        description: string;
        reference: string;
        occurred_at: string;
        href: string;
    }>;
};
