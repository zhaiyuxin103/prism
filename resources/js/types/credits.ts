import { formatCost } from './generation';

export type CreditBalance = {
    balance: string;
    reserved: string;
    available: string;
    has_debt: boolean;
};

export type CreditEntry = {
    id: number;
    type: 'purchase' | 'reservation' | 'settlement';
    amount: string;
    reserved_amount: string;
    balance_after: string;
    reserved_after: string;
    generation_id: string | null;
    created_at: string;
};

export type CreditEntryPage = {
    data: CreditEntry[];
    links: { prev: string | null; next: string | null };
    meta: { current_page: number; last_page: number; total: number };
};

function units(value: string): bigint {
    const negative = value.startsWith('-');
    const [whole, fraction = ''] = (negative ? value.slice(1) : value).split(
        '.',
    );
    const amount = BigInt(whole + fraction.padEnd(18, '0'));
    return negative ? -amount : amount;
}

export function canAfford(available: string, required: string): boolean {
    return units(available) >= units(required);
}

export function formatCredit(value: string): string {
    return value.startsWith('-')
        ? `-${formatCost(value.slice(1))}`
        : formatCost(value);
}
