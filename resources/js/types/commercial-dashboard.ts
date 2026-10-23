export type CommercialOverview = {
    period: 'month' | 'previous_month' | 'quarter' | 'year';
    from: string;
    to: string;
    today: string;
    scope: 'own' | 'team';
    permissions: { crm: boolean; quotes: boolean; sales: boolean };
    kpis: {
        sales: number | null;
        sales_change: number | null;
        pending_quotes: number | null;
        conversion: number | null;
        pipeline: number | null;
    };
    target: { amount: number; achieved: number } | null;
    monthly: Array<{
        month: string;
        sales: number | null;
        quotes: number | null;
        conversion: number | null;
    }>;
    pipeline: Array<{
        stage: string;
        label: string;
        count: number;
        closed: boolean;
    }> | null;
    quotes: Array<{ status: string; count: number; amount: number }> | null;
    customers: { new: number; statuses: Record<string, number> } | null;
    activity: Array<{
        id: string;
        date: string;
        type: string;
        description: string;
        reference: string | null;
        user: string | null;
        href: string;
    }>;
    tasks: Array<{
        id: number;
        title: string;
        reference: string | null;
        due_at: string;
        overdue: boolean;
        href: string;
    }> | null;
};
