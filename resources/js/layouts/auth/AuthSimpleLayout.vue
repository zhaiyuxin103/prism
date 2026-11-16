<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import AppLogoIcon from '@/components/AppLogoIcon.vue';
import { home } from '@/routes';

defineProps<{
    title?: string;
    description?: string;
}>();
</script>

<template>
    <div
        class="flex min-h-svh flex-col items-center justify-center gap-6 bg-background bg-[radial-gradient(ellipse_at_top,var(--accent),transparent_65%)] p-5 md:p-10"
    >
        <div
            class="w-full max-w-md rounded-2xl border bg-card p-6 shadow-sm sm:p-9"
        >
            <div class="flex flex-col gap-8">
                <div class="flex flex-col items-center gap-4">
                    <Link
                        :href="home()"
                        class="flex items-center gap-2.5 rounded-lg text-xl font-semibold tracking-tight"
                    >
                        <div
                            class="flex size-10 items-center justify-center rounded-xl bg-primary text-primary-foreground"
                        >
                            <AppLogoIcon class="size-6" />
                        </div>
                        <span>Prism</span>
                    </Link>
                    <div class="space-y-2 text-center">
                        <h1 class="text-2xl font-semibold tracking-tight">
                            {{ title }}
                        </h1>
                        <p
                            class="text-center text-sm leading-relaxed text-muted-foreground"
                        >
                            {{ description }}
                        </p>
                    </div>
                </div>
                <slot />
            </div>
        </div>
    </div>
</template>
