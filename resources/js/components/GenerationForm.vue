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
import { Alert, AlertDescription } from '@/components/ui/alert';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardHeader,
    CardTitle,
    CardDescription,
    CardContent,
} from '@/components/ui/card';
import {
    Field,
    FieldGroup,
    FieldSet,
    FieldLegend,
    FieldLabel,
    FieldDescription,
} from '@/components/ui/field';
import {
    NativeSelect,
    NativeSelectOption,
} from '@/components/ui/native-select';
import { RadioGroup, RadioGroupItem } from '@/components/ui/radio-group';
import { Separator } from '@/components/ui/separator';
import { Textarea } from '@/components/ui/textarea';
import { useGenerationForm } from '@/composables/useGenerationForm';
import { formatCost } from '@/types/generation';

const props = defineProps<{ configured: boolean; availableCredits: string }>();
const emit = defineEmits<{ created: []; balanceChanged: [] }>();
const {
    fields,
    canAfford,
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
    () => props.availableCredits,
    () => emit('balanceChanged'),
);

const prompt = computed({
    get: () => fields.prompt,
    set: (value: string | number) => {
        fields.prompt = String(value);
    },
});
const references = computed({
    get: () => fields.references,
    set: (value: string | number) => {
        fields.references = String(value);
    },
});

