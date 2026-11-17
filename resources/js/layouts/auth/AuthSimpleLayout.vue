<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import AppLogoIcon from '@/components/AppLogoIcon.vue';
import {
    Card,
    CardHeader,
    CardContent,
    CardDescription,
} from '@/components/ui/card';
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
        <Card class="w-full max-w-md">
            <CardHeader class="flex flex-col items-center gap-4">
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
                    <CardDescription class="text-center">
                        {{ description }}
                    </CardDescription>
                </div>
            </CardHeader>
            <CardContent class="flex flex-col gap-6"><slot /></CardContent>
        </Card>
    </div>
</template>
