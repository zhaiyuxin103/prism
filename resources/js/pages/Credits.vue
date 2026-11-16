<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import { ref, watch } from 'vue';
import CreditBalanceCard from '@/components/CreditBalance.vue';
import { Button } from '@/components/ui/button';
import { index } from '@/routes/credits';
import { formatCredit } from '@/types/credits';
import type { CreditBalance, CreditEntryPage } from '@/types/credits';

const props = defineProps<{
    credits: CreditBalance;
    creditPackages: string[];
    entries: CreditEntryPage;
}>();
const balance = ref(props.credits);
watch(
    () => props.credits,
    (value) => {
        balance.value = value;
    },
);
function purchased(credits: CreditBalance): void {
    balance.value = credits;
    router.get(index.url(), {}, { preserveState: true, preserveScroll: true });
}
const labels = {
    purchase: 'Simulated purchase',
    reservation: 'Generation reservation',
    settlement: 'Generation settled / reservation released',
};
defineOptions({
    layout: { breadcrumbs: [{ title: 'Credits', href: index() }] },
});
</script>

<template>
    <div class="mx-auto flex w-full max-w-6xl flex-col gap-6 p-5 lg:p-8">
        <Head title="Credits" />
        <header class="space-y-2">
            <h1 class="text-3xl font-semibold tracking-tight">Credits</h1>
            <p class="text-sm text-muted-foreground">
                Your balance and activity. Credits are US dollars.
            </p>
        </header>
        <CreditBalanceCard
            :credits="balance"
            :packages="creditPackages"
            @purchased="purchased"
        />
        <section class="space-y-4" aria-labelledby="history-heading">
            <div class="flex items-center justify-between gap-3">
                <h2 id="history-heading" class="text-lg font-semibold">
                    Credit history
                </h2>
                <Button
                    variant="outline"
                    size="sm"
                    @click="router.reload({ only: ['credits', 'entries'] })"
                    >Refresh</Button
                >
            </div>
            <p
                v-if="!entries.meta.total"
                class="rounded-xl border border-dashed p-8 text-center text-sm text-muted-foreground"
            >
                No credit activity yet. Buy simulated credits to get started.
            </p>
            <div v-else class="overflow-x-auto rounded-xl border">
                <table
                    class="w-full text-left text-sm whitespace-nowrap [&_td]:p-3 [&_th]:p-3"
                >
                    <caption class="sr-only">
                        Credit changes and reservations in USD
                    </caption>
                    <thead class="bg-muted/50 text-xs text-muted-foreground">
                        <tr>
                            <th scope="col">Time (UTC)</th>
                            <th scope="col">Activity</th>
                            <th scope="col">Balance change</th>
                            <th scope="col">Reserved change</th>
                            <th scope="col">Balance after</th>
                            <th scope="col">Reserved after</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y">
                        <tr v-for="entry in entries.data" :key="entry.id">
                            <td>{{ entry.created_at }}</td>
                            <th scope="row" class="font-medium">
                                <span>{{ labels[entry.type] }}</span
                                ><span
                                    v-if="entry.generation_id"
                                    class="mt-1 block font-mono text-xs text-muted-foreground"
                                    >{{ entry.generation_id }}</span
                                >
                            </th>
                            <td class="font-mono">
                                {{ formatCredit(entry.amount) }}
                            </td>
                            <td class="font-mono">
                                {{ formatCredit(entry.reserved_amount) }}
                            </td>
                            <td class="font-mono">
                                {{ formatCredit(entry.balance_after) }}
                            </td>
                            <td class="font-mono">
                                {{ formatCredit(entry.reserved_after) }}
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
            <nav
                v-if="entries.meta.last_page > 1"
                aria-label="Credit history pages"
                class="flex items-center justify-between"
            >
                <Button
                    variant="outline"
                    :disabled="!entries.links.prev"
                    as-child
                    ><Link
                        v-if="entries.links.prev"
                        :href="entries.links.prev"
                        preserve-scroll
                        >Previous</Link
                    ><span v-else>Previous</span></Button
                >
                <span class="text-sm text-muted-foreground"
                    >{{ entries.meta.current_page }} /
                    {{ entries.meta.last_page }}</span
                >
                <Button
                    variant="outline"
                    :disabled="!entries.links.next"
                    as-child
                    ><Link
                        v-if="entries.links.next"
                        :href="entries.links.next"
                        preserve-scroll
                        >Next</Link
                    ><span v-else>Next</span></Button
                >
            </nav>
        </section>
    </div>
</template>
