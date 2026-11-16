<script setup lang="ts">
import { ArrowUpRight, LoaderCircle, Sparkles } from '@lucide/vue';
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

const referenceErrors = computed(() =>
    Object.entries(errors.value).filter(
        ([key]) =>
            key === 'reference_images' || key.startsWith('reference_images.'),
    ),
);
</script>

<template>
    <section
        class="rounded-2xl border bg-card p-6 shadow-sm lg:sticky lg:top-6"
    >
        <div class="mb-6 flex items-center gap-3">
            <div
                class="flex size-9 items-center justify-center rounded-xl bg-primary/10 text-primary"
            >
                <Sparkles class="size-4" />
            </div>
            <div>
                <h2 class="font-semibold">Create something new</h2>
                <p class="text-xs text-muted-foreground">
                    A few words. A fresh perspective.
                </p>
            </div>
        </div>
        <form class="space-y-5" @submit.prevent="generate">
            <fieldset
                :disabled="submitHttp.processing || !configured"
                class="space-y-5 disabled:opacity-60"
            >
                <div class="space-y-2">
                    <Label for="prompt">Your prompt</Label>
                    <textarea
                        id="prompt"
                        v-model="fields.prompt"
                        rows="5"
                        maxlength="4000"
                        required
                        placeholder="A sunlit glass house in a quiet forest, soft morning light, film photography…"
                        class="w-full resize-y rounded-xl border bg-background p-3 text-sm leading-relaxed outline-none focus-visible:ring-2 focus-visible:ring-ring"
                    />
                    <div
                        class="flex justify-between text-xs text-muted-foreground"
                    >
                        <span>Describe the image you have in mind.</span
                        ><span
                            >{{ Array.from(fields.prompt).length }} /
                            4,000</span
                        >
                    </div>
                    <InputError :message="errors.prompt" />
                </div>
                <div class="grid grid-cols-2 gap-3">
                    <div class="space-y-2">
                        <Label for="size">Image size</Label>
                        <select
                            id="size"
                            v-model="fields.size"
                            class="h-10 w-full rounded-lg border bg-background px-3 text-sm"
                        >
                            <option value="1024x1024">Square</option>
                            <option value="1536x1024">Landscape</option>
                            <option value="1024x1536">Portrait</option>
                        </select>
                        <p class="text-xs text-muted-foreground">
                            {{ fields.size.replace('x', ' × ') }} px
                        </p>
                        <InputError :message="errors.size" />
                    </div>
                    <div class="space-y-2">
                        <Label for="quality">Quality</Label>
                        <select
                            id="quality"
                            v-model="fields.quality"
                            class="h-10 w-full rounded-lg border bg-background px-3 text-sm"
                        >
                            <option value="low">Low</option>
                            <option value="medium">Medium</option>
                            <option value="high">High</option>
                        </select>
                        <InputError :message="errors.quality" />
                    </div>
                </div>
                <div class="space-y-2">
                    <Label for="references"
                        >Reference image URLs
                        <span class="font-normal text-muted-foreground"
                            >Optional</span
                        ></Label
                    >
                    <textarea
                        id="references"
                        v-model="fields.references"
                        rows="2"
                        placeholder="https://example.com/reference.png"
                        class="w-full rounded-xl border bg-background p-3 text-sm outline-none focus-visible:ring-2 focus-visible:ring-ring"
                    />
                    <p class="text-xs text-muted-foreground">
                        One URL per line, up to 4. References add to the cost.
                    </p>
                    <p
                        v-for="[key, error] in referenceErrors"
                        :key="key"
                        class="text-sm text-destructive"
                    >
                        {{ error }}
                    </p>
                </div>
            </fieldset>
            <div class="rounded-xl bg-muted/60 p-4" aria-live="polite">
                <div class="flex items-center justify-between gap-3">
                    <span class="text-sm font-medium">Estimated cost</span
                    ><span
                        class="font-mono text-lg font-semibold tracking-tight"
                        >{{
                            quote ? formatCost(quote.estimated_cost) : '—'
                        }}</span
                    >
                </div>
                <p v-if="quote" class="mt-2 text-sm">
                    Upper estimate at current rates:
                    <span class="font-mono">{{
                        formatCost(quote.maximum_cost)
                    }}</span>
                </p>
                <p class="mt-2 text-xs leading-relaxed text-muted-foreground">
                    USD · Both estimates include a 25% markup. Actual cost may
                    be higher or lower than the estimate. The upper estimate
                    assumes maximum token usage at current rates, not a fixed
                    spending cap. Failed generations are free.
                </p>
                <p
                    v-if="estimateHttp.processing"
                    class="mt-2 text-xs text-muted-foreground"
                >
                    Calculating your estimate…
                </p>
            </div>
            <p
                v-if="!configured"
                role="status"
                class="text-sm text-muted-foreground"
            >
                Image generation is being set up. Please check back shortly.
            </p>
            <InputError :message="errors.quote" />
            <InputError :message="errors.client_request_id" />
            <p v-if="message" role="alert" class="text-sm text-destructive">
                {{ message }}
            </p>
            <Button
                v-if="!quote"
                type="button"
                variant="outline"
                class="w-full"
                :disabled="
                    !input.prompt || estimateHttp.processing || !configured
                "
                @click="getEstimate"
                >{{
                    estimateHttp.processing ? 'Estimating…' : 'Refresh estimate'
                }}</Button
            >
            <Button
                type="submit"
                class="h-11 w-full rounded-xl"
                :disabled="!quote || submitHttp.processing || !configured"
                ><LoaderCircle
                    v-if="submitHttp.processing"
                    class="size-4 animate-spin" /><span>{{
                    submitHttp.processing ? 'Submitting…' : 'Generate image'
                }}</span
                ><ArrowUpRight v-if="!submitHttp.processing" class="size-4"
            /></Button>
        </form>
    </section>
</template>
