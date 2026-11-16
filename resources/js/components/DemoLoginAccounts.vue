<script setup lang="ts">
import { useForm } from '@inertiajs/vue3';
import DemoLoginController from '@/actions/App/Http/Controllers/DemoLoginController';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Spinner } from '@/components/ui/spinner';
import { formatCredit } from '@/types/credits';

export type DemoAccount = {
    account: string;
    label: string;
    description: string;
    available: string;
};

defineProps<{ accounts: DemoAccount[] }>();

const form = useForm({ account: '' });

function login(account: string): void {
    form.account = account;
    form.submit(DemoLoginController());
}
</script>

<template>
    <section v-if="accounts.length" class="grid gap-3 rounded-xl border p-4">
        <div class="grid gap-1">
            <h2 class="text-sm font-semibold">Try a demo account</h2>
            <p class="text-xs text-muted-foreground">
                No password needed. Accounts are shared; balances reflect
                previous activity. Log out to try another role.
            </p>
        </div>
        <div class="grid gap-2">
            <Button
                v-for="account in accounts"
                :key="account.account"
                type="button"
                variant="outline"
                class="h-auto w-full flex-col items-start gap-1 px-3 py-2.5 text-left whitespace-normal"
                :disabled="form.processing"
                @click="login(account.account)"
            >
                <span class="flex w-full items-center justify-between gap-2">
                    <span class="flex items-center gap-2">
                        <Spinner
                            v-if="
                                form.processing &&
                                form.account === account.account
                            "
                        />
                        {{ account.label }}
                    </span>
                    <span class="text-xs text-muted-foreground">
                        {{ formatCredit(account.available) }} available
                    </span>
                </span>
                <span class="text-xs font-normal text-muted-foreground">
                    {{ account.description }}
                </span>
            </Button>
        </div>
        <InputError :message="form.errors.account" />
    </section>
</template>
