<script setup lang="ts">
import { Head, Link, router, usePage, usePoll } from '@inertiajs/vue3';
import {
    ArrowDown,
    ArrowLeft,
    ArrowRight,
    LockKeyhole,
    Sparkles,
} from '@lucide/vue';
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
        <div
            class="mx-auto w-full max-w-[1440px] space-y-8 p-4 sm:p-6 lg:p-8 xl:p-10"
        >
            <header class="flex flex-wrap items-end justify-between gap-4">
                <div>
                    <p
                        class="mb-2 text-xs font-medium tracking-[0.2em] text-muted-foreground uppercase"
                    >
                        Your creative space
                    </p>
                    <h1
                        class="text-3xl font-semibold tracking-tight sm:text-4xl"
                    >
                        A little imagination.
                        <span class="text-primary">Endless possibilities.</span>
                    </h1>
                    <p class="mt-2 text-sm text-muted-foreground">
                        Bring an idea to life, one prompt at a time.
                    </p>
                </div>
                <span
                    v-if="hasActiveGenerations"
                    role="status"
                    class="flex items-center gap-2 text-xs text-muted-foreground"
                    ><span
                        class="size-2 rounded-full bg-emerald-500 motion-safe:animate-pulse"
                    />Updating your images</span
                >
            </header>
            <a
                href="#generations"
                class="flex w-fit items-center gap-2 rounded-lg border bg-card px-3 py-2.5 text-sm font-medium lg:hidden"
                >Your generations
                <span class="text-muted-foreground">{{
                    generations.meta.total
                }}</span
                ><ArrowDown class="size-4" aria-hidden="true"
            /></a>
            <div
                class="grid items-start gap-8 lg:grid-cols-[340px_minmax(0,1fr)] xl:grid-cols-[380px_minmax(0,1fr)]"
            >
                <GenerationForm
                    :configured="providerConfigured"
                    @created="showLatest"
                />
                <section
                    id="generations"
                    aria-labelledby="generations-heading"
                    tabindex="-1"
                    class="min-w-0 space-y-5"
                >
                    <div
                        class="flex flex-wrap items-center justify-between gap-3 border-b pb-4"
                    >
                        <h2
                            id="generations-heading"
                            class="flex items-center gap-2.5 font-semibold"
                        >
                            Your generations
                            <span
                                class="rounded-md bg-muted px-2 py-0.5 text-xs font-medium text-muted-foreground tabular-nums"
                                >{{ generations.meta.total }}</span
                            >
                        </h2>
                        <span
                            class="flex items-center gap-1.5 text-xs text-muted-foreground"
                            ><LockKeyhole
                                class="size-3"
                                aria-hidden="true"
                            />Only visible to you</span
                        >
                    </div>
                    <div
                        v-if="generations.data.length"
                        class="grid gap-5 sm:grid-cols-2 lg:grid-cols-1 xl:grid-cols-2"
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
                        class="relative flex min-h-[440px] flex-col items-center justify-center overflow-hidden rounded-2xl border bg-card p-6 text-center sm:p-10"
                    >
                        <div
                            aria-hidden="true"
                            class="pointer-events-none absolute inset-0 bg-[radial-gradient(var(--border)_1px,transparent_1px)] bg-size-[20px_20px] opacity-40"
                        />
                        <div class="relative flex flex-col items-center">
                            <div
                                class="mb-6 flex size-16 -rotate-6 items-center justify-center rounded-2xl border border-primary/15 bg-accent shadow-sm"
                            >
                                <Sparkles
                                    class="size-7 text-primary"
                                    aria-hidden="true"
                                />
                            </div>
                            <h3 class="text-xl font-semibold tracking-tight">
                                Your next idea belongs here.
                            </h3>
                            <p
                                class="mt-2 max-w-xs text-sm leading-relaxed text-muted-foreground"
                            >
                                Describe what you imagine and review the
                                estimate. Your images and their final costs will
                                appear here.
                            </p>
                            <a
                                href="#prompt"
                                class="mt-6 inline-flex min-h-11 items-center gap-2 rounded-lg bg-accent px-4 text-sm font-medium text-accent-foreground hover:bg-primary/15"
                                >Write your first prompt<ArrowRight
                                    class="size-4"
                                    aria-hidden="true"
                            /></a>
                            <p class="mt-5 text-xs text-muted-foreground">
                                A place, a product, a world of your own.
                            </p>
                        </div>
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
