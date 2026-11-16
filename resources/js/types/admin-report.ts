export type ReportAmounts = {
    generations: number;
    failed: number;
    user_charge: string;
    provider_cost: string;
    profit: string;
};

export type Reconciliation = {
    status: 'matched' | 'mismatch';
    provider_cost: string;
    provider_generations: number;
    cost_difference: string;
    generation_difference: number;
};

export type UserReport = {
    id: number | null;
    name: string;
    email: string | null;
    amounts: ReportAmounts;
    days: { date: string; amounts: ReportAmounts }[];
};

export type AdminReport = {
    totals: ReportAmounts;
    days: {
        date: string;
        amounts: ReportAmounts;
        reconciliation: Reconciliation | null;
    }[];
    reconciliation: {
        status: 'matched' | 'mismatch' | 'unavailable';
        message: string | null;
        checked_at: string | null;
        total: Reconciliation | null;
    };
    unresolved_generations: number;
    generated_at: string;
};

export type UserReportPage = {
    data: UserReport[];
    current_page: number;
    last_page: number;
    total: number;
    prev_page_url: string | null;
    next_page_url: string | null;
};
