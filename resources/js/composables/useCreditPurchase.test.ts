import { http } from '@inertiajs/vue3';
import { afterEach, expect, test, vi } from 'vite-plus/test';
import { useCreditPurchase } from './useCreditPurchase';

const originalClient = http.getClient();
afterEach(() => http.setClient(originalClient));

const credits = {
    balance: '10.000000000000000000',
    reserved: '0.000000000000000000',
    available: '10.000000000000000000',
    has_debt: false,
};
const response = {
    status: 201,
    headers: {},
    data: JSON.stringify({ credits, entry: { id: 1 } }),
};

test('retries a lost purchase response using the same amount and identifier', async () => {
    const request = vi
        .fn()
        .mockRejectedValueOnce(new Error('Connection lost'))
        .mockResolvedValue(response);
    http.setClient({ request });
    const purchased = vi.fn();
    const form = useCreditPurchase(purchased);

    await form.purchase();
    expect(form.message.value).toContain('without adding credits twice');
    expect(form.pending.value).not.toBeNull();
    expect(purchased).not.toHaveBeenCalled();
    const first = JSON.parse(request.mock.calls[0][0].data);
    form.amount.value = '50';
    await form.purchase();

    expect(JSON.parse(request.mock.calls[1][0].data)).toEqual(first);
    expect(purchased).toHaveBeenCalledExactlyOnceWith(credits);
    expect(form.pending.value).toBeNull();
    expect(form.success.value).toContain('No real payment');

    await form.purchase();
    const next = JSON.parse(request.mock.calls[2][0].data);
    expect(next.amount).toBe('50');
    expect(next.client_request_id).not.toBe(first.client_request_id);
});

test('does not dispatch a second purchase while the first response is pending', async () => {
    let resolve!: (value: typeof response) => void;
    const request = vi.fn(
        () =>
            new Promise<typeof response>((done) => {
                resolve = done;
            }),
    );
    http.setClient({ request });
    const purchased = vi.fn();
    const form = useCreditPurchase(purchased);
    const first = form.purchase();
    await form.purchase();
    expect(request).toHaveBeenCalledOnce();
    resolve(response);
    await first;
    expect(purchased).toHaveBeenCalledOnce();
});

test('shows validation errors and allows selecting a valid package', async () => {
    const request = vi.fn().mockResolvedValueOnce({
        status: 422,
        headers: {},
        data: JSON.stringify({
            errors: { amount: ['Choose a credit package.'] },
        }),
    });
    http.setClient({ request });
    const purchased = vi.fn();
    const form = useCreditPurchase(purchased);
    form.amount.value = 'invalid';

    await form.purchase();
    expect(form.http.errors.amount).toBe('Choose a credit package.');
    expect(form.pending.value).toBeNull();
    expect(purchased).not.toHaveBeenCalled();
});
