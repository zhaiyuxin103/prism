import { expect, test } from 'vite-plus/test';
import { parseCalendarDate } from './date';

test.each(['2026-11-17', '2024-02-29', '2026-01-01'])(
    'preserves the calendar day without timezone conversion for %s',
    (value) => {
        expect(parseCalendarDate(value)?.toString()).toBe(value);
    },
);

test.each([
    '',
    '2026-02-29',
    '2026-04-31',
    '2026-13-01',
    '2026-00-01',
    '2026-11-00',
    '11/17/2026',
    '2026-1-1',
    '2026-11-17T00:00:00Z',
])('rejects invalid or non-date-only input %s', (value) => {
    expect(parseCalendarDate(value)).toBeUndefined();
});
