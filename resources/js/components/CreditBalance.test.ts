import { expect, test } from 'vite-plus/test';
import { createSSRApp } from 'vue';
import { renderToString } from 'vue/server-renderer';
import CreditBalance from './CreditBalance.vue';

test('renders available credits separately from the balance and reserved funds', async () => {
    const html = await renderToString(
        createSSRApp(CreditBalance, {
            credits: {
                balance: '10.000000000000000000',
                reserved: '0.086268750000000000',
                available: '9.913731250000000000',
                has_debt: false,
            },
            packages: ['10', '25', '50'],
        }),
    );
    expect(html).toContain('$9.91373125');
    expect(html).toContain('Balance $10.0000 · Reserved $0.08626875');
    expect(html).toContain(
        'Simulated purchase. No card details or real payment required.',
    );
    expect(html).toContain('Buy credits');
    expect(html).toContain('Paused checks keep their');
});

test('explains why a negative available balance needs replenishing', async () => {
    const html = await renderToString(
        createSSRApp(CreditBalance, {
            credits: {
                balance: '-0.471556250000000000',
                reserved: '0.000000000000000000',
                available: '-0.471556250000000000',
                has_debt: true,
            },
            packages: ['10', '25', '50'],
        }),
    );
    expect(html).toContain('-$0.47155625');
    expect(html).toContain('Add credits to cover the');
    expect(html).toContain('difference before generating again.');
});
