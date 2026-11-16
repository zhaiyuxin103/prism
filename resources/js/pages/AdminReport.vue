<script setup lang="ts">
import { Form, Head, Link } from '@inertiajs/vue3';
import { CircleCheck, CircleAlert, RefreshCw } from '@lucide/vue';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { report as reportRoute } from '@/routes/admin';
import type { AdminReport, UserReportPage } from '@/types/admin-report';
import { formatCost } from '@/types/generation';

defineProps<{
    filters: { start_date: string; end_date: string };
    report: AdminReport;
    users: UserReportPage;
}>();

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

            <Form
                :action="reportRoute.url()"
                method="get"
                :options="{ preserveState: 'errors', preserveScroll: true }"
                #default="{ errors, processing }"
                class="flex flex-wrap items-start gap-4 rounded-xl border bg-card p-5"
            >
                <div class="grid gap-2">
                    <Label for="start_date">From (UTC)</Label>
                    <Input
                        id="start_date"
                        name="start_date"
                        type="date"
                        :default-value="filters.start_date"
                        required
                    />
                    <InputError :message="errors.start_date" />
                </div>
                <div class="grid gap-2">
                    <Label for="end_date">Through (UTC)</Label>
                    <Input
                        id="end_date"
                        name="end_date"
                        type="date"
                        :default-value="filters.end_date"
                        required
                    />
                    <InputError :message="errors.end_date" />
                </div>
                <Button type="submit" :disabled="processing" class="mt-6">
                    <RefreshCw
                        class="size-4"
                        :class="{ 'animate-spin': processing }"
                    />
                    {{ processing ? 'Checking…' : 'Refresh report' }}
                </Button>
                <p class="w-full text-xs text-muted-foreground">
                    Up to 92 days, inclusive. Generations belong to the UTC day
                    they completed at Pixelforge.
                </p>
            </Form>

            <div class="grid gap-4 md:grid-cols-3">
                <section
                    v-for="metric in metrics"
                    :key="metric.key"
                    class="flex flex-col gap-2 rounded-xl border bg-card p-5"
                >
                    <h2 class="text-sm font-medium text-muted-foreground">
                        {{ metric.label }}
                    </h2>
                    <p
                        class="font-mono text-xl font-semibold break-all tabular-nums"
                    >
                        {{ formatCost(report.totals[metric.key]) }}
                    </p>
                    <p class="text-xs text-muted-foreground">
                        {{ metric.description }}
                    </p>
                </section>
            </div>

            <section
                class="flex flex-col gap-3 rounded-xl border bg-card p-5"
                aria-labelledby="reconciliation-title"
            >
                <div class="flex items-center gap-2">
                    <CircleCheck
                        v-if="report.reconciliation.status === 'matched'"
                        class="size-5 text-emerald-600 dark:text-emerald-400"
                    />
                    <CircleAlert
                        v-else
                        class="size-5 text-amber-600 dark:text-amber-400"
                    />
                    <h2 id="reconciliation-title" class="font-semibold">
                        {{
                            report.reconciliation.status === 'matched'
                                ? 'Provider costs match'
                                : report.reconciliation.status === 'mismatch'
                                  ? 'Reconciliation difference found'
                                  : 'Reconciliation unavailable'
                        }}
                    </h2>
                </div>
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
                                report.reconciliation.total.provider_generations
                            }}
                            provider</strong
                        >
                    </p>
                </div>
                <p
                    v-if="report.reconciliation.status === 'mismatch'"
                    class="text-sm text-muted-foreground"
                >
                    Review the highlighted days below. Delayed results or using
                    a different provider key can cause differences. Refresh
                    after pending generations have settled.
                </p>
                <p class="text-xs leading-relaxed text-muted-foreground">
                    Compared with the usage reported by the API key configured
                    for this environment. Use a dedicated key for this database;
                    changing keys or sharing one across environments changes the
                    comparison. Differences are local minus provider. Today's
                    figures may change as generations complete.
                </p>
                <p
                    v-if="report.reconciliation.checked_at"
                    class="text-xs text-muted-foreground"
                >
                    Checked {{ report.reconciliation.checked_at }} (UTC)
                </p>
                <p
                    v-if="report.unresolved_generations"
                    class="text-sm text-muted-foreground"
                >
                    {{ report.unresolved_generations }} generation(s) in this
                    environment still have no final result, including paused
                    checks. Their final charges are not yet included.
                </p>
            </section>

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
                <div class="overflow-x-auto rounded-xl border">
                    <table
                        class="w-full text-left text-sm whitespace-nowrap [&_td]:p-3 [&_th]:p-3"
                    >
                        <caption class="sr-only">
                            Daily charges, profit and provider reconciliation in
                            USD
                        </caption>
                        <thead
                            class="bg-muted/50 text-xs text-muted-foreground"
                        >
                            <tr>
                                <th scope="col">Date (UTC)</th>
                                <th scope="col">Succeeded / failed</th>
                                <th scope="col">Credit spend</th>
                                <th scope="col">Provider cost</th>
                                <th scope="col">Profit</th>
                                <th scope="col">Pixelforge cost</th>
                                <th scope="col">Cost difference</th>
                                <th scope="col">Count difference</th>
                                <th scope="col">Check</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y">
                            <tr
                                v-for="day in report.days"
                                :key="day.date"
                                :class="{
                                    'bg-amber-50/60 dark:bg-amber-950/20':
                                        day.reconciliation?.status ===
                                        'mismatch',
                                }"
                            >
                                <th scope="row" class="font-medium">
                                    {{ day.date }}
                                </th>
                                <td>
                                    {{ day.amounts.generations }} /
                                    {{ day.amounts.failed }}
                                </td>
                                <td class="font-mono tabular-nums">
                                    {{ formatCost(day.amounts.user_charge) }}
                                </td>
                                <td class="font-mono tabular-nums">
                                    {{ formatCost(day.amounts.provider_cost) }}
                                </td>
                                <td class="font-mono tabular-nums">
                                    {{ formatCost(day.amounts.profit) }}
                                </td>
                                <td class="font-mono tabular-nums">
                                    {{
                                        day.reconciliation
                                            ? formatCost(
                                                  day.reconciliation
                                                      .provider_cost,
                                              )
                                            : '—'
                                    }}
                                </td>
                                <td class="font-mono tabular-nums">
                                    {{
                                        day.reconciliation
                                            ? formatCost(
                                                  day.reconciliation
                                                      .cost_difference,
                                              )
                                            : '—'
                                    }}
                                </td>
                                <td>
                                    {{
                                        day.reconciliation
                                            ?.generation_difference ?? '—'
                                    }}
                                </td>
                                <td>
                                    {{
                                        day.reconciliation?.status === 'matched'
                                            ? 'Matched'
                                            : day.reconciliation
                                              ? 'Difference'
                                              : 'Not checked'
                                    }}
                                </td>
                            </tr>
                        </tbody>
                    </table>
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
                <p
                    v-if="users.total === 0"
                    class="rounded-xl border border-dashed p-8 text-center text-sm text-muted-foreground"
                >
                    No completed, settled generations in this date range.
                </p>
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
                            <table
                                class="w-full text-left text-sm whitespace-nowrap [&_td]:p-3 [&_th]:p-3"
                            >
                                <caption class="sr-only">
                                    Daily totals for
                                    {{
                                        user.name
                                    }}
                                </caption>
                                <thead class="text-xs text-muted-foreground">
                                    <tr>
                                        <th scope="col">Date (UTC)</th>
                                        <th scope="col">Succeeded / failed</th>
                                        <th scope="col">Credit spend</th>
                                        <th scope="col">Provider cost</th>
                                        <th scope="col">Profit</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr
                                        v-for="day in user.days"
                                        :key="day.date"
                                    >
                                        <th scope="row" class="font-medium">
                                            {{ day.date }}
                                        </th>
                                        <td>
                                            {{ day.amounts.generations }} /
                                            {{ day.amounts.failed }}
                                        </td>
                                        <td class="font-mono">
                                            {{
                                                formatCost(
                                                    day.amounts.user_charge,
                                                )
                                            }}
                                        </td>
                                        <td class="font-mono">
                                            {{
                                                formatCost(
                                                    day.amounts.provider_cost,
                                                )
                                            }}
                                        </td>
                                        <td class="font-mono">
                                            {{ formatCost(day.amounts.profit) }}
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
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
