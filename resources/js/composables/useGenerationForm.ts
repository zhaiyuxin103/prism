import { HttpCancelledError, HttpResponseError } from '@inertiajs/core';
import { useHttp } from '@inertiajs/vue3';
import { computed, onScopeDispose, reactive, ref, watch } from 'vue';
import { estimate, store } from '@/routes/generations';
import type { GenerationInput } from '@/types/generation';

export function useGenerationForm(
    configured: () => boolean,
    onCreated: () => void,
) {
    type Quote = {
        estimated_cost: string;
        maximum_cost: string;
        expires_at: string;
        quote: string;
    };
    const fields = reactive({
        prompt: '',
        size: '1024x1024',
        quality: 'medium',
        references: '',
    });
    const input = computed<GenerationInput>(() => ({
        prompt: fields.prompt.trim(),
        size: fields.size,
        quality: fields.quality,
        reference_images: fields.references
            .split('\n')
            .map((url) => url.trim())
            .filter(Boolean),
    }));
    const quote = ref<Quote | null>(null);
    const message = ref('');
    const requestId = ref(crypto.randomUUID());
    const estimateHttp = useHttp<GenerationInput, Quote>({
        prompt: '',
        size: '',
        quality: '',
        reference_images: [],
    });
    const submitHttp = useHttp({
        ...input.value,
        client_request_id: '',
        quote: '',
    });
    let revision = 0;
    let timer: ReturnType<typeof setTimeout> | undefined;

    function errorMessage(error: unknown): string {
        if (error instanceof SyntaxError) {
            return 'The server returned an unexpected response. Please try again.';
        }
        if (error instanceof HttpResponseError) {
            try {
                const data: unknown = JSON.parse(error.response.data);
                if (
                    data &&
                    typeof data === 'object' &&
                    'message' in data &&
                    typeof data.message === 'string'
                ) {
                    return data.message;
                }
            } catch {
                // A proxy may return HTML instead of the application's JSON response.
            }
            return 'The server could not complete this request. Please try again.';
        }
        return 'We could not reach the server. Please try again.';
    }

    async function getEstimate(): Promise<void> {
        const currentRevision = revision;
        message.value = '';
        try {
            await estimateHttp
                .transform(() => input.value)
                .post(estimate.url(), {
                    onSuccess: (result) => {
                        if (currentRevision === revision) {
                            quote.value = result;
                            submitHttp.clearErrors('quote');
                        }
                    },
                });
        } catch (error) {
            if (
                currentRevision === revision &&
                !(error instanceof HttpCancelledError) &&
                !estimateHttp.hasErrors
            ) {
                message.value = errorMessage(error);
            }
        }
    }

    watch(
        input,
        () => {
            revision++;
            quote.value = null;
            message.value = '';
            requestId.value = crypto.randomUUID();
            estimateHttp.cancel();
            estimateHttp.clearErrors();
            submitHttp.clearErrors();
            clearTimeout(timer);
            if (input.value.prompt && configured()) {
                timer = setTimeout(() => void getEstimate(), 500);
            }
        },
        { deep: true },
    );

    onScopeDispose(() => {
        clearTimeout(timer);
        estimateHttp.cancel();
        submitHttp.cancel();
    });

    async function generate(): Promise<void> {
        if (!quote.value || submitHttp.processing) {
            return;
        }
        message.value = '';
        try {
            await submitHttp
                .transform(() => ({
                    ...input.value,
                    client_request_id: requestId.value,
                    quote: quote.value!.quote,
                }))
                .post(store.url(), {
                    onSuccess: () => {
                        onCreated();
                        fields.prompt = '';
                        fields.references = '';
                        quote.value = null;
                        requestId.value = crypto.randomUUID();
                    },
                    onError: (errors) => {
                        if (errors.quote) {
                            quote.value = null;
                        }
                    },
                });
        } catch (error) {
            message.value = errorMessage(error);
        }
    }

    const errors = computed(() => ({
        ...estimateHttp.errors,
        ...submitHttp.errors,
    }));

    return {
        fields,
        input,
        quote,
        message,
        estimateHttp,
        submitHttp,
        errors,
        getEstimate,
        generate,
    };
}