const isEstimating = computed(() => estimateHttp.processing);
const isSubmitting = computed(() => submitHttp.processing);

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
    <Card aria-labelledby="create-heading">
        <CardHeader>
            <CardTitle id="create-heading" class="flex items-center gap-2">
                <Sparkles class="size-5" aria-hidden="true" /> Create an image
            </CardTitle>
            <CardDescription>A few words. A fresh perspective.</CardDescription>
        </CardHeader>
        <CardContent>
            <form
                class="flex flex-col gap-6"
                :aria-busy="isSubmitting"
                @submit.prevent="generate"
            >
                <FieldSet
                    :disabled="isSubmitting || !configured"
                    class="disabled:opacity-60"
                >
                    <FieldGroup>
                        <Field :data-invalid="!!errors.prompt">
                            <div
                                class="flex items-center justify-between gap-2"
                            >
                                <FieldLabel for="prompt"
                                    >Describe your image</FieldLabel
                                >
                                <span
                                    class="text-xs text-muted-foreground tabular-nums"
                                    >{{
                                        Array.from(
                                            fields.prompt,
                                        ).length.toLocaleString()
                                    }}
                                    / 4,000</span
                                >
                            </div>
                            <Textarea
                                id="prompt"
                                v-model="prompt"
                                rows="5"
                                maxlength="4000"
                                required
                                aria-describedby="prompt-help prompt-error"
                                :aria-invalid="!!errors.prompt"
                                placeholder="A sunlit glass house in a quiet forest, soft morning light, film photography…"
                                class="min-h-36 resize-y"
                            />
                            <FieldDescription id="prompt-help"
                                >Try a subject, a setting, and a mood. Specific
                                details help.</FieldDescription
                            >
                            <InputError
                                id="prompt-error"
                                :message="errors.prompt"
                            />
                        </Field>
                        <FieldSet aria-describedby="size-help size-error">
                            <FieldLegend id="size-label" variant="label"
                                >Aspect ratio</FieldLegend
                            >
                            <RadioGroup
                                v-model="fields.size"
                                name="size"
                                class="grid-cols-3"
                                aria-labelledby="size-label"
                                aria-describedby="size-help size-error"
                                :aria-invalid="!!errors.size"
                                :disabled="isSubmitting || !configured"
                            >
                                <FieldLabel
                                    v-for="size in imageSizes"
                                    :key="size.value"
                                    :for="`size-${size.value}`"
                                >
                                    <Field
                                        :data-invalid="!!errors.size"
                                        :data-disabled="
                                            isSubmitting || !configured
                                        "
                                        class="items-center"
                                    >
                                        <span class="flex justify-center"
                                            ><RadioGroupItem
                                                :id="`size-${size.value}`"
                                                :value="size.value"
                                                :aria-invalid="!!errors.size"
                                        /></span>
                                        <span
                                            class="flex h-6 items-center justify-center"
                                            aria-hidden="true"
                                            ><span
                                                class="rounded-sm border border-current"
                                                :class="size.shape"
                                        /></span>
                                        <span class="text-center text-xs"
                                            >{{ size.label
                                            }}<span
                                                class="block text-muted-foreground"
                                                >{{ size.ratio }}</span
                                            ></span
                                        >
                                    </Field>
                                </FieldLabel>
                            </RadioGroup>
                            <FieldDescription id="size-help"
                                >{{
                                    fields.size.replace('x', ' × ')
                                }}
                                pixels</FieldDescription
                            >
                            <InputError
                                id="size-error"
                                :message="errors.size"
                            />
                        </FieldSet>
                        <Field :data-invalid="!!errors.quality">
                            <FieldLabel for="quality">Quality</FieldLabel>
                            <NativeSelect
                                id="quality"
                                v-model="fields.quality"
                                aria-describedby="quality-help quality-error"
                                :aria-invalid="!!errors.quality"
                            >
                                <NativeSelectOption value="low"
                                    >Low · Quick
                                    explorations</NativeSelectOption
                                >
                                <NativeSelectOption value="medium"
                                    >Medium · Everyday
                                    creations</NativeSelectOption
                                >
                                <NativeSelectOption value="high"
                                    >High · Finer details</NativeSelectOption
                                >
                            </NativeSelect>
                            <FieldDescription id="quality-help"
                                >Higher quality increases the estimated
                                cost.</FieldDescription
                            >
                            <InputError
                                id="quality-error"
                                :message="errors.quality"
                            />
                        </Field>
                        <details
                            :open="
                                referenceErrors.length > 0 ||
                                !!fields.references
                            "
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
                                <span
                                    class="text-xs font-normal text-muted-foreground"
                                    >Optional</span
                                >
                                <ChevronDown
                                    class="ml-auto size-4 text-muted-foreground transition-transform group-open:rotate-180 motion-reduce:transition-none"
                                    aria-hidden="true"
                                />
                            </summary>
                            <Field
                                class="mt-4"
                                :data-invalid="referenceErrors.length > 0"
                            >
                                <FieldLabel for="references"
                                    >Reference image URLs</FieldLabel
                                >
                                <Textarea
                                    id="references"
                                    v-model="references"
                                    rows="2"
                                    aria-describedby="references-help references-errors"
                                    :aria-invalid="referenceErrors.length > 0"
                                    placeholder="https://example.com/reference.png"
                                />
                                <p
                                    id="references-help"
                                    class="text-xs leading-relaxed text-muted-foreground"
                                >
                                    One URL per line, up to 4. References add to
                                    the cost.
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
                            </Field>
                        </details>
                    </FieldGroup>
                </FieldSet>
                <Separator />
                <div class="flex flex-col gap-4">
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
                                    isEstimating
                                        ? '…'
                                        : quote
                                          ? formatCost(quote.estimated_cost)
                                          : '—'
                                }}</span
                            >
                        </div>
                        <p
                            v-if="isEstimating"
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
                            Both estimates include a 25% markup. Actual cost may
                            be higher or lower. The upper estimate is not a
                            fixed spending cap.
                        </p>
                        <details class="mt-2 text-xs text-muted-foreground">
                            <summary
                                class="w-fit cursor-pointer py-1 underline decoration-border underline-offset-4"
                            >
                                How estimates work
                            </summary>
                            <p class="pt-2 leading-relaxed">
                                The upper estimate assumes maximum token usage
                                at current rates. Your final cost is based on
                                actual usage. The upper estimate is reserved
                                from your balance when you submit. If provider
                                rates change before acceptance, the final charge
                                can exceed the reservation and must be covered
                                before generating again.
                            </p>
                        </details>
                    </div>
                    <Alert v-if="!configured" role="status"
                        ><AlertDescription
                            >Image generation is being set up. Please check back
                            shortly.</AlertDescription
                        ></Alert
                    >
                    <InputError :message="errors.balance" />
                    <p
                        v-if="quote && !canAfford"
                        role="status"
                        class="text-sm text-destructive"
                    >
                        Not enough available credits for this image. Buy credits
                        above to cover the upper estimate.
                    </p>
                    <InputError :message="errors.quote" />
                    <InputError :message="errors.client_request_id" />
                    <p
                        v-if="message"
                        role="alert"
                        class="text-sm text-destructive"
                    >
                        {{ message }}
                    </p>
                    <Button
                        v-if="!quote && input.prompt"
                        type="button"
                        variant="outline"
                        class="w-full"
                        :disabled="isEstimating || isSubmitting || !configured"
                        @click="getEstimate"
                        >{{
                            isEstimating ? 'Estimating…' : 'Refresh estimate'
                        }}</Button
                    >
                    <Button
                        type="submit"
                        size="lg"
                        class="w-full"
                        :disabled="
                            !quote || !canAfford || isSubmitting || !configured
                        "
                    >
                        <LoaderCircle
                            v-if="isSubmitting"
                            class="size-4 motion-safe:animate-spin"
                            aria-hidden="true"
                        />
                        <Sparkles v-else class="size-4" aria-hidden="true" />
                        <span>{{
                            isSubmitting ? 'Submitting…' : 'Generate image'
                        }}</span>
                        <ArrowUpRight
                            v-if="!isSubmitting"
                            class="ml-auto size-4"
                            aria-hidden="true"
                        />
                    </Button>
                    <p class="text-center text-xs text-muted-foreground">
                        Failed generations are always free.
                    </p>
                </div>
            </form>
        </CardContent>
    </Card>
</template>
