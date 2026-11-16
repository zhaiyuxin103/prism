import { expect, test, vi } from 'vite-plus/test';
import { createSSRApp, defineComponent, h } from 'vue';
import { renderToString } from 'vue/server-renderer';
import type {
    AdminReport as Report,
    UserReportPage,
} from '@/types/admin-report';
import AdminReport from './AdminReport.vue';
import { formatCost } from '@/types/generation';

vi.mock('@inertiajs/vue3', () => ({
    Head: defineComponent({ setup: () => () => null }),
    Form: defineComponent({
        setup:
            (_, { slots }) =>
            () =>
                h('form', slots.default?.({ errors: {}, processing: false })),
    }),
    Link: defineComponent({
        setup:
            (_, { slots }) =>
            () =>
                h('a', slots.default?.()),
    }),
}));

function report(): Report {
    const amounts = {
        generations: 1,
        failed: 0,
        user_charge: '0.000000001250000000',
        provider_cost: '0.000000001000000000',
        profit: '0.000000000250000000',
    };
    return {
        totals: amounts,
        days: [{ date: '2026-11-16', amounts, reconciliation: null }],
        reconciliation: {
            status: 'unavailable',
            message: 'Provider usage is unavailable.',
            checked_at: null,
            total: null,
        },
        unresolved_generations: 1,
        generated_at: '2026-11-17T00:00:00Z',
    };
}

function render(value: Report, users: UserReportPage): Promise<string> {
    return renderToString(
        createSSRApp(AdminReport, {
            filters: { start_date: '2026-11-16', end_date: '2026-11-16' },
            report: value,
            users,
        }),
    );
}

const emptyUsers: UserReportPage = {
    data: [],
    current_page: 1,
    last_page: 1,
    total: 0,
    prev_page_url: null,
    next_page_url: null,
};

test('shows exact money and an unchecked state when the provider is unavailable', async () => {
    const html = await render(report(), emptyUsers);

    expect(html).toContain('$0.00000000125');
    expect(html).toContain('$0.00000000025');
    expect(html).toContain('Reconciliation unavailable');
    expect(html).toContain('Not checked');
    expect(html).not.toContain('Provider costs match');
    expect(html).toContain('No completed, settled generations');
});

test('renders user daily details and escapes user supplied names', async () => {
    const value = report();
    const html = await render(value, {
        ...emptyUsers,
        total: 1,
        data: [
            {
                id: 1,
                name: '<script>alert(1)</script>',
                email: 'operator@example.com',
                amounts: value.totals,
                days: [{ date: '2026-11-16', amounts: value.totals }],
            },
        ],
    });

    expect(html).toContain('&lt;script&gt;alert(1)&lt;/script&gt;');
    expect(html).not.toContain('<script>alert(1)</script>');
    expect(html).toContain('Daily totals for');
    expect(html).toContain('operator@example.com');
    expect(html).toContain('expand daily details');
});

test('shows a daily mismatch even if the overall cost difference is zero', async () => {
    const value = report();
    const comparison = {
        status: 'mismatch' as const,
        provider_cost: '0.000000000',
        provider_generations: 0,
        cost_difference: '0.000000001000000000',
        generation_difference: 1,
    };
    value.days[0].reconciliation = comparison;
    value.reconciliation = {
        status: 'mismatch',
        message: null,
        checked_at: '2026-11-17T00:00:00Z',
        total: {
            ...comparison,
            status: 'matched',
            cost_difference: '0.000000000000000000',
            generation_difference: 0,
        },
    };

    const html = await render(value, emptyUsers);

    expect(html).toContain('Reconciliation difference found');
    expect(html).not.toContain('Provider costs match');
    expect(html).toContain('Difference');
    expect(html).toContain('0.000000001');
});

function emptyDay(date: string): Report['days'][number] {
    return {
        date,
        amounts: {
            generations: 0,
            failed: 0,
            user_charge: '0.000000000000000000',
            provider_cost: '0.000000000000000000',
            profit: '0.000000000000000000',
        },
        reconciliation: {
            status: 'matched',
            provider_cost: '0.000000000',
            provider_generations: 0,
            cost_difference: '0.000000000000000000',
            generation_difference: 0,
        },
    };
}

test('hides only confirmed empty days and keeps failures, differences and unchecked days', async () => {
    const value = report();
    const failed = emptyDay('2026-11-02');
    failed.amounts.failed = 1;
    const providerOnly = emptyDay('2026-11-03');
    providerOnly.reconciliation = {
        ...providerOnly.reconciliation!,
        status: 'mismatch',
        provider_generations: 1,
        generation_difference: -1,
    };
    const unchecked = emptyDay('2026-11-04');
    unchecked.reconciliation = null;
    const tinyCost = emptyDay('2026-11-05');
    tinyCost.amounts.provider_cost = '0.000000000000000001';
    const freeSuccess = emptyDay('2026-11-06');
    freeSuccess.amounts.generations = 1;
    freeSuccess.reconciliation!.provider_generations = 1;
    const costDifference = emptyDay('2026-11-07');
    costDifference.reconciliation = {
        ...costDifference.reconciliation!,
        status: 'mismatch',
        provider_cost: '0.100000000',
        cost_difference: '-0.100000000000000000',
    };
    value.days = [
        emptyDay('2026-11-01'),
        failed,
        providerOnly,
        unchecked,
        tinyCost,
        freeSuccess,
        costDifference,
    ];

    const html = await render(value, emptyUsers);

    expect(html).not.toContain('2026-11-01');
    for (const date of [
        '2026-11-02',
        '2026-11-03',
        '2026-11-04',
        '2026-11-05',
        '2026-11-06',
        '2026-11-07',
    ]) {
        expect(html).toContain(date);
    }
    expect(html).toContain('-$0.1000');
    expect(html).toContain('Show empty days');
    expect(html).toContain('aria-pressed="false"');
});

test('shows an empty state when both sides have no activity', async () => {
    const value = report();
    value.days = [emptyDay('2026-11-01')];
    value.totals = value.days[0].amounts;

    const html = await render(value, emptyUsers);

    expect(html).toContain('No activity on either side for this date range.');
    expect(html).toContain('Show empty days');
    expect(html).not.toContain('2026-11-01');
});

test.each([
    ['-0.100000000000000000', '-$0.1000'],
    ['-0.000000000000000001', '-$0.000000000000000001'],
    ['0.000000000000000000', '$0.0000'],
    ['0.123456789123456789', '$0.123456789123456789'],
])('formats %s without losing precision', (value, expected) => {
    expect(formatCost(value)).toBe(expected);
});
