<script setup lang="ts">
import { useHttp } from '@inertiajs/vue3';
import { Expand, ImageOff, LoaderCircle, Sparkles } from '@lucide/vue';
import { computed, ref, watch } from 'vue';
import { Alert, AlertDescription } from '@/components/ui/alert';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardHeader,
    CardContent,
    CardFooter,
} from '@/components/ui/card';
import {
    Collapsible,
    CollapsibleTrigger,
    CollapsibleContent,
} from '@/components/ui/collapsible';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogHeader,
    DialogTitle,
    DialogTrigger,
} from '@/components/ui/dialog';
import { Empty, EmptyHeader, EmptyDescription } from '@/components/ui/empty';
import { Separator } from '@/components/ui/separator';
import { resume } from '@/routes/generations';
import { formatCost } from '@/types/generation';
import type { Generation } from '@/types/generation';

const props = defineProps<{ generation: Generation }>();
const emit = defineEmits<{ resumed: [] }>();
const brokenImage = ref(false);
const error = ref('');
const http = useHttp({});
const hasImage = computed(
    () =>
        !!props.generation.image_url &&
        !props.generation.image_expired &&
        !brokenImage.value,
);
watch(
    () => props.generation.image_url,
    () => {
        brokenImage.value = false;
    },
);
const pending = computed(() =>
    ['queued', 'processing'].includes(props.generation.status),
);
const label = computed(() => {
    if (props.generation.can_resume) return 'Needs a check';
    if (props.generation.submission_state === 'uncertain')
        return 'Confirming submission';
    if (props.generation.submission_state === 'pending') return 'Submitting';
    return {
        queued: 'In queue',
        processing: 'Creating',
        succeeded: 'Complete',
        failed: 'Failed',
    }[props.generation.status];
});
async function resumeChecking(): Promise<void> {
    error.value = '';
    try {
        await http.post(resume.url(props.generation.id));
        emit('resumed');
    } catch {
        error.value = 'Could not resume checking. Please try again.';
    }
}
const promptOpen = ref(false);
</script>

