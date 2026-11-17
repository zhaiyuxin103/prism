<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { Wallet, LoaderCircle } from '@lucide/vue';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardHeader,
    CardTitle,
    CardDescription,
    CardContent,
} from '@/components/ui/card';
import { Field, FieldLabel, FieldGroup } from '@/components/ui/field';
import {
    NativeSelect,
    NativeSelectOption,
} from '@/components/ui/native-select';
import { useCreditPurchase } from '@/composables/useCreditPurchase';
import { index } from '@/routes/credits';
import { formatCredit } from '@/types/credits';
import type { CreditBalance } from '@/types/credits';

defineProps<{
    credits: CreditBalance;
    packages: string[];
    showHistory?: boolean;
}>();
const emit = defineEmits<{ purchased: [credits: CreditBalance] }>();
const { amount, pending, http, message, success, purchase } = useCreditPurchase(
    (credits) => emit('purchased', credits),
);
</script>

<template>
    <Card aria-labelledby="credits-heading">
        <CardHeader class="flex flex-wrap items-start justify-between gap-4">
            <div class="space-y-2">
                <CardTitle id="credits-heading" class="flex items-center gap-2">
                    <Wallet class="size-4" aria-hidden="true" /> Available
                    credits
                </CardTitle>
                <p
                    class="font-mono text-2xl font-semibold break-all tabular-nums"
                    aria-live="polite"
                >
                    {{ formatCredit(credits.available) }}
                </p>
                <CardDescription>
                    Balance {{ formatCredit(credits.balance) }} · Reserved
                    {{ formatCredit(credits.reserved) }} · USD
                </CardDescription>
            </div>
            <Button v-if="showHistory" variant="link" as-child
                ><Link :href="index()">Credit history</Link></Button
            >
        </CardHeader>
        <CardContent class="flex flex-col gap-4">
            <p
                v-if="credits.available.startsWith('-')"
                role="status"
                class="text-sm text-destructive"
            >
                A final charge exceeded its reservation. Add credits to cover
                the difference before generating again.
            </p>
            <form @submit.prevent="purchase">
                <FieldGroup class="flex-row flex-wrap items-end gap-3">
                    <Field
                        class="w-auto"
                        :data-invalid="!!http.errors.amount"
                        :data-disabled="http.processing || !!pending"
                    >
                        <FieldLabel for="credit-package"
                            >Credit package</FieldLabel
                        >
                        <NativeSelect
                            id="credit-package"
                            v-model="amount"
                            :disabled="http.processing || !!pending"
                            :aria-invalid="!!http.errors.amount"
                            aria-describedby="credit-package-error"
                        >
                            <NativeSelectOption
                                v-for="value in packages"
                                :key="value"
                                :value="value"
                            >
                                ${{ value }} USD
                            </NativeSelectOption>
                        </NativeSelect>
                    </Field>
                    <Button type="submit" :disabled="http.processing"
                        ><LoaderCircle
                            v-if="http.processing"
                            class="size-4 animate-spin"
                        />{{
                            http.processing
                                ? 'Adding credits…'
                                : pending
                                  ? 'Retry purchase'
                                  : 'Buy credits'
                        }}</Button
                    >
                    <p class="w-full text-xs text-muted-foreground">
                        Simulated purchase. No card details or real payment
                        required.
                    </p>
                </FieldGroup>
            </form>
            <InputError
                id="credit-package-error"
                :message="http.errors.amount"
            />
            <InputError :message="http.errors.client_request_id" />
            <p v-if="message" role="alert" class="text-sm text-destructive">
                {{ message }}
            </p>
            <p
                v-if="success"
                role="status"
                class="text-sm text-muted-foreground"
            >
                {{ success }}
            </p>
            <p class="text-xs leading-relaxed text-muted-foreground">
                Submitting an image reserves its upper estimate. The actual
                charge is deducted when it finishes and the remaining
                reservation is released. Failed generations are free. Paused
                checks keep their reservation until the outcome is confirmed.
            </p>
        </CardContent>
    </Card>
</template>
