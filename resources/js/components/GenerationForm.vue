<script setup lang="ts">
import {
    ArrowUpRight,
    ChevronDown,
    ImagePlus,
    LoaderCircle,
    Sparkles,
} from '@lucide/vue';
import { computed } from 'vue';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Label } from '@/components/ui/label';
import { useGenerationForm } from '@/composables/useGenerationForm';
import { formatCost } from '@/types/generation';

const props = defineProps<{ configured: boolean }>();
const emit = defineEmits<{ created: [] }>();
const {
    fields,
    input,
    quote,
    message,
    estimateHttp,
    submitHttp,
    errors,
    getEstimate,
    generate,
} = useGenerationForm(
    () => props.configured,
    () => emit('created'),
);

const imageSizes = [
    { value: '1024x1024', label: 'Square', ratio: '1:1', shape: 'size-5' },
    { value: '1536x1024', label: 'Landscape', ratio: '3:2', shape: 'h-4 w-6' },
    { value: '1024x1536', label: 'Portrait', ratio: '2:3', shape: 'h-6 w-4' },
];

const referenceErrors = computed(() =>
    Object.entries(errors.value).filter(
        ([key]) =>
            key === 'reference_images' || key.startsWith('reference_images.'),
    ),
);
</script>

<template>
    <section
        aria-labelledby="create-heading"
        class="overflow-hidden rounded-2xl border bg-card shadow-sm"
    >
        <div
            class="flex items-center gap-3 border-b bg-accent/30 px-5 py-5 sm:px-6"
        >
            <div
                class="flex size-10 shrink-0 items-center justify-center rounded-xl border border-primary/15 bg-card text-primary"
            >
                <Sparkles class="size-5" aria-hidden="true" />
            </div>
            <div>
                <h2 id="create-heading" class="font-semibold">
                    Create an image
                </h2>
                <p class="mt-0.5 text-xs text-muted-foreground">
                    A few words. A fresh perspective.
                </p>
            </div>
        </div>
        <form
            class="space-y-6 p-5 sm:p-6"
            :aria-busy="submitHttp.processing"
            @submit.prevent="generate"
        >
            <fieldset
                :disabled="submitHttp.processing || !configured"
                class="min-w-0 space-y-6 disabled:opacity-60"
            >
                <div class="space-y-2.5">
                    <div class="flex items-center justify-between gap-2">
                        <Label for="prompt">Describe your image</Label>
                        <span class="text-xs text-muted-foreground tabular-nums"
                            >{{
                                Array.from(
                                    fields.prompt,
                                ).length.toLocaleString()
                            }}
                            / 4,000</span
                        >
                    </div>
                    <textarea
                        id="prompt"
                        v-model="fields.prompt"
                        rows="5"
                        maxlength="4000"
                        required
                        aria-describedby="prompt-help prompt-error"
                        :aria-invalid="!!errors.prompt"
                        placeholder="A sunlit glass house in a quiet forest, soft morning light, film photography…"
                        class="min-h-36 w-full resize-y rounded-xl border bg-background/60 p-3.5 text-base leading-relaxed transition-colors placeholder:text-muted-foreground/75 focus:border-primary focus:bg-card focus-visible:outline-offset-2 sm:text-sm"
                    />
                    <p
                        id="prompt-help"
                        class="text-xs leading-relaxed text-muted-foreground"
                    >
                        Try a subject, a setting, and a mood. Specific details
                        help.
                    </p>
                    <InputError id="prompt-error" :message="errors.prompt" />
                </div>
                <fieldset
                    class="min-w-0 space-y-2.5"
                    aria-describedby="size-help size-error"
                >
                    <legend class="text-sm font-medium">Aspect ratio</legend>
                    <div class="grid grid-cols-3 gap-2">
                        <label
                            v-for="size in imageSizes"
                            :key="size.value"
                            class="relative cursor-pointer"
                        >
                            <input
                                v-model="fields.size"
                                type="radio"
                                name="size"
                                :value="size.value"
                                class="peer sr-only"
                                :aria-invalid="!!errors.size"
                            />
                            <span
                                class="flex h-24 flex-col items-center justify-center gap-2 rounded-xl border bg-background/40 text-muted-foreground transition-colors peer-checked:border-primary peer-checked:bg-accent peer-checked:text-accent-foreground peer-focus-visible:outline-2 peer-focus-visible:outline-offset-2 peer-focus-visible:outline-ring peer-disabled:cursor-not-allowed hover:bg-accent/50"
                            >
                                <span
                                    class="flex h-6 items-center"
                                    aria-hidden="true"
                                    ><span
                                        class="rounded-[3px] border-[1.5px] border-current"
                                        :class="size.shape"
                                /></span>
                                <span class="text-xs font-medium"
                                    >{{ size.label }}
                                    <span class="opacity-70">{{
                                        size.ratio
                                    }}</span></span
                                >
                            </span>
                        </label>
                    </div>
                    <p id="size-help" class="text-xs text-muted-foreground">
                        {{ fields.size.replace('x', ' × ') }} pixels
                    </p>
                    <InputError id="size-error" :message="errors.size" />
                </fieldset>
                <div class="space-y-2.5">
                    <Label for="quality">Quality</Label>
                    <select
                        id="quality"
                        v-model="fields.quality"
                        aria-describedby="quality-help quality-error"
                        :aria-invalid="!!errors.quality"
                        class="h-11 w-full rounded-lg border bg-background/60 px-3 text-base sm:text-sm"
                    >
                        <option value="low">Low · Quick explorations</option>
                        <option value="medium">
                            Medium · Everyday creations
                        </option>
                        <option value="high">High · Finer details</option>
                    </select>
                    <p id="quality-help" class="text-xs text-muted-foreground">
                        Higher quality increases the estimated cost.
                    </p>
                    <InputError id="quality-error" :message="errors.quality" />
                </div>
                <details
                    :open="referenceErrors.length > 0 || !!fields.references"
                    class="group rounded-xl border p-3.5"
                >
                    <summary
                        class="flex min-h-6 cursor-pointer list-none items-center gap-2 text-sm font-medium [&::-webkit-details-marker]:hidden"
                    >
                        <ImagePlus
                            class="size-4 text-muted-foreground"
                            aria-hidden="true"
                        />
                        Add reference images
                        <span class="text-xs font-normal text-muted-foreground"
                            >Optional</span
                        >
                        <ChevronDown
                            class="ml-auto size-4 text-muted-foreground transition-transform group-open:rotate-180 motion-reduce:transition-none"
                            aria-hidden="true"
                        />
                    </summary>
                    <div class="mt-4 space-y-2">
                        <Label for="references">Reference image URLs</Label>
                        <textarea
                            id="references"
                            v-model="fields.references"
                            rows="2"
                            aria-describedby="references-help references-errors"
                            :aria-invalid="referenceErrors.length > 0"
                            placeholder="https://example.com/reference.png"
                            class="w-full rounded-lg border bg-background/60 p-3 text-base sm:text-sm"
                        />
                        <p
                            id="references-help"
                            class="text-xs leading-relaxed text-muted-foreground"
                        >
                            One URL per line, up to 4. References add to the
                            cost.
                        </p>
                        <div id="references-errors">
                            <p
                                v-for="[key, error] in referenceErrors"
                                :key="key"
                                class="text-sm text-destructive"
                            >
                                {{ error }}
                            </p>
                        </div>
                    </div>
                </details>
            </fieldset>
            <div class="space-y-4 border-t pt-5">
                <div
                    class="rounded-xl border border-primary/10 bg-accent/40 p-4"
                >
                    <div
                        class="flex flex-wrap items-baseline justify-between gap-2"
                        aria-live="polite"
                        aria-atomic="true"
                    >
                        <span class="text-sm font-medium"
                            >Estimated cost
                            <span
                                class="text-xs font-normal text-muted-foreground"
                                >USD</span
                            ></span
                        >
                        <span
                            class="font-mono text-xl font-semibold tracking-tight break-all"
                            >{{
                                estimateHttp.processing
                                    ? '…'
                                    : quote
                                      ? formatCost(quote.estimated_cost)
                                      : '—'
                            }}</span
                        >
                    </div>
                    <p
                        v-if="estimateHttp.processing"
                        role="status"
                        class="mt-2 text-xs text-muted-foreground"
                    >
                        Calculating your estimate…
                    </p>
                    <p
                        v-else-if="!quote"
                        class="mt-2 text-xs leading-relaxed text-muted-foreground"
                    >
                        Add your prompt to see a price before you generate.
                    </p>
                    <p
                        v-if="quote"
                        class="mt-2 text-xs leading-relaxed text-muted-foreground"
                    >
                        Upper estimate at current rates:
                        <span class="font-mono break-all text-foreground">{{
                            formatCost(quote.maximum_cost)
                        }}</span>
                    </p>
                    <p
                        class="mt-3 text-xs leading-relaxed text-muted-foreground"
                    >
                        Both estimates include a 25% markup. Actual cost may be
                        higher or lower. The upper estimate is not a fixed
                        spending cap.
                    </p>
                    <details class="mt-2 text-xs text-muted-foreground">
                        <summary
                            class="w-fit cursor-pointer py-1 underline decoration-border underline-offset-4"
                        >
                            How estimates work
                        </summary>
                        <p class="pt-2 leading-relaxed">
                            The upper estimate assumes maximum token usage at
                            current rates. Your final cost is based on actual
                            usage.
                        </p>
                    </details>
                </div>
                <p
                    v-if="!configured"
                    role="status"
                    class="rounded-lg border bg-muted/50 p-3 text-sm leading-relaxed text-muted-foreground"
                >
                    Image generation is being set up. Please check back shortly.
                </p>
                <InputError :message="errors.quote" />
                <InputError :message="errors.client_request_id" />
                <p v-if="message" role="alert" class="text-sm text-destructive">
                    {{ message }}
                </p>
                <Button
                    v-if="!quote && input.prompt"
                    type="button"
                    variant="outline"
                    class="h-11 w-full"
                    :disabled="
                        estimateHttp.processing ||
                        submitHttp.processing ||
                        !configured
                    "
                    @click="getEstimate"
                    >{{
                        estimateHttp.processing
                            ? 'Estimating…'
                            : 'Refresh estimate'
                    }}</Button
                >
                <Button
                    type="submit"
                    class="h-12 w-full rounded-xl shadow-sm"
                    :disabled="!quote || submitHttp.processing || !configured"
                >
                    <LoaderCircle
                        v-if="submitHttp.processing"
                        class="size-4 motion-safe:animate-spin"
                        aria-hidden="true"
                    />
                    <Sparkles v-else class="size-4" aria-hidden="true" />
                    <span>{{
                        submitHttp.processing ? 'Submitting…' : 'Generate image'
                    }}</span>
                    <ArrowUpRight
                        v-if="!submitHttp.processing"
                        class="ml-auto size-4"
                        aria-hidden="true"
                    />
                </Button>
                <p class="text-center text-xs text-muted-foreground">
                    Failed generations are always free.
                </p>
            </div>
        </form>
    </section>
</template>
