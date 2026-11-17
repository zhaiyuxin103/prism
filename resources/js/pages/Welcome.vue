<script setup lang="ts">
import { Head, Link, usePage } from '@inertiajs/vue3';
import { ArrowUpRight, Check, Sparkles } from '@lucide/vue';
import { computed } from 'vue';
import AppLogoIcon from '@/components/AppLogoIcon.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { dashboard, home, login, register } from '@/routes';

const page = usePage();
const destination = computed(() =>
    page.props.currentTeam
        ? dashboard(page.props.currentTeam.slug)
        : register(),
);
</script>

<template>
    <div class="min-h-screen overflow-x-clip bg-background text-foreground">
        <Head title="Imagine it. Create it." />
        <header
            class="mx-auto flex max-w-6xl items-center justify-between gap-6 border-b px-5 py-6 sm:px-8"
        >
            <Link
                :href="home()"
                class="flex items-center gap-2.5 text-xl font-semibold tracking-tight"
                ><AppLogoIcon class="size-7" />Prism</Link
            ><Button variant="outline" as-child
                ><Link :href="page.props.auth.user ? destination : login()"
                    >{{ page.props.auth.user ? 'Open studio' : 'Log in' }}
                    <ArrowUpRight aria-hidden="true" /></Link
            ></Button>
        </header>
        <main
            class="mx-auto grid max-w-6xl items-center gap-12 px-5 py-14 sm:px-8 sm:py-20 lg:min-h-[75vh] lg:grid-cols-[1.1fr_1fr] lg:gap-16 lg:py-24"
        >
            <section>
                <Badge variant="secondary" class="mb-6"
                    ><Sparkles />A space for your imagination</Badge
                >
                <h1
                    class="max-w-lg text-5xl leading-[1.08] font-semibold tracking-[-0.045em] sm:text-6xl lg:text-7xl"
                >
                    Your next idea.<br /><span class="text-primary"
                        >In a new light.</span
                    >
                </h1>
                <p
                    class="mt-6 max-w-md text-base leading-relaxed text-muted-foreground sm:text-lg"
                >
                    Turn a prompt into an image. Choose your style, see the
                    estimated price, and follow every creation from start to
                    finish.
                </p>
                <div class="mt-8 flex flex-wrap items-center gap-4">
                    <Button as-child size="lg"
                        ><Link :href="destination"
                            >{{
                                page.props.auth.user
                                    ? 'Open your studio'
                                    : 'Start creating'
                            }}<ArrowUpRight class="size-4" /></Link></Button
                    ><Button variant="link" as-child
                        ><a href="#how-it-works">See how it works</a></Button
                    >
                </div>
                <ul
                    class="mt-8 space-y-2.5 text-xs text-muted-foreground sm:text-sm"
                >
                    <li class="flex gap-2">
                        <Check
                            class="size-4 shrink-0 text-primary"
                            aria-hidden="true"
                        />Clear estimates before you generate
                    </li>
                    <li class="flex gap-2">
                        <Check
                            class="size-4 shrink-0 text-primary"
                            aria-hidden="true"
                        />Exact usage-based pricing, with a 25% markup
                    </li>
                    <li class="flex gap-2">
                        <Check
                            class="size-4 shrink-0 text-primary"
                            aria-hidden="true"
                        />Failed generations are always free
                    </li>
                </ul>
            </section>
            <div
                class="relative flex aspect-square items-center justify-center overflow-hidden rounded-[2rem] border border-primary/10 bg-linear-to-br from-indigo-100 via-violet-50 to-orange-100 dark:from-indigo-950 dark:via-slate-950 dark:to-orange-950"
                aria-hidden="true"
            >
                <div
                    class="absolute top-6 right-6 left-6 flex items-center justify-between text-[10px] font-medium tracking-[0.2em] text-slate-600 uppercase dark:text-slate-300"
                >
                    <span>Prism studio</span><span>Ideas, refracted.</span>
                </div>
                <div
                    class="absolute size-72 rounded-full bg-orange-200/60 blur-3xl dark:bg-orange-800/30"
                />
                <svg
                    viewBox="0 0 400 400"
                    class="relative w-4/5 drop-shadow-2xl"
                >
                    <defs>
                        <linearGradient
                            id="prismFace"
                            x1="0"
                            y1="0"
                            x2="1"
                            y2="1"
                        >
                            <stop stop-color="#fff" stop-opacity="0.85" />
                            <stop
                                offset="1"
                                stop-color="#a5b4fc"
                                stop-opacity="0.25"
                            />
                        </linearGradient>
                        <linearGradient id="prismEdge">
                            <stop stop-color="#818cf8" />
                            <stop offset="0.5" stop-color="#f0abfc" />
                            <stop offset="1" stop-color="#fdba74" />
                        </linearGradient>
                    </defs>
                    <path
                        d="M200 60 345 315H55Z"
                        fill="url(#prismFace)"
                        stroke="white"
                        stroke-opacity="0.8"
                        stroke-width="2"
                    />
                    <path d="m200 60 35 255H55Z" fill="#fff" opacity="0.2" />
                    <path
                        d="m200 60 35 255h110Z"
                        fill="#818cf8"
                        opacity="0.2"
                    />
                    <path
                        d="m0 245 139-80 261 60"
                        fill="none"
                        stroke="url(#prismEdge)"
                        stroke-width="3"
                    />
                    <path
                        d="m139 165 261 90M139 165l261 120"
                        fill="none"
                        stroke="url(#prismEdge)"
                        stroke-width="2"
                        opacity="0.5"
                    />
                </svg>
                <p
                    class="absolute right-6 bottom-6 left-6 text-center text-xs tracking-wide text-slate-600 dark:text-slate-400"
                >
                    A different perspective changes everything.
                </p>
            </div>
        </main>
        <section
            id="how-it-works"
            aria-labelledby="how-heading"
            class="mx-auto max-w-6xl border-t px-5 py-12 sm:px-8 sm:py-16"
        >
            <div
                class="mb-8 flex flex-wrap items-baseline justify-between gap-3"
            >
                <h2
                    id="how-heading"
                    class="text-2xl font-semibold tracking-tight"
                >
                    From a spark to something you can see.
                </h2>
                <p class="text-sm text-muted-foreground">Three simple steps.</p>
            </div>
            <ol class="grid gap-8 sm:grid-cols-3">
                <li
                    v-for="step in [
                        {
                            number: '01',
                            title: 'Make it yours',
                            description:
                                'Describe your idea. Choose a format and quality, or add reference images to guide the result.',
                        },
                        {
                            number: '02',
                            title: 'Know the estimate',
                            description:
                                'Review the estimated price before generating. Your final cost follows actual usage.',
                        },
                        {
                            number: '03',
                            title: 'Watch it take shape',
                            description:
                                'Follow your image’s progress in your private studio, then open it for a closer look.',
                        },
                    ]"
                    :key="step.number"
                    class="flex gap-4"
                >
                    <span class="pt-1 font-mono text-xs text-primary">{{
                        step.number
                    }}</span>
                    <div>
                        <h3 class="mb-2 font-medium">{{ step.title }}</h3>
                        <p
                            class="text-sm leading-relaxed text-muted-foreground"
                        >
                            {{ step.description }}
                        </p>
                    </div>
                </li>
            </ol>
        </section>
        <footer
            class="mx-auto flex max-w-6xl flex-wrap justify-between gap-3 border-t px-6 py-6 text-xs text-muted-foreground"
        >
            <span>Prism · Powered by Pixelforge</span
            ><span
                >Assessment demo · Simulated images and costs, no real
                payments.</span
            >
        </footer>
    </div>
</template>
