<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { ArrowRight, Download } from '@lucide/vue';
import { computed } from 'vue';
import { Button } from '@/components/ui/button';
import { exportMethod, review } from '@/routes/supplier-reconciliation';
import DeleteRunButton from '@/tools/supplier-reconciliation/components/DeleteRunButton.vue';
import StepNav from '@/tools/supplier-reconciliation/components/StepNav.vue';
import type { Run, Summary } from '@/tools/supplier-reconciliation/types';

const props = defineProps<{ run: Run; summary: Summary }>();

const categories = computed(() => {
    const counts = props.summary.engine_counts;

    return [
        {
            key: 'possible_match',
            label: 'Possible matches',
            count: counts.possible_match,
        },
        { key: 'ambiguous', label: 'Ambiguous', count: counts.ambiguous },
        {
            key: 'missing_in_ledger',
            label: 'Missing in ledger',
            count: counts.missing_in_ledger,
        },
        { key: 'ledger_only', label: 'Ledger only', count: counts.ledger_only },
        {
            key: 'amount_mismatch',
            label: 'Amount mismatches',
            count: counts.amount_mismatch,
        },
        {
            key: 'duplicate_suspected',
            label: 'Duplicates suspected',
            count: counts.duplicate_suspected,
        },
        {
            key: 'review_required',
            label: 'Need review',
            count: counts.review_required,
        },
    ].filter((category) => category.count > 0);
});

const amounts = computed(() =>
    [
        {
            label: 'Invoices missing in ledger',
            ...props.summary.amounts.missing_invoices,
        },
        {
            label: 'Credits missing in ledger',
            ...props.summary.amounts.missing_credits,
        },
        {
            label: 'Amount differences',
            ...props.summary.amounts.amount_differences,
        },
        { label: 'Ledger-only lines', ...props.summary.amounts.ledger_only },
    ].filter((amount) => amount.count > 0),
);

const decided = computed(
    () =>
        props.summary.resolutions.confirmed +
        props.summary.resolutions.manual_match +
        props.summary.resolutions.rejected,
);
</script>

<template>
    <Head title="Summary — Supplier reconciliation" />

    <StepNav :run="run" current="summary" />

    <section class="bg-background rounded-xl border p-6 sm:p-8">
        <p class="text-muted-foreground text-sm font-medium">
            Reconciliation complete
        </p>
        <p class="mt-1 text-lg">
            <span class="font-semibold">{{
                summary.analyzed_lines.toLocaleString()
            }}</span>
            lines analysed
            <span class="text-muted-foreground text-sm">
                ({{ summary.statement_lines }} on the statement,
                {{ summary.ledger_lines }} in the ledger<template
                    v-if="summary.excluded_lines"
                    >, {{ summary.excluded_lines }} balance/total lines set
                    aside</template
                >)
            </span>
        </p>

        <h1 class="mt-6 text-3xl font-semibold tracking-tight sm:text-4xl">
            <template v-if="summary.engine_attention_items === 0">
                Nothing needs your attention.
            </template>
            <template v-else>
                {{ summary.engine_attention_items }}
                {{
                    summary.engine_attention_items === 1
                        ? 'item needs'
                        : 'items need'
                }}
                your attention instead of
                {{ summary.analyzed_lines.toLocaleString() }}.
            </template>
        </h1>
        <p class="mt-3 text-emerald-700 dark:text-emerald-400">
            <span class="font-semibold"
                >{{ summary.cleared_automatically_percent }}%</span
            >
            of lines cleared automatically
            <span class="text-muted-foreground text-sm">
                ({{ summary.matched_automatically.items }} certain matches)
            </span>
        </p>
        <p class="text-muted-foreground mt-1 text-xs">
            This is the share of lines you no longer need to check by hand — not
            an accuracy score.
        </p>

        <div class="mt-8 grid grid-cols-2 gap-3 sm:grid-cols-4">
            <div
                class="rounded-lg border bg-emerald-50 p-4 dark:bg-emerald-950/40"
            >
                <p class="text-2xl font-semibold">
                    {{ summary.matched_automatically.items }}
                </p>
                <p class="text-muted-foreground text-sm">
                    Matched automatically
                </p>
            </div>
            <Link
                v-for="category in categories"
                :key="category.key"
                :href="review(run.id, { query: { filter: category.key } })"
                class="hover:bg-accent rounded-lg border p-4"
            >
                <p class="text-2xl font-semibold">{{ category.count }}</p>
                <p class="text-muted-foreground text-sm">
                    {{ category.label }}
                </p>
            </Link>
        </div>

        <div v-if="amounts.length" class="mt-8">
            <h2 class="text-sm font-medium">
                Amounts involved in open exceptions
            </h2>
            <dl class="mt-2 grid gap-2 text-sm sm:grid-cols-2">
                <div
                    v-for="amount in amounts"
                    :key="amount.label"
                    class="flex justify-between rounded-md border px-3 py-2"
                >
                    <dt class="text-muted-foreground">
                        {{ amount.label }} ({{ amount.count }})
                    </dt>
                    <dd class="font-medium tabular-nums">{{ amount.total }}</dd>
                </div>
            </dl>
            <p class="text-muted-foreground mt-2 text-xs">
                Shown per category: these amounts are not added together.
            </p>
        </div>

        <div class="mt-8 flex flex-wrap items-center gap-3">
            <Button v-if="summary.attention_items > 0" as-child size="lg">
                <Link :href="review(run.id)">
                    Review {{ summary.attention_items }}
                    {{ summary.attention_items === 1 ? 'item' : 'items' }}
                    <ArrowRight />
                </Link>
            </Button>
            <Button v-else as-child size="lg" variant="outline">
                <Link :href="review(run.id, { query: { filter: 'matched' } })">
                    See the matches
                </Link>
            </Button>
            <Button as-child variant="outline">
                <a :href="exportMethod.url({ run: run.id, format: 'xlsx' })">
                    <Download /> Export Excel
                </a>
            </Button>
            <Button as-child variant="outline">
                <a :href="exportMethod.url({ run: run.id, format: 'csv' })">
                    <Download /> Export CSV
                </a>
            </Button>
        </div>

        <p
            v-if="decided > 0 || summary.resolutions.deferred > 0"
            class="text-muted-foreground mt-4 text-sm"
        >
            Your review so far: {{ summary.resolutions.confirmed }} confirmed,
            {{ summary.resolutions.manual_match }} matched manually,
            {{ summary.resolutions.rejected }} rejected,
            {{ summary.resolutions.deferred }} left for review —
            {{ summary.attention_items }} still open.
        </p>
    </section>

    <section class="bg-background mt-6 rounded-xl border p-5 text-sm">
        <p class="font-medium">
            You reconciled {{ summary.analyzed_lines.toLocaleString() }} lines
            and only needed to review {{ summary.engine_attention_items }}. Do
            this every month?
        </p>
        <p class="text-muted-foreground mt-1">
            Saving suppliers and their mappings, so you don't have to configure
            them again, is on our roadmap.
        </p>
    </section>

    <div class="mt-6">
        <DeleteRunButton :run-id="run.id" />
    </div>
</template>
