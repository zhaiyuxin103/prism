<script setup lang="ts">
import { Head, Link, router, usePage, usePoll } from '@inertiajs/vue3';
import { ArrowLeft, ArrowRight, Sparkles } from '@lucide/vue';
import { watch } from 'vue';
import GenerationCard from '@/components/GenerationCard.vue';
import GenerationForm from '@/components/GenerationForm.vue';
import PendingInvitationsModal from '@/components/PendingInvitationsModal.vue';
import { Button } from '@/components/ui/button';
import { dashboard } from '@/routes';
import type { DashboardInvitation, Team } from '@/types';
import type { GenerationPage } from '@/types/generation';

const props = defineProps<{
    pendingInvitations?: DashboardInvitation[];
    generations: GenerationPage;
    hasActiveGenerations: boolean;
    hasPausedGenerations: boolean;
    providerConfigured: boolean;
}>();
const page = usePage();
const generationProps = [
    'generations',
    'hasActiveGenerations',
    'hasPausedGenerations',
];
const { start, stop } = usePoll(
    3000,
    { only: generationProps },
    { autoStart: false },
);
const { start: startPaused, stop: stopPaused } = usePoll(
    30000,
    { only: generationProps },
    { autoStart: false },
);
watch(
    () => [props.hasActiveGenerations, props.hasPausedGenerations],
    ([active, paused]) => {
        if (active) {
            start();
        } else {
            stop();
        }
        if (!active && paused) {
            startPaused();
        } else {
            stopPaused();
        }
    },
    { immediate: true },
);
function refresh(): void {
    router.reload({ only: generationProps });
}
function showLatest(): void {
    if (page.props.currentTeam) {
        router.get(
            dashboard.url(page.props.currentTeam.slug),
            {},
            { preserveState: true, preserveScroll: true },
        );
    }
}
defineOptions({
    layout: (props: { currentTeam?: Team | null }) => ({
        breadcrumbs: [
            {
                title: 'Studio',
                href: props.currentTeam
                    ? dashboard(props.currentTeam.slug)
                    : '/',
            },
        ],
    }),
});
</script>

<template>
    <div class="flex flex-1 flex-col">
        <Head title="Studio" />
        <PendingInvitationsModal
            v-if="pendingInvitations && pendingInvitations.length > 0"
            :invitations="pendingInvitations"
        />
        <div class="mx-auto w-full max-w-7xl space-y-8 p-5 lg:p-8">
            <header class="flex flex-wrap items-end justify-between gap-4">
                <div>
                    <p
                        class="mb-2 text-xs font-medium tracking-[0.2em] text-muted-foreground uppercase"
                    >
                        Prism / Your creative space
                    </p>
                    <h1 class="text-3xl font-semibold tracking-tight">
                        From a thought to an image.
                    </h1>
                    <p class="mt-2 text-sm text-muted-foreground">
                        Make something you imagined. Know exactly what it costs.
                    </p>
                </div>
                <span
                    v-if="hasActiveGenerations"
                    role="status"
                    class="flex items-center gap-2 text-xs text-muted-foreground"
                    ><span
                        class="size-2 animate-pulse rounded-full bg-emerald-500"
                    />Updating your images</span
                >
            </header>
            <div
                class="grid items-start gap-8 lg:grid-cols-[360px_minmax(0,1fr)] xl:grid-cols-[390px_minmax(0,1fr)]"
            >
                <GenerationForm
                    :configured="providerConfigured"
                    @created="showLatest"
                />
                <section class="min-w-0 space-y-5">
                    <div class="flex items-center justify-between">
                        <h2 class="font-semibold">
                            Your generations
                            <span
                                class="ml-2 text-sm font-normal text-muted-foreground"
                                >{{ generations.meta.total }}</span
                            >
                        </h2>
                        <span class="text-xs text-muted-foreground"
                            >Only visible to you</span
                        >
                    </div>
                    <div
                        v-if="generations.data.length"
                        class="grid gap-5 sm:grid-cols-2"
                    >
                        <GenerationCard
                            v-for="generation in generations.data"
                            :key="generation.id"
                            :generation="generation"
                            @resumed="refresh"
                        />
                    </div>
                    <div
                        v-else
                        class="flex min-h-96 flex-col items-center justify-center rounded-2xl border border-dashed bg-muted/20 p-10 text-center"
                    >
                        <div
                            class="mb-5 flex size-14 items-center justify-center rounded-2xl border bg-background"
                        >
                            <Sparkles class="size-6 text-muted-foreground" />
                        </div>
                        <h3 class="font-medium">A blank canvas, for now.</h3>
                        <p
                            class="mt-2 max-w-xs text-sm leading-relaxed text-muted-foreground"
                        >
                            Start with a prompt. Your images, progress, and
                            final costs will appear here.
                        </p>
                    </div>
                    <nav
                        v-if="generations.meta.last_page > 1"
                        aria-label="Generation history pages"
                        class="flex items-center justify-between gap-3 border-t pt-5"
                    >
                        <Button
                            variant="outline"
                            size="sm"
                            :disabled="!generations.links.prev"
                            as-child
                            ><Link
                                v-if="generations.links.prev"
                                :href="generations.links.prev"
                                preserve-scroll
                                ><ArrowLeft class="size-4" />Previous</Link
                            ><span v-else>Previous</span></Button
                        >
                        <span class="text-xs text-muted-foreground"
                            >{{ generations.meta.current_page }} /
                            {{ generations.meta.last_page }}</span
                        >
                        <Button
                            variant="outline"
                            size="sm"
                            :disabled="!generations.links.next"
                            as-child
                            ><Link
                                v-if="generations.links.next"
                                :href="generations.links.next"
                                preserve-scroll
                                >Next<ArrowRight class="size-4" /></Link
                            ><span v-else>Next</span></Button
                        >
                    </nav>
                </section>
            </div>
        </div>
    </div>
</template>
