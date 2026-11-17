import { expect, test } from 'vite-plus/test';
import { createSSRApp } from 'vue';
import { renderToString } from 'vue/server-renderer';
import DatePicker from './DatePicker.vue';

test('renders the report date as a named required input with associated validation', async () => {
    const html = await renderToString(
        createSSRApp(DatePicker, {
            id: 'start_date',
            name: 'start_date',
            label: 'From (UTC)',
            defaultValue: '2024-02-29',
            required: true,
            'aria-invalid': true,
            'aria-describedby': 'start_date-error',
        }),
    );
    const input = html.match(/<input\b[^>]*>/)?.[0];
    expect(input).toContain('name="start_date"');
    expect(input).toContain('value="2024-02-29"');
    expect(input).toContain('required');
    expect(input).toContain('aria-invalid="true"');
    expect(input).toContain('aria-describedby="start_date-error"');
    expect(html).toContain('Choose From (UTC)');
});

test('disables both date entry and calendar opening when disabled', async () => {
    const html = await renderToString(
        createSSRApp(DatePicker, {
            id: 'end_date',
            name: 'end_date',
            label: 'Through (UTC)',
            disabled: true,
        }),
    );
    expect(html).toMatch(/<input\b[^>]*\bdisabled/);
    expect(html).toMatch(/<button\b[^>]*\bdisabled/);
});
