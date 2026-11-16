<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { Wallet, LoaderCircle } from '@lucide/vue';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Label } from '@/components/ui/label';
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
    <section
        aria-labelledby="credits-heading"
        class="flex flex-col gap-5 rounded-2xl border bg-card p-5 sm:p-6"
    >
        <div class="flex flex-wrap items-start justify-between gap-4">
            <div class="space-y-2">
                <h2
                    id="credits-heading"
                    class="flex items-center gap-2 text-sm font-medium text-muted-foreground"
                >
                    <Wallet class="size-4" aria-hidden="true" /> Available
                    credits
                </h2>
                <p
                    class="font-mono text-2xl font-semibold break-all tabular-nums"
                    aria-live="polite"
                >
                    {{ formatCredit(credits.available) }}
                </p>
                <p class="text-xs text-muted-foreground">
                    Balance {{ formatCredit(credits.balance) }} · Reserved
                    {{ formatCredit(credits.reserved) }} · USD
                </p>
            </div>
            <Link
                v-if="showHistory"
                :href="index()"
                class="text-sm underline underline-offset-4"
                >Credit history</Link
            >
        </div>
        <p
            v-if="credits.available.startsWith('-')"
            role="status"
            class="text-sm text-destructive"
        >
            A final charge exceeded its reservation. Add credits to cover the
            difference before generating again.
        </p>
        <form class="flex flex-wrap items-end gap-3" @submit.prevent="purchase">
            <div class="grid gap-2">
                <Label for="credit-package">Credit package</Label>
                <select
                    id="credit-package"
                    v-model="amount"
                    :disabled="http.processing || !!pending"
                    class="h-10 rounded-lg border bg-background px-3 text-sm"
                >
                    <option
                        v-for="value in packages"
                        :key="value"
                        :value="value"
                    >
                        ${{ value }} USD
                    </option>
                </select>
            </div>
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
                Simulated purchase. No card details or real payment required.
            </p>
        </form>
        <InputError :message="http.errors.amount" />
        <InputError :message="http.errors.client_request_id" />
        <p v-if="message" role="alert" class="text-sm text-destructive">
            {{ message }}
        </p>
        <p v-if="success" role="status" class="text-sm text-muted-foreground">
            {{ success }}
        </p>
        <p class="text-xs leading-relaxed text-muted-foreground">
            Submitting an image reserves its upper estimate. The actual charge
            is deducted when it finishes and the remaining reservation is
            released. Failed generations are free. Paused checks keep their
            reservation until the outcome is confirmed.
        </p>
    </section>
</template>