<template>
    <Card role="article" class="min-w-0 gap-0 overflow-hidden py-0">
        <div
            class="relative flex aspect-square items-center justify-center overflow-hidden bg-muted/40"
        >
            <Dialog v-if="hasImage">
                <DialogTrigger as-child>
                    <Button
                        variant="ghost"
                        type="button"
                        class="group relative size-full cursor-zoom-in rounded-none p-0"
                        :aria-label="`Preview image: ${generation.prompt}`"
                    >
                        <img
                            :src="generation.image_url!"
                            :alt="generation.prompt"
                            loading="lazy"
                            referrerpolicy="no-referrer"
                            class="size-full object-contain"
                            @error="brokenImage = true"
                        />
                        <Badge
                            variant="secondary"
                            class="absolute right-3 bottom-3"
                            ><Expand aria-hidden="true" />Preview</Badge
                        >
                    </Button>
                </DialogTrigger>
                <DialogContent
                    class="max-h-[90svh] overflow-y-auto sm:max-w-4xl"
                >
                    <DialogHeader class="pr-6">
                        <DialogTitle>Image preview</DialogTitle>
                        <DialogDescription
                            >{{ generation.size.replace('x', ' × ') }} pixels ·
                            {{ generation.quality }} quality</DialogDescription
                        >
                    </DialogHeader>
                    <img
                        :src="generation.image_url!"
                        :alt="generation.prompt"
                        referrerpolicy="no-referrer"
                        class="max-h-[60svh] w-full rounded-lg bg-muted object-contain"
                        @error="brokenImage = true"
                    />
                    <p class="text-sm leading-relaxed wrap-anywhere">
                        {{ generation.prompt }}
                    </p>
                    <p class="text-xs text-muted-foreground">
                        Images are available for 24 hours after generation.
                    </p>
                </DialogContent>
            </Dialog>
            <Empty v-else class="max-w-64 p-6 md:p-6"
                ><EmptyHeader>
                    <LoaderCircle
                        v-if="pending && !generation.can_resume"
                        class="size-7 motion-safe:animate-spin"
                    />
                    <ImageOff
                        v-else-if="
                            generation.status === 'failed' ||
                            generation.image_expired ||
                            brokenImage
                        "
                        class="size-7"
                    />
                    <Sparkles v-else class="size-7" />
                    <EmptyDescription>
                        {{
                            generation.status === 'failed'
                                ? 'No image generated'
                                : generation.image_expired
                                  ? 'Image link expired'
                                  : brokenImage
                                    ? 'Image is unavailable'
                                    : generation.can_resume
                                      ? 'Check the original request to continue'
                                      : generation.status === 'processing'
                                        ? 'Your image is taking shape'
                                        : 'Your image is on its way'
                        }}
                    </EmptyDescription>
                </EmptyHeader></Empty
            >
            <div
                class="pointer-events-none absolute top-3 left-3 rounded-full bg-background/95"
            >
                <Badge
                    :variant="
                        generation.status === 'succeeded'
                            ? 'success'
                            : generation.status === 'failed'
                              ? 'destructive'
                              : 'secondary'
                    "
                    >{{ label }}</Badge
                >
            </div>
        </div>
        <CardHeader class="py-6">
            <Collapsible v-model:open="promptOpen" class="flex flex-col gap-2">
                <p
                    v-if="!promptOpen"
                    class="line-clamp-2 text-sm leading-relaxed wrap-anywhere"
                >
                    {{ generation.prompt }}
                </p>
                <CollapsibleTrigger as-child>
                    <Button
                        type="button"
                        variant="link"
                        size="sm"
                        class="justify-start px-0"
                        >{{
                            promptOpen ? 'Hide full prompt' : 'View full prompt'
                        }}</Button
                    >
                </CollapsibleTrigger>
                <CollapsibleContent force-mount v-show="promptOpen">
                    <p
                        class="text-sm leading-relaxed wrap-anywhere whitespace-pre-wrap"
                    >
                        {{ generation.prompt }}
                    </p>
                </CollapsibleContent>
            </Collapsible>
            <div class="flex items-center gap-2 text-xs text-muted-foreground">
                <span class="capitalize">{{ generation.quality }}</span
                ><span>·</span
                ><span>{{ generation.size.replace('x', ' × ') }}</span>
            </div>
        </CardHeader>
        <CardContent class="flex flex-col gap-4">
            <Separator />
            <dl class="grid grid-cols-2 gap-3 text-xs">
                <div>
                    <dt class="text-muted-foreground">Estimated</dt>
                    <dd class="mt-1 font-mono break-all tabular-nums">
                        {{ formatCost(generation.estimated_cost) }}
                    </dd>
                </div>
                <div>
                    <dt class="text-muted-foreground">Actual · USD</dt>
                    <dd
                        class="mt-1 font-mono font-semibold break-all tabular-nums"
                    >
                        {{
                            generation.actual_cost === null
                                ? '—'
                                : formatCost(generation.actual_cost)
                        }}
                    </dd>
                </div>
                <div
                    v-if="generation.reserved_cost"
                    class="flex items-center justify-between gap-2 text-xs"
                >
                    <dt class="text-muted-foreground">Reserved credits</dt>
                    <dd class="font-mono">
                        {{ formatCost(generation.reserved_cost) }}
                    </dd>
                </div>
            </dl>
            <Alert v-if="generation.error_message" variant="destructive"
                ><AlertDescription class="wrap-anywhere">{{
                    generation.error_message
                }}</AlertDescription></Alert
            >
            <p
                v-if="generation.status === 'failed'"
                class="text-xs font-medium"
            >
                You were not charged.
            </p>
            <p
                v-if="generation.image_expired"
                class="text-xs text-muted-foreground"
            >
                Images are available for 24 hours. Your generation and cost
                remain recorded.
            </p>
            <Button
                v-if="generation.can_resume"
                variant="outline"
                size="sm"
                :disabled="http.processing"
                @click="resumeChecking"
                >{{ http.processing ? 'Resuming…' : 'Resume checking' }}</Button
            >
            <p v-if="error" role="alert" class="text-xs text-destructive">
                {{ error }}
            </p>
        </CardContent>
        <CardFooter class="py-6">
            <time
                :datetime="generation.created_at"
                class="block text-xs text-muted-foreground"
                >{{ new Date(generation.created_at).toLocaleString() }}</time
            >
        </CardFooter>
    </Card>
</template>
