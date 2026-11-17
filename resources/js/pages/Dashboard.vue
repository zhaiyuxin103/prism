<script setup lang="ts">
import { Head, Link, router, usePage, usePoll } from '@inertiajs/vue3';
import {
    ArrowDown,
    ArrowLeft,
    ArrowRight,
    LockKeyhole,
    Sparkles,
} from '@lucide/vue';
import { ref, watch } from 'vue';
import CreditBalanceCard from '@/components/CreditBalance.vue';
import GenerationCard from '@/components/GenerationCard.vue';
import GenerationForm from '@/components/GenerationForm.vue';
import PendingInvitationsModal from '@/components/PendingInvitationsModal.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import {
    Empty,
    EmptyHeader,
    EmptyMedia,
    EmptyTitle,
    EmptyDescription,
    EmptyContent,
} from '@/components/ui/empty';
import { dashboard } from '@/routes';
import type { DashboardInvitation, Team } from '@/types';
import type { CreditBalance } from '@/types/credits';
import type { GenerationPage } from '@/types/generation';

const props = defineProps<{
    pendingInvitations?: DashboardInvitation[];
    generations: GenerationPage;
    hasActiveGenerations: boolean;
    hasPausedGenerations: boolean;
    providerConfigured: boolean;
    credits: CreditBalance;
    creditPackages: string[];
}>();
const page = usePage();
const balance = ref(props.credits);
watch(
    () => props.credits,
    (value) => {
        balance.value = value;
    },
);
function purchased(credits: CreditBalance): void {
    balance.value = credits;
    router.reload({ only: ['credits'] });
}
const generationProps = [
    'generations',
    'credits',
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
                        class="size-2 rounded-full bg-success motion-safe:animate-pulse"
                    />Updating your images</span
                >
            </header>
            <CreditBalanceCard
                :credits="balance"
                :packages="creditPackages"
                show-history
                @purchased="purchased"
            />
            <Button variant="outline" as-child class="w-fit lg:hidden"
                ><a href="#generations"
                    >Your generations
                    <Badge variant="secondary">{{
                        generations.meta.total
                    }}</Badge
                    ><ArrowDown aria-hidden="true" /></a
            ></Button>
            <div
                class="grid items-start gap-8 lg:grid-cols-[340px_minmax(0,1fr)] xl:grid-cols-[380px_minmax(0,1fr)]"
            >
                <GenerationForm
                    :configured="providerConfigured"
                    :available-credits="balance.available"
                    @balance-changed="router.reload({ only: ['credits'] })"
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
                    <Card v-else>
                        <CardContent>
                            <Empty class="min-h-[440px]">
                                <EmptyHeader>
                                    <EmptyMedia variant="icon"
                                        ><Sparkles aria-hidden="true"
                                    /></EmptyMedia>
                                    <EmptyTitle
                                        >Your next idea belongs
                                        here.</EmptyTitle
                                    >
                                    <EmptyDescription
                                        >Describe what you imagine and review
                                        the estimate. Your images and their
                                        final costs will appear
                                        here.</EmptyDescription
                                    >
                                </EmptyHeader>
                                <EmptyContent>
                                    <Button variant="secondary" as-child
                                        ><a href="#prompt"
                                            >Write your first prompt<ArrowRight
                                                aria-hidden="true" /></a
                                    ></Button>
                                    <p class="text-xs text-muted-foreground">
                                        A place, a product, a world of your own.
                                    </p>
                                </EmptyContent>
                            </Empty>
                        </CardContent>
                    </Card>
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
