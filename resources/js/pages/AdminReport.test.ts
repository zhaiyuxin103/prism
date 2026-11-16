import { expect, test, vi } from 'vite-plus/test';
import { createSSRApp, defineComponent, h } from 'vue';
import { renderToString } from 'vue/server-renderer';
import type {
    AdminReport as Report,
    UserReportPage,
} from '@/types/admin-report';
import AdminReport from './AdminReport.vue';

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
