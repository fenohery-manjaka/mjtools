<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { ArrowRight, CircleCheck, Download } from '@lucide/vue';
import { computed } from 'vue';
import { Button } from '@/components/ui/button';
import { exportMethod, review } from '@/routes/supplier-reconciliation';
import DeleteRunButton from '@/tools/supplier-reconciliation/components/DeleteRunButton.vue';
import BalanceStatus from '@/tools/supplier-reconciliation/components/BalanceStatus.vue';
import SaveSupplierIntent from '@/tools/supplier-reconciliation/components/SaveSupplierIntent.vue';
import StepNav from '@/tools/supplier-reconciliation/components/StepNav.vue';
import {
    dotClasses,
    edgeClasses,
    toneOf,
} from '@/tools/supplier-reconciliation/tones';
import type {
    BalanceResult,
    Intent,
    Run,
    Summary,
} from '@/tools/supplier-reconciliation/types';

const props = defineProps<{
    run: Run;
    summary: Summary;
    balance: BalanceResult | null;
    intent: Intent;
}>();

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
            status: 'missing_in_ledger',
            ...props.summary.amounts.missing_invoices,
        },
        {
            label: 'Credits missing in ledger',
            status: 'missing_in_ledger',
            ...props.summary.amounts.missing_credits,
        },
        {
            label: 'Amount differences',
            status: 'amount_mismatch',
            ...props.summary.amounts.amount_differences,
        },
        {
            label: 'Ledger-only lines',
            status: 'ledger_only',
            ...props.summary.amounts.ledger_only,
        },
    ].filter((amount) => amount.count > 0),
);

const decided = computed(
    () =>
        props.summary.resolutions.confirmed +
        props.summary.resolutions.manual_match +
        props.summary.resolutions.rejected,
);

// Empty cells completing the last row of tiles (2 columns on phones, 4 above).
const fillers = computed(() => {
    const tiles = categories.value.length + 1;

    return Array.from({ length: (4 - (tiles % 4)) % 4 }, (_, index) =>
        index < (2 - (tiles % 2)) % 2 ? 'block' : 'hidden sm:block',
    );
});

const clearedWidth = computed(
    () =>
        `${Math.min(100, Math.max(0, props.summary.cleared_automatically_percent))}%`,
);
</script>

