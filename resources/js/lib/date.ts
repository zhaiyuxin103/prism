import { parseDate } from '@internationalized/date';
import type { CalendarDate } from '@internationalized/date';

export function parseCalendarDate(value: string): CalendarDate | undefined {
    if (!/^\d{4}-\d{2}-\d{2}$/.test(value)) {
        return undefined;
    }

    try {
        return parseDate(value);
    } catch {
        return undefined;
    }
}
