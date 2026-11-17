<script setup lang="ts">
import { Form, Head, Link } from '@inertiajs/vue3';
import { CircleCheck, CircleAlert, RefreshCw } from '@lucide/vue';
import { computed, ref } from 'vue';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardHeader,
    CardTitle,
    CardDescription,
    CardContent,
} from '@/components/ui/card';
import { Empty, EmptyDescription } from '@/components/ui/empty';
import { Field, FieldGroup, FieldLabel } from '@/components/ui/field';
import { Input } from '@/components/ui/input';
import {
    Table,
    TableBody,
    TableCaption,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import { report as reportRoute } from '@/routes/admin';
import type { AdminReport, UserReportPage } from '@/types/admin-report';
import { formatCost } from '@/types/generation';

const props = defineProps<{
    filters: { start_date: string; end_date: string };
    report: AdminReport;
    users: UserReportPage;
}>();

const showEmptyDays = ref(false);
const visibleDays = computed(() =>
    props.report.days.filter((day) => {
        if (showEmptyDays.value || day.reconciliation?.status !== 'matched') {
            return true;
        }

        return (
            day.amounts.generations > 0 ||
            day.amounts.failed > 0 ||
            day.reconciliation.provider_generations > 0 ||
            [
                day.amounts.user_charge,
                day.amounts.provider_cost,
                day.amounts.profit,
                day.reconciliation.provider_cost,
            ].some((amount) => /[1-9]/.test(amount))
        );
    }),
);

const metrics = [
    {
        key: 'user_charge',
        label: 'Credit spend',
        description: 'Actual charges to users',
    },
    {
        key: 'provider_cost',
        label: 'Provider cost',
        description: 'Cost recorded by Prism',
    },
    {
        key: 'profit',
        label: 'Profit',
        description: 'Credit spend minus provider cost',
    },
] as const;

defineOptions({
    layout: { breadcrumbs: [{ title: 'Admin report', href: reportRoute() }] },
});
</script>

<template>
    <div class="flex flex-1 flex-col">
        <Head title="Admin report" />
        <div class="mx-auto flex w-full max-w-7xl flex-col gap-8 p-5 lg:p-8">
            <header class="flex flex-col gap-2">
                <p
                    class="text-xs font-medium tracking-[0.2em] text-muted-foreground uppercase"
                >
                    Prism / Operations
                </p>
                <h1 class="text-3xl font-semibold tracking-tight">
                    Admin report
                </h1>
                <p class="text-sm text-muted-foreground">
                    Credit spend, provider costs and profit across all users.
                    All amounts are in USD.
                </p>
            </header>

            <Card
                ><CardContent>
                    <Form
                        :action="reportRoute.url()"
                        method="get"
                        :options="{
                            preserveState: 'errors',
                            preserveScroll: true,
                        }"
                        #default="{ errors, processing }"
                    >
                        <FieldGroup
                            class="flex-row flex-wrap items-start gap-4"
                        >
                            <Field
                                class="w-auto"
                                :data-invalid="!!errors.start_date"
                            >
                                <FieldLabel for="start_date"
                                    >From (UTC)</FieldLabel
                                >
                                <Input
                                    id="start_date"
                                    name="start_date"
                                    type="date"
                                    :default-value="filters.start_date"
                                    required
                                />
                                <InputError :message="errors.start_date" />
                            </Field>
                            <Field
                                class="w-auto"
                                :data-invalid="!!errors.end_date"
                            >
                                <FieldLabel for="end_date"
                                    >Through (UTC)</FieldLabel
                                >
                                <Input
                                    id="end_date"
                                    name="end_date"
                                    type="date"
                                    :default-value="filters.end_date"
                                    required
                                />
                                <InputError :message="errors.end_date" />
                            </Field>
                            <Button
                                type="submit"
                                :disabled="processing"
                                class="mt-6"
                            >
                                <RefreshCw
                                    class="size-4"
                                    :class="{ 'animate-spin': processing }"
                                />
                                {{
                                    processing ? 'Checking…' : 'Refresh report'
                                }}
                            </Button>
                            <p class="w-full text-xs text-muted-foreground">
                                Up to 92 days, inclusive. Generations belong to
                                the UTC day they completed at Pixelforge.
                            </p>
                        </FieldGroup>
                    </Form>
                </CardContent></Card
            >

            <div class="grid gap-4 md:grid-cols-3">
                <Card v-for="metric in metrics" :key="metric.key">
                    <CardHeader
                        ><CardTitle>{{ metric.label }}</CardTitle
                        ><CardDescription>{{
                            metric.description
                        }}</CardDescription></CardHeader
                    >
                    <CardContent
                        ><p
                            class="font-mono text-xl font-semibold break-all tabular-nums"
                        >
                            {{ formatCost(report.totals[metric.key]) }}
                        </p></CardContent
                    >
                </Card>
            </div>

            <Card aria-labelledby="reconciliation-title">
                <CardHeader class="flex items-center gap-2">
                    <CircleCheck
                        v-if="report.reconciliation.status === 'matched'"
                        class="size-5 text-success"
                    />
                    <CircleAlert v-else class="size-5 text-warning" />
                    <CardTitle id="reconciliation-title">
                        {{
                            report.reconciliation.status === 'matched'
                                ? 'Provider costs match'
                                : report.reconciliation.status === 'mismatch'
                                  ? 'Reconciliation difference found'
                                  : 'Reconciliation unavailable'
                        }}
                    </CardTitle>
                </CardHeader>
                <CardContent class="flex flex-col gap-3">
                    <p
                        v-if="report.reconciliation.message"
                        role="status"
                        class="text-sm"
                    >
                        {{ report.reconciliation.message }}
                    </p>
                    <div
                        v-if="report.reconciliation.total"
                        class="flex flex-wrap gap-x-8 gap-y-3 text-sm"
                    >
                        <p>
                            Pixelforge total
                            <strong class="font-mono">{{
                                formatCost(
                                    report.reconciliation.total.provider_cost,
                                )
                            }}</strong>
                        </p>
                        <p>
                            Cost difference
                            <strong class="font-mono">{{
                                formatCost(
                                    report.reconciliation.total.cost_difference,
                                )
                            }}</strong>
                        </p>
                        <p>
                            Successful generations
                            <strong
                                >{{ report.totals.generations }} local /
                                {{
                                    report.reconciliation.total
                                        .provider_generations
                                }}
                                provider</strong
                            >
                        </p>
                    </div>
                    <p
                        v-if="report.reconciliation.status === 'mismatch'"
                        class="text-sm text-muted-foreground"
                    >
                        Review the highlighted days below. Delayed results or
                        using a different provider key can cause differences.
                        Refresh after pending generations have settled.
                    </p>
                    <p class="text-xs leading-relaxed text-muted-foreground">
                        Compared with the usage reported by the API key
                        configured for this environment. Use a dedicated key for
                        this database; changing keys or sharing one across
                        environments changes the comparison. Differences are
                        local minus provider. Today's figures may change as
                        generations complete.
                    </p>
                    <p
                        v-if="report.reconciliation.checked_at"
                        class="text-xs text-muted-foreground"
                    >
                        Checked {{ report.reconciliation.checked_at }} (UTC).
                        Provider usage may be cached for up to 30 seconds.
                    </p>
                    <p
                        v-if="report.unresolved_generations"
                        class="text-sm text-muted-foreground"
                    >
                        {{ report.unresolved_generations }} generation(s) in
                        this environment still have no final result, including
                        paused checks. Their final charges are not yet included.
                    </p>
                </CardContent>
            </Card>

            <section class="flex flex-col gap-4" aria-labelledby="daily-title">
                <div
                    class="flex flex-wrap items-baseline justify-between gap-2"
                >
                    <h2 id="daily-title" class="text-lg font-semibold">
                        Daily totals
                    </h2>
                    <p class="text-xs text-muted-foreground">
                        {{ report.totals.generations }} succeeded ·
                        {{ report.totals.failed }} failed at provider
                    </p>
                </div>
                <div class="flex flex-wrap items-center justify-between gap-2">
                    <p class="text-xs text-muted-foreground">
                        Days with no activity on either side are hidden by
                        default. Failed generations, differences and unchecked
                        days remain visible.
                    </p>
                    <Button
                        type="button"
                        variant="outline"
                        size="sm"
                        :aria-pressed="showEmptyDays"
                        @click="showEmptyDays = !showEmptyDays"
                    >
                        {{
                            showEmptyDays
                                ? 'Hide empty days'
                                : 'Show empty days'
                        }}
                    </Button>
                </div>
                <Empty v-if="visibleDays.length === 0" class="border"
                    ><EmptyDescription>
                        No activity on either side for this date range.
                    </EmptyDescription></Empty
                >
                <div v-else class="overflow-hidden rounded-xl border">
                    <Table class="text-left">
                        <TableCaption class="sr-only">
                            Daily charges, profit and provider reconciliation in
                            USD
                        </TableCaption>
                        <TableHeader>
                            <TableRow>
                                <TableHead scope="col">Date (UTC)</TableHead>
                                <TableHead scope="col"
                                    >Succeeded / failed</TableHead
                                >
                                <TableHead scope="col">Credit spend</TableHead>
                                <TableHead scope="col">Provider cost</TableHead>
                                <TableHead scope="col">Profit</TableHead>
                                <TableHead scope="col"
                                    >Pixelforge cost</TableHead
                                >
                                <TableHead scope="col"
                                    >Cost difference</TableHead
                                >
                                <TableHead scope="col"
                                    >Count difference</TableHead
                                >
                                <TableHead scope="col">Check</TableHead>
                            </TableRow>
                        </TableHeader>
                        <TableBody>
                            <TableRow
                                v-for="day in visibleDays"
                                :key="day.date"
                                :class="{
                                    'bg-warning/10 hover:bg-warning/15':
                                        day.reconciliation?.status ===
                                        'mismatch',
                                }"
                            >
                                <TableHead scope="row" class="font-medium">
                                    {{ day.date }}
                                </TableHead>
                                <TableCell>
                                    {{ day.amounts.generations }} /
                                    {{ day.amounts.failed }}
                                </TableCell>
                                <TableCell class="font-mono tabular-nums">
                                    {{ formatCost(day.amounts.user_charge) }}
                                </TableCell>
                                <TableCell class="font-mono tabular-nums">
                                    {{ formatCost(day.amounts.provider_cost) }}
                                </TableCell>
                                <TableCell class="font-mono tabular-nums">
                                    {{ formatCost(day.amounts.profit) }}
                                </TableCell>
                                <TableCell class="font-mono tabular-nums">
                                    {{
                                        day.reconciliation
                                            ? formatCost(
                                                  day.reconciliation
                                                      .provider_cost,
                                              )
                                            : '—'
                                    }}
                                </TableCell>
                                <TableCell class="font-mono tabular-nums">
                                    {{
                                        day.reconciliation
                                            ? formatCost(
                                                  day.reconciliation
                                                      .cost_difference,
                                              )
                                            : '—'
                                    }}
                                </TableCell>
                                <TableCell>
                                    {{
                                        day.reconciliation
                                            ?.generation_difference ?? '—'
                                    }}
                                </TableCell>
                                <TableCell>
                                    {{
                                        day.reconciliation?.status === 'matched'
                                            ? 'Matched'
                                            : day.reconciliation
                                              ? 'Difference'
                                              : 'Not checked'
                                    }}
                                </TableCell>
                            </TableRow>
                        </TableBody>
                    </Table>
                </div>
            </section>

            <section class="flex flex-col gap-4" aria-labelledby="users-title">
                <div
                    class="flex flex-wrap items-baseline justify-between gap-2"
                >
                    <h2 id="users-title" class="text-lg font-semibold">
                        Spend by user
                    </h2>
                    <p class="text-xs text-muted-foreground">
                        {{ users.total }} users · highest spend first
                    </p>
                </div>
                <Empty v-if="users.total === 0" class="border"
                    ><EmptyDescription>
                        No completed, settled generations in this date range.
                    </EmptyDescription></Empty
                >
                <div v-else class="overflow-hidden rounded-xl border">
                    <details
                        v-for="user in users.data"
                        :key="user.id ?? 'deleted'"
                        class="border-b last:border-b-0"
                    >
                        <summary
                            class="cursor-pointer p-4 focus-visible:outline-2 focus-visible:outline-ring"
                        >
                            <span class="font-medium">{{ user.name }}</span>
                            <span class="ml-2 text-xs text-muted-foreground">{{
                                user.email ?? 'Retained financial history'
                            }}</span>
                            <span
                                class="mt-2 flex flex-wrap gap-x-6 gap-y-2 pl-4 text-sm"
                            >
                                <span
                                    >Spend
                                    <strong class="font-mono">{{
                                        formatCost(user.amounts.user_charge)
                                    }}</strong></span
                                >
                                <span
                                    >Cost
                                    <strong class="font-mono">{{
                                        formatCost(user.amounts.provider_cost)
                                    }}</strong></span
                                >
                                <span
                                    >Profit
                                    <strong class="font-mono">{{
                                        formatCost(user.amounts.profit)
                                    }}</strong></span
                                >
                                <span class="text-muted-foreground"
                                    >{{ user.amounts.generations }} succeeded /
                                    {{ user.amounts.failed }} failed · expand
                                    daily details</span
                                >
                            </span>
                        </summary>
                        <div class="overflow-x-auto border-t bg-muted/20">
                            <Table class="text-left">
                                <TableCaption class="sr-only">
                                    Daily totals for
                                    {{ user.name }}
                                </TableCaption>
                                <TableHeader
                                    class="text-xs text-muted-foreground"
                                >
                                    <TableRow>
                                        <TableHead scope="col"
                                            >Date (UTC)</TableHead
                                        >
                                        <TableHead scope="col"
                                            >Succeeded / failed</TableHead
                                        >
                                        <TableHead scope="col"
                                            >Credit spend</TableHead
                                        >
                                        <TableHead scope="col"
                                            >Provider cost</TableHead
                                        >
                                        <TableHead scope="col"
                                            >Profit</TableHead
                                        >
                                    </TableRow>
                                </TableHeader>
                                <TableBody>
                                    <TableRow
                                        v-for="day in user.days"
                                        :key="day.date"
                                    >
                                        <TableHead
                                            scope="row"
                                            class="font-medium"
                                        >
                                            {{ day.date }}
                                        </TableHead>
                                        <TableCell>
                                            {{ day.amounts.generations }} /
                                            {{ day.amounts.failed }}
                                        </TableCell>
                                        <TableCell class="font-mono">
                                            {{
                                                formatCost(
                                                    day.amounts.user_charge,
                                                )
                                            }}
                                        </TableCell>
                                        <TableCell class="font-mono">
                                            {{
                                                formatCost(
                                                    day.amounts.provider_cost,
                                                )
                                            }}
                                        </TableCell>
                                        <TableCell class="font-mono">
                                            {{ formatCost(day.amounts.profit) }}
                                        </TableCell>
                                    </TableRow>
                                </TableBody>
                            </Table>
                        </div>
                    </details>
                </div>
                <nav
                    v-if="users.last_page > 1"
                    aria-label="User report pages"
                    class="flex items-center justify-between"
                >
                    <Button
                        variant="outline"
                        :disabled="!users.prev_page_url"
                        as-child
                        ><Link
                            v-if="users.prev_page_url"
                            :href="users.prev_page_url"
                            preserve-scroll
                            >Previous</Link
                        ><span v-else>Previous</span></Button
                    >
                    <span class="text-sm text-muted-foreground"
                        >{{ users.current_page }} / {{ users.last_page }}</span
                    >
                    <Button
                        variant="outline"
                        :disabled="!users.next_page_url"
                        as-child
                        ><Link
                            v-if="users.next_page_url"
                            :href="users.next_page_url"
                            preserve-scroll
                            >Next</Link
                        ><span v-else>Next</span></Button
                    >
                </nav>
            </section>
            <p class="text-xs text-muted-foreground">
                Amounts retain their full precision. Failed generations are
                free. Requests rejected before acceptance have no provider
                completion date and do not appear in these daily totals.
            </p>
        </div>
    </div>
</template>
