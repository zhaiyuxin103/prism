import { beforeEach, expect, test, vi } from 'vite-plus/test';
import { createSSRApp, ref } from 'vue';
import { renderToString } from 'vue/server-renderer';
import GenerationForm from './GenerationForm.vue';

const formState = vi.hoisted(() => ({ errors: {} as Record<string, string> }));

beforeEach(() => {
    formState.errors = {};
});

vi.mock('@/composables/useGenerationForm', () => ({
    useGenerationForm: () => ({
        fields: {
            prompt: 'A lake',
            size: '1536x1024',
            quality: 'high',
            references: '',
        },
        input: ref({ prompt: 'A lake' }),
        quote: ref({
            estimated_cost: '0.412562500000000000',
            maximum_cost: '0.467343750000000000',
        }),
        message: ref(''),
        estimateHttp: { processing: false },
        submitHttp: { processing: false },
        errors: ref(formState.errors),
        getEstimate: vi.fn(),
        generate: vi.fn(),
    }),
}));

test('shows both prices and explains that the upper estimate uses current rates', async () => {
    const html = await renderToString(
        createSSRApp(GenerationForm, { configured: true }),
    );

    expect(html).toContain('$0.4125625');
    expect(html).toContain('$0.46734375');
    expect(html).toContain('Upper estimate at current rates:');
    expect(html).toContain('Both estimates include a 25% markup.');
    expect(html).toContain('not a fixed spending cap');
});

test.each<{ name: string; errors: Record<string, string>; expected: string[] }>(
    [
        {
            name: 'unrelated errors',
            errors: {
                prompt: 'A prompt is required.',
                quote: 'Refresh the estimate.',
            },
            expected: [],
        },
        {
            name: 'mixed field and reference errors',
            errors: {
                prompt: 'A prompt is required.',
                reference_images: 'Use at most four images.',
                'reference_images.0': 'The first image URL is invalid.',
            },
            expected: [
                'Use at most four images.',
                'The first image URL is invalid.',
            ],
        },
    ],
)(
    'renders only reference error paragraphs for $name',
    async ({ errors, expected }) => {
        formState.errors = errors;

        const html = await renderToString(
            createSSRApp(GenerationForm, { configured: true }),
        );

        const paragraphs = Array.from(
            html.matchAll(
                /<p class="text-sm text-destructive"[^>]*>([\s\S]*?)<\/p>/g,
            ),
            (match) => match[1].trim(),
        );
        expect(paragraphs).toEqual(expected);
        expect(html.match(/A prompt is required\./g)).toHaveLength(1);
    },
);
