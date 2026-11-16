import { HttpResponseError } from '@inertiajs/core';
import { useHttp } from '@inertiajs/vue3';
import { ref } from 'vue';
import { store } from '@/routes/credits';
import type { CreditBalance, CreditEntry } from '@/types/credits';

export function useCreditPurchase(
    onPurchased: (credits: CreditBalance) => void,
) {
    const amount = ref('10');
    const message = ref('');
    const success = ref('');
    const pending = ref<{ amount: string; client_request_id: string } | null>(
        null,
    );
    const http = useHttp<
        { amount: string; client_request_id: string },
        { credits: CreditBalance; entry: CreditEntry }
    >({ amount: '', client_request_id: '' });

    async function purchase(): Promise<void> {
        if (http.processing) return;
        pending.value ??= {
            amount: amount.value,
            client_request_id: crypto.randomUUID(),
        };
        message.value = '';
        success.value = '';
        try {
            await http
                .transform(() => ({ ...pending.value! }))
                .post(store.url(), {
                    onSuccess: (result) => {
                        pending.value = null;
                        success.value =
                            'Credits added. No real payment was made.';
                        onPurchased(result.credits);
                    },
                    onError: () => {
                        pending.value = null;
                    },
                });
        } catch (error) {
            if (
                error instanceof HttpResponseError &&
                error.response.status === 409
            ) {
                message.value =
                    'This purchase ID is already in use. Check your credit history before starting another purchase.';
            } else {
                message.value =
                    'We could not confirm the purchase. Retry to check the same purchase without adding credits twice.';
            }
        }
    }

    return { amount, pending, http, message, success, purchase };
}