<template>
    <Head title="Summary — Supplier Statement Checker" />

    <StepNav :run="run" current="summary" />

    <section class="bg-card overflow-hidden rounded-xl border shadow-xs">
        <div class="p-6 sm:p-10">
            <p
                class="text-success-strong flex items-center gap-1.5 text-sm font-medium"
            >
                <CircleCheck class="size-4" />
                Reconciliation complete
            </p>
            <p class="text-muted-foreground mt-2 text-sm">
                <span class="figure text-foreground font-medium">{{
                    summary.analyzed_lines.toLocaleString()
                }}</span>
                lines analysed ·
                <span class="figure">{{ summary.statement_lines }}</span> on the
                statement,
                <span class="figure">{{ summary.ledger_lines }}</span> in the
                ledger<template v-if="summary.excluded_lines"
                    >,
                    <span class="figure">{{ summary.excluded_lines }}</span>
                    balance/total lines set aside</template
                >
            </p>

            <h1
                class="font-display mt-6 max-w-3xl text-4xl leading-[1.1] font-semibold tracking-tight sm:text-5xl"
            >
                <template v-if="summary.engine_attention_items === 0">
                    Nothing needs your attention.
                </template>
                <template v-else>
                    <span class="figure">{{
                        summary.engine_attention_items.toLocaleString()
                    }}</span>
                    {{
                        summary.engine_attention_items === 1
                            ? 'item needs'
                            : 'items need'
                    }}
                    your attention instead of
                    <span class="figure">{{
                        summary.analyzed_lines.toLocaleString()
                    }}</span
                    >.
                </template>
            </h1>

            <div class="mt-8 max-w-2xl">
                <div
                    class="bg-warning/35 flex h-2.5 overflow-hidden rounded-full"
                    role="img"
                    :aria-label="`${summary.cleared_automatically_percent}% of lines cleared automatically`"
                >
                    <span
                        class="bg-success h-full rounded-r-full"
                        :style="{ width: clearedWidth }"
                    />
                </div>
                <p class="mt-3">
                    <span class="figure text-success-strong text-lg font-medium"
                        >{{ summary.cleared_automatically_percent }}%</span
                    >
                    of lines cleared automatically
                    <span class="text-muted-foreground text-sm">
                        ({{ summary.matched_automatically.items }} certain
                        matches)
                    </span>
                </p>
                <p class="text-muted-foreground mt-1 text-xs">
                    This is the share of lines you no longer need to check by
                    hand — not an accuracy score.
                </p>
            </div>
        </div>

        <div class="bg-border grid grid-cols-2 gap-px border-t sm:grid-cols-4">
            <div class="bg-card border-l-success border-l-4 p-5">
                <p class="figure text-3xl font-medium">
                    {{ summary.matched_automatically.items }}
                </p>
                <p class="text-muted-foreground mt-1 text-sm">
                    Matched automatically
                </p>
            </div>
            <Link
                v-for="category in categories"
                :key="category.key"
                :href="review(run.id, { query: { filter: category.key } })"
                class="group bg-card hover:bg-accent/60 border-l-4 p-5 transition-colors"
                :class="edgeClasses[toneOf(category.key)]"
            >
                <p class="figure text-3xl font-medium">{{ category.count }}</p>
                <p
                    class="text-muted-foreground group-hover:text-foreground mt-1 flex items-center gap-1 text-sm"
                >
                    {{ category.label }}
                    <ArrowRight
                        class="size-3.5 opacity-0 transition-opacity group-hover:opacity-100"
                    />
                </p>
            </Link>
            <div
                v-for="(display, index) in fillers"
                :key="`filler-${index}`"
                class="bg-card"
                :class="display"
                aria-hidden="true"
            />
        </div>
    </section>

    <div class="mt-6 grid gap-6 lg:grid-cols-[1fr_auto]">
        <section
            v-if="amounts.length"
            class="bg-card rounded-xl border p-6 shadow-xs"
        >
            <h2 class="flex items-baseline justify-between gap-3 font-semibold">
                Amounts involved in open exceptions
                <span
                    v-if="run.currency"
                    class="figure text-muted-foreground text-xs font-normal"
                    >{{ run.currency }}</span
                >
            </h2>
            <dl class="mt-4 divide-y">
                <div
                    v-for="amount in amounts"
                    :key="amount.label"
                    class="flex items-center justify-between gap-4 py-2.5 text-sm"
                >
                    <dt class="flex items-center gap-2">
                        <span
                            class="size-2 rounded-full"
                            :class="dotClasses[toneOf(amount.status)]"
                        />
                        {{ amount.label }}
                        <span class="text-muted-foreground figure"
                            >({{ amount.count }})</span
                        >
                    </dt>
                    <dd class="figure font-medium">
                        {{ amount.total }}
                        <span
                            v-if="run.currency"
                            class="text-muted-foreground text-xs"
                            >{{ run.currency }}</span
                        >
                    </dd>
                </div>
            </dl>
            <p class="text-muted-foreground mt-3 text-xs">
                Shown per category: these amounts are not added together.
            </p>
        </section>

        <section
            class="bg-card flex flex-col justify-between gap-5 rounded-xl border p-6 shadow-xs lg:w-80"
            :class="amounts.length ? '' : 'lg:col-span-2 lg:w-auto'"
        >
            <div>
                <h2 class="font-semibold">Next step</h2>
                <p
                    v-if="decided > 0 || summary.resolutions.deferred > 0"
                    class="text-muted-foreground mt-1 text-sm"
                >
                    Your review so far:
                    {{ summary.resolutions.confirmed }} confirmed,
                    {{ summary.resolutions.manual_match }} matched manually,
                    {{ summary.resolutions.rejected }} rejected,
                    {{ summary.resolutions.deferred }} left for review —
                    <strong class="text-foreground font-medium"
                        >{{ summary.attention_items }} still open</strong
                    >.
                </p>
                <p v-else class="text-muted-foreground mt-1 text-sm">
                    Go through the exceptions, then export the result with your
                    decisions.
                </p>
            </div>
            <div class="flex flex-col gap-2">
                <Button v-if="summary.attention_items > 0" as-child size="lg">
                    <Link :href="review(run.id)">
                        Review {{ summary.attention_items }}
                        {{ summary.attention_items === 1 ? 'item' : 'items' }}
                        <ArrowRight />
                    </Link>
                </Button>
                <Button v-else as-child size="lg" variant="outline">
                    <Link
                        :href="review(run.id, { query: { filter: 'matched' } })"
                    >
                        See the matches
                    </Link>
                </Button>
                <div class="grid grid-cols-2 gap-2">
                    <Button as-child variant="outline">
                        <a
                            :href="
                                exportMethod.url({
                                    run: run.id,
                                    format: 'xlsx',
                                })
                            "
                        >
                            <Download /> Excel
                        </a>
                    </Button>
                    <Button as-child variant="outline">
                        <a
                            :href="
                                exportMethod.url({ run: run.id, format: 'csv' })
                            "
                        >
                            <Download /> CSV
                        </a>
                    </Button>
                </div>
            </div>
        </section>
    </div>

    <BalanceStatus
        v-if="balance"
        class="mt-6"
        :balance="balance"
        :currency="run.currency"
    />

    <SaveSupplierIntent
        class="mt-6"
        :run-id="run.id"
        :intent="intent"
        :analyzed-lines="summary.analyzed_lines"
        :reviewed-items="summary.engine_attention_items"
    />

    <div class="mt-6">
        <DeleteRunButton :run-id="run.id" />
    </div>
</template>
