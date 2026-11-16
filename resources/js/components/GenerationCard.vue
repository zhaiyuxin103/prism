<script setup lang="ts">
import { useHttp } from '@inertiajs/vue3';
import { ImageOff, LoaderCircle, Sparkles } from '@lucide/vue';
import { computed, ref } from 'vue';
import { Button } from '@/components/ui/button';
import { resume } from '@/routes/generations';
import { formatCost } from '@/types/generation';
import type { Generation } from '@/types/generation';

const props = defineProps<{ generation: Generation }>();
const emit = defineEmits<{ resumed: [] }>();
const brokenImage = ref(false);
const error = ref('');
const http = useHttp({});
const pending = computed(() =>
    ['queued', 'processing'].includes(props.generation.status),
);
const label = computed(() => {
    if (props.generation.can_resume) return 'Needs a check';
    if (props.generation.submission_state === 'uncertain')
        return 'Confirming submission';
    if (props.generation.submission_state === 'pending') return 'Submitting';
    return props.generation.status;
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
</script>

<template>
    <article class="overflow-hidden rounded-2xl border bg-card">
        <div
            class="relative flex aspect-square items-center justify-center overflow-hidden bg-muted/40"
        >
            <img
                v-if="
                    generation.image_url &&
                    !generation.image_expired &&
                    !brokenImage
                "
                :src="generation.image_url"
                :alt="generation.prompt"
                loading="lazy"
                referrerpolicy="no-referrer"
                class="size-full object-contain"
                @error="brokenImage = true"
            />
            <div
                v-else
                class="flex max-w-64 flex-col items-center gap-3 p-6 text-center text-muted-foreground"
            >
                <LoaderCircle
                    v-if="pending && !generation.can_resume"
                    class="size-7 animate-spin"
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
                <p class="text-sm">
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
                </p>
            </div>
            <span
                class="absolute top-3 left-3 rounded-full border bg-background/95 px-2.5 py-1 text-xs font-medium capitalize"
                :class="{
                    'text-emerald-700 dark:text-emerald-400':
                        generation.status === 'succeeded',
                    'text-destructive': generation.status === 'failed',
                }"
                >{{ label }}</span
            >
        </div>
        <div class="space-y-3 p-4">
            <p
                class="line-clamp-3 text-sm leading-relaxed"
                :title="generation.prompt"
            >
                {{ generation.prompt }}
            </p>
            <div class="flex items-center gap-2 text-xs text-muted-foreground">
                <span class="capitalize">{{ generation.quality }}</span
                ><span>·</span
                ><span>{{ generation.size.replace('x', ' × ') }}</span>
            </div>
            <dl class="grid grid-cols-2 gap-3 border-t pt-3 text-xs">
                <div>
                    <dt class="text-muted-foreground">Estimated</dt>
                    <dd class="mt-1 font-mono">
                        {{ formatCost(generation.estimated_cost) }}
                    </dd>
                </div>
                <div>
                    <dt class="text-muted-foreground">Actual · USD</dt>
                    <dd class="mt-1 font-mono font-semibold">
                        {{
                            generation.actual_cost === null
                                ? '—'
                                : formatCost(generation.actual_cost)
                        }}
                    </dd>
                </div>
            </dl>
            <p
                v-if="generation.error_message"
                class="text-xs leading-relaxed text-muted-foreground"
            >
                {{ generation.error_message }}
            </p>
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
            <time
                :datetime="generation.created_at"
                class="block text-xs text-muted-foreground"
                >{{ new Date(generation.created_at).toLocaleString() }}</time
            >
        </div>
    </article>
</template>
