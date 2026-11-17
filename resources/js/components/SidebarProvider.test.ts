import { expect, test, vi } from 'vite-plus/test';
import { createSSRApp, defineComponent, h } from 'vue';
import { renderToString } from 'vue/server-renderer';
import { SidebarProvider } from '@/components/ui/sidebar';
import { useSidebar } from '@/components/ui/sidebar/utils';

vi.mock('@vueuse/core', async (importOriginal) => {
    const actual = await importOriginal<typeof import('@vueuse/core')>();
    const mobileWindow = {
        matchMedia: () => ({
            matches: true,
            addEventListener: vi.fn(),
            removeEventListener: vi.fn(),
        }),
    } as unknown as Window;

    return {
        ...actual,
        useMediaQuery: (query: string, options = {}) =>
            actual.useMediaQuery(query, { ...options, window: mobileWindow }),
    };
});

test.each([true, false])(
    'keeps initial hydration on the server layout with defaultOpen=%s even on a mobile client',
    async (defaultOpen) => {
        const Probe = defineComponent({
            setup() {
                const { isMobile, open, openMobile } = useSidebar();
                return () =>
                    h('output', {
                        'data-layout': isMobile.value ? 'mobile' : 'desktop',
                        'data-open': open.value,
                        'data-mobile-open': openMobile.value,
                    });
            },
        });
        const html = await renderToString(
            createSSRApp({
                render: () =>
                    h(
                        SidebarProvider,
                        { defaultOpen },
                        { default: () => h(Probe) },
                    ),
            }),
        );

        expect(html).toContain('data-layout="desktop"');
        expect(html).toContain(`data-open="${defaultOpen}"`);
        expect(html).toContain('data-mobile-open="false"');
    },
);
