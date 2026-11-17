<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import { ref, watch } from 'vue';
import CreditBalanceCard from '@/components/CreditBalance.vue';
import { Button } from '@/components/ui/button';
import { Empty, EmptyDescription } from '@/components/ui/empty';
import {
    Table,
    TableBody,
    TableCaption,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
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
            <Empty v-if="!entries.meta.total" class="border"
                ><EmptyDescription>
                    No credit activity yet. Buy simulated credits to get
                    started.
                </EmptyDescription></Empty
            >
            <div v-else class="overflow-hidden rounded-xl border">
                <Table class="text-left">
                    <TableCaption class="sr-only">
                        Credit changes and reservations in USD
                    </TableCaption>
                    <TableHeader>
                        <TableRow>
                            <TableHead scope="col">Time (UTC)</TableHead>
                            <TableHead scope="col">Activity</TableHead>
                            <TableHead scope="col">Balance change</TableHead>
                            <TableHead scope="col">Reserved change</TableHead>
                            <TableHead scope="col">Balance after</TableHead>
                            <TableHead scope="col">Reserved after</TableHead>
                        </TableRow>
                    </TableHeader>
                    <TableBody>
                        <TableRow v-for="entry in entries.data" :key="entry.id">
                            <TableCell>{{ entry.created_at }}</TableCell>
                            <TableHead scope="row" class="font-medium">
                                <span>{{ labels[entry.type] }}</span
                                ><span
                                    v-if="entry.generation_id"
                                    class="mt-1 block font-mono text-xs text-muted-foreground"
                                    >{{ entry.generation_id }}</span
                                >
                            </TableHead>
                            <TableCell class="font-mono">
                                {{ formatCredit(entry.amount) }}
                            </TableCell>
                            <TableCell class="font-mono">
                                {{ formatCredit(entry.reserved_amount) }}
                            </TableCell>
                            <TableCell class="font-mono">
                                {{ formatCredit(entry.balance_after) }}
                            </TableCell>
                            <TableCell class="font-mono">
                                {{ formatCredit(entry.reserved_after) }}
                            </TableCell>
                        </TableRow>
                    </TableBody>
                </Table>
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
