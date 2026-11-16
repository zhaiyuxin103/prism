import { http } from '@inertiajs/vue3';
import { afterEach, beforeEach, expect, test, vi } from 'vite-plus/test';
import { effectScope, nextTick } from 'vue';
import { useGenerationForm } from './useGenerationForm';

const scopes: ReturnType<typeof effectScope>[] = [];
const originalClient = http.getClient();

beforeEach(() => {
    vi.useFakeTimers();
});

afterEach(() => {
    scopes.forEach((scope) => scope.stop());
    scopes.length = 0;
    http.setClient(originalClient);
    vi.useRealTimers();
});

async function prepareForm(status: number, body: unknown) {
    const request = vi
        .fn()
        .mockResolvedValueOnce({
            status: 200,
            headers: {},
            data: JSON.stringify({
                estimated_cost: '0.10',
                maximum_cost: '0.15',
                expires_at: '2030-01-01T00:00:00Z',
                quote: 'signed-quote',
            }),
        })
        .mockResolvedValueOnce({
            status,
            headers: {},
            data: typeof body === 'string' ? body : JSON.stringify(body),
        });
    http.setClient({ request });
    const created = vi.fn();
    const scope = effectScope();
    scopes.push(scope);
    const form = scope.run(() => useGenerationForm(() => true, created))!;
    form.fields.prompt = 'A quiet lake';
    form.fields.references = 'https://example.com/lake.png';
    await nextTick();
    await form.getEstimate();
    return { form, request, created };
}

test.each([
    'Your estimate expired. Please refresh it.',
    'Prices changed. Please get a fresh estimate.',
])('preserves inputs and exposes quote validation: %s', async (message) => {
    const { form, created } = await prepareForm(422, {
        errors: { quote: [message] },
    });

    await form.generate();
    await nextTick();

    expect(form.fields.prompt).toBe('A quiet lake');
    expect(form.fields.references).toBe('https://example.com/lake.png');
    expect(form.errors.value.quote).toBe(message);
    expect(form.quote.value).toBeNull();
    expect(created).not.toHaveBeenCalled();
});

test('preserves field errors after a rejected submission', async () => {
    const { form, created } = await prepareForm(422, {
        errors: { prompt: ['The prompt is too long.'] },
    });

    await form.generate();
    await nextTick();

    expect(form.errors.value.prompt).toBe('The prompt is too long.');
    expect(form.fields.prompt).toBe('A quiet lake');
    expect(created).not.toHaveBeenCalled();
});

test.each([409, 503])(
    'displays the server message for HTTP %s and retains the submission identity',
    async (status) => {
        const { form, request, created } = await prepareForm(status, {
            message: 'Image generation is temporarily unavailable.',
        });

        await form.generate();
        expect(form.message.value).toBe(
            'Image generation is temporarily unavailable.',
        );
        expect(form.fields.prompt).toBe('A quiet lake');
        expect(created).not.toHaveBeenCalled();
        const firstSubmission = JSON.parse(request.mock.calls[1][0].data);
        request.mockResolvedValueOnce({
            status: 202,
            headers: {},
            data: '{"data":{"id":"generation-1"}}',
        });
        await form.generate();

        expect(
            JSON.parse(request.mock.calls[2][0].data).client_request_id,
        ).toBe(firstSubmission.client_request_id);
        expect(created).toHaveBeenCalledOnce();
        expect(form.fields.prompt).toBe('');
        expect(form.fields.references).toBe('');
    },
);

test('shows the decoded HTTP error message', async () => {
    const { form, created } = await prepareForm(503, {
        message: 'The image provider is temporarily unreachable.',
    });

    await form.generate();

    expect(form.message.value).toBe(
        'The image provider is temporarily unreachable.',
    );
    expect(form.fields.prompt).toBe('A quiet lake');
    expect(created).not.toHaveBeenCalled();
});

test('keeps inputs when a proxy returns a non-JSON error', async () => {
    const { form, created } = await prepareForm(
        503,
        '<html>Service unavailable</html>',
    );

    await form.generate();

    expect(form.message.value).toBe(
        'The server returned an unexpected response. Please try again.',
    );
    expect(form.fields.prompt).toBe('A quiet lake');
    expect(created).not.toHaveBeenCalled();
});

test('requires a new confirmation after refreshing an invalid quote', async () => {
    const { form, request, created } = await prepareForm(422, {
        errors: { quote: ['Prices changed.'] },
    });
    await form.generate();
    request.mockResolvedValueOnce({
        status: 200,
        headers: {},
        data: JSON.stringify({
            estimated_cost: '0.20',
            maximum_cost: '0.30',
            expires_at: '2030-01-01T00:00:00Z',
            quote: 'new-quote',
        }),
    });

    await form.getEstimate();

    expect(form.quote.value?.estimated_cost).toBe('0.20');
    expect(form.errors.value.quote).toBeUndefined();
    expect(form.fields.prompt).toBe('A quiet lake');
    expect(created).not.toHaveBeenCalled();
    expect(request).toHaveBeenCalledTimes(3);
});

test('invalidates the upper estimate when image settings change', async () => {
    const { form } = await prepareForm(202, { data: { id: 'generation-1' } });
    expect(form.quote.value?.maximum_cost).toBe('0.15');

    form.fields.quality = 'high';
    await nextTick();

    expect(form.quote.value).toBeNull();
});
