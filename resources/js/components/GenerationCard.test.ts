import { expect, test, vi } from 'vite-plus/test';
import { createSSRApp } from 'vue';
import { renderToString } from 'vue/server-renderer';
import type { Generation } from '@/types/generation';
import GenerationCard from './GenerationCard.vue';

vi.mock('@inertiajs/vue3', () => ({
    useHttp: () => ({ processing: false, post: vi.fn() }),
}));

function generation(overrides: Partial<Generation> = {}): Generation {
    return {
        id: 'preview-generation',
        prompt: 'A quiet garden in the morning',
        size: '1024x1024',
        quality: 'medium',
        reference_images: [],
        status: 'succeeded',
        submission_state: 'accepted',
        estimated_cost: '0.042500000000000000',
        actual_cost: '0.039125000000000000',
        image_url: 'https://example.com/garden.png',
        image_expired: false,
        error_message: null,
        can_resume: false,
        created_at: '2026-11-17T00:00:00Z',
        ...overrides,
    };
}

test('offers a named preview for an available image and preserves exact costs', async () => {
    const html = await renderToString(
        createSSRApp(GenerationCard, { generation: generation() }),
    );

    expect(html).toContain(
        'aria-label="Preview image: A quiet garden in the morning"',
    );
    expect(html).toContain('Complete');
    expect(html).toContain('$0.0425');
    expect(html).toContain('$0.039125');
});

test.each([
    {
        image_expired: true,
        image_url: 'https://example.com/expired.png',
        message: 'Image link expired',
    },
    {
        image_expired: false,
        image_url: null,
        status: 'failed' as const,
        message: 'You were not charged.',
    },
])(
    'does not offer a preview when the image is unavailable: $message',
    async ({ message, ...overrides }) => {
        const html = await renderToString(
            createSSRApp(GenerationCard, { generation: generation(overrides) }),
        );

        expect(html).not.toContain('aria-label="Preview image:');
        expect(html).toContain(message);
    },
);

test('keeps the entire prompt readable and escapes its markup', async () => {
    const prompt =
        '<script>alert("hello")</script> ' +
        'A garden with a winding path. '.repeat(20);

    const html = await renderToString(
        createSSRApp(GenerationCard, {
            generation: generation({ prompt, image_url: null }),
        }),
    );

    expect(html).toContain('View full prompt');
    expect(html).toContain(
        '&lt;script&gt;alert(&quot;hello&quot;)&lt;/script&gt;',
    );
    expect(html).toContain('A garden with a winding path. '.repeat(20).trim());
    expect(html).not.toContain('<script>');
});
