<script setup lang="ts">
import { Head, Link, usePage } from '@inertiajs/vue3';
import { ArrowUpRight, Check, Sparkles } from '@lucide/vue';
import { computed } from 'vue';
import AppLogoIcon from '@/components/AppLogoIcon.vue';
import { Button } from '@/components/ui/button';
import { dashboard, login, register } from '@/routes';

const page = usePage();
const destination = computed(() =>
    page.props.currentTeam
        ? dashboard(page.props.currentTeam.slug)
        : register(),
);
</script>

<template>
    <div class="min-h-screen bg-background text-foreground">
        <Head title="Imagine it. Create it." />
        <header
            class="mx-auto flex max-w-6xl items-center justify-between px-6 py-7"
        >
            <Link
                href="/"
                class="flex items-center gap-2.5 text-xl font-semibold tracking-tight"
                ><AppLogoIcon class="size-7" />Prism</Link
            ><Link
                :href="page.props.auth.user ? destination : login()"
                class="text-sm font-medium"
                >{{ page.props.auth.user ? 'Open studio' : 'Log in' }}
                <span aria-hidden="true">↗</span></Link
            >
        </header>
        <main
            class="mx-auto grid max-w-6xl items-center gap-14 px-6 py-16 lg:min-h-[75vh] lg:grid-cols-2 lg:py-24"
        >
            <section>
                <p
                    class="mb-6 flex items-center gap-2 text-xs font-medium tracking-[0.18em] text-muted-foreground uppercase"
                >
                    <Sparkles class="size-4" />A little imagination goes a long
                    way
                </p>
                <h1
                    class="max-w-lg text-5xl leading-[1.08] font-semibold tracking-tight sm:text-6xl"
                >
                    Your next idea.<br /><span class="text-muted-foreground"
                        >In a new light.</span
                    >
                </h1>
                <p
                    class="mt-7 max-w-md text-lg leading-relaxed text-muted-foreground"
                >
                    Turn a prompt into an image. Choose your style, see the
                    estimated price, and follow every creation from start to
                    finish.
                </p>
                <Button as-child size="lg" class="mt-8 h-12 rounded-xl px-6"
                    ><Link :href="destination"
                        >{{
                            page.props.auth.user
                                ? 'Open your studio'
                                : 'Start creating'
                        }}<ArrowUpRight class="size-4" /></Link
                ></Button>
                <ul class="mt-8 space-y-3 text-sm text-muted-foreground">
                    <li class="flex gap-2">
                        <Check class="size-4" />Clear estimates before you
                        generate
                    </li>
                    <li class="flex gap-2">
                        <Check class="size-4" />Exact usage-based pricing, with
                        a 25% markup
                    </li>
                    <li class="flex gap-2">
                        <Check class="size-4" />Failed generations are always
                        free
                    </li>
                </ul>
            </section>
            <div
                class="relative flex aspect-square items-center justify-center overflow-hidden rounded-[2rem] border bg-linear-to-br from-indigo-100 via-violet-50 to-orange-100 dark:from-indigo-950 dark:via-slate-950 dark:to-orange-950"
                aria-hidden="true"
            >
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
                    A space for ideas to take shape.
                </p>
            </div>
        </main>
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
