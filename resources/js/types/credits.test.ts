import { expect, test } from 'vite-plus/test';
import { canAfford, formatCredit } from './credits';

test.each([
    ['0', '0.000000000000000001', false],
    ['0.149999999999999999', '0.15', false],
    ['0.150000000000000000', '0.15', true],
    [
        '9007199254740992.000000000000000000',
        '9007199254740992.000000000000000001',
        false,
    ],
    ['-0.000000000000000001', '0', false],
    ['10', '0.086268750000000000', true],
])(
    'compares %s against %s without floating point rounding',
    (available, required, expected) => {
        expect(canAfford(available, required)).toBe(expected);
    },
);

test('formats credit debt with a leading minus sign', () => {
    expect(formatCredit('-0.471556250000000000')).toBe('-$0.47155625');
});
