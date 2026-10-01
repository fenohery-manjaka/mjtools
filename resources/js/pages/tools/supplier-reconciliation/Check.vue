<script setup lang="ts">
import { Form, Head, Link, usePage } from '@inertiajs/vue3';
import {
    ArrowRight,
    CircleCheck,
    CircleX,
    LoaderCircle,
    TriangleAlert,
} from '@lucide/vue';
import { computed } from 'vue';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { reconcile, summary } from '@/routes/supplier-reconciliation';
import { edit as mappingEdit } from '@/routes/supplier-reconciliation/mapping';
import PageHeading from '@/tools/supplier-reconciliation/components/PageHeading.vue';
import StepNav from '@/tools/supplier-reconciliation/components/StepNav.vue';
import type {
    PreflightReport,
    Run,
    Side,
} from '@/tools/supplier-reconciliation/types';

defineProps<{ run: Run; report: PreflightReport }>();

const page = usePage();
const reconcileError = computed(
    () => page.props.errors?.reconcile as string | undefined,
);

const sides: { side: Side; title: string }[] = [
    { side: 'statement', title: 'Supplier statement' },
    { side: 'ledger', title: 'Ledger' },
];

const fieldLabels: Record<string, string> = {
    reference: 'Reference',
    date: 'Date',
    amount: 'Amount',
};
</script>

<template>
    <Head title="Check — Supplier Statement Checker" />

    <StepNav :run="run" current="check" />

    <PageHeading title="Before reconciling">
        A quick check of what was read from both files. Nothing is matched until
        you start the reconciliation.
    </PageHeading>

    <div class="mt-8 grid gap-6 lg:grid-cols-2">
        <section
            v-for="entry in sides"
            :key="entry.side"
            class="bg-card rounded-xl border p-6 shadow-xs"
        >
            <h2
                class="text-muted-foreground text-xs font-medium tracking-[0.14em] uppercase"
            >
                {{ entry.title }}
            </h2>
            <p class="mt-2 flex items-baseline gap-2">
                <span class="figure text-4xl font-medium">{{
                    report.sides[entry.side].transactions.toLocaleString()
                }}</span>
                <span class="text-muted-foreground">lines to reconcile</span>
            </p>

            <ul class="mt-5 flex flex-wrap gap-2 text-sm">
                <li
                    v-for="(present, field) in report.sides[entry.side].fields"
                    :key="field"
                    class="flex items-center gap-1.5 rounded-full px-2.5 py-1"
                    :class="
                        present
                            ? 'bg-success-soft text-success-strong'
                            : 'bg-danger-soft text-danger-strong'
                    "
                >
                    <CircleCheck v-if="present" class="size-4" />
                    <CircleX v-else class="size-4" />
                    {{ fieldLabels[field] }}
                </li>
            </ul>

            <ul
                class="text-muted-foreground mt-5 space-y-1.5 border-t pt-4 text-sm"
            >
                <li
                    v-for="convention in report.sides[entry.side].conventions"
                    :key="convention"
                >
                    {{ convention }}
                </li>
                <li v-if="report.sides[entry.side].filtered_out">
                    {{ report.sides[entry.side].filtered_out }} lines of other
                    suppliers left out
                </li>
                <li v-if="report.sides[entry.side].ignored_text_rows">
                    {{ report.sides[entry.side].ignored_text_rows }} text-only
                    lines ignored (no reference, date or amount)
                </li>
            </ul>

            <details
                v-if="report.sides[entry.side].row_issues.length"
                class="mt-4 text-sm"
            >
                <summary class="text-warning-strong cursor-pointer font-medium">
                    {{ report.sides[entry.side].row_issues.length }} row(s) with
                    values that could not be read
                </summary>
                <ul class="mt-2 space-y-1">
                    <li
                        v-for="issue in report.sides[entry.side].row_issues"
                        :key="issue.row"
                    >
                        <span class="figure font-medium"
                            >Row {{ issue.row }}:</span
                        >
                        {{ issue.issues.join(' ') }}
                    </li>
                </ul>
            </details>
        </section>
    </div>

    <div
        v-if="report.blocking.length"
        class="bg-danger-soft text-danger-strong border-danger/40 mt-6 rounded-xl border p-5"
        role="alert"
    >
        <h2 class="flex items-center gap-2 font-semibold">
            <CircleX class="size-5" />
            Review required before reconciliation
        </h2>
        <ul class="mt-2 list-disc space-y-1 pl-7 text-sm">
            <li v-for="message in report.blocking" :key="message">
                {{ message }}
            </li>
        </ul>
    </div>

    <div
        v-if="report.warnings.length"
        class="bg-warning-soft text-warning-strong border-warning/40 mt-6 rounded-xl border p-5"
    >
        <h2 class="flex items-center gap-2 font-semibold">
            <TriangleAlert class="size-5" />
            Worth checking
        </h2>
        <ul class="mt-2 list-disc space-y-1 pl-7 text-sm">
            <li v-for="message in report.warnings" :key="message">
                {{ message }}
            </li>
        </ul>
    </div>

    <div
        class="mt-8 flex flex-wrap items-center justify-between gap-4 border-t pt-6"
    >
        <Button variant="outline" as-child>
            <Link :href="mappingEdit(run.id)">Adjust the columns</Link>
        </Button>

        <Button v-if="run.reconciled" as-child size="lg">
            <Link :href="summary(run.id)">
                View the results
                <ArrowRight />
            </Link>
        </Button>
        <Form
            v-else
            v-bind="reconcile.form(run.id)"
            v-slot="{ processing }"
            class="flex flex-wrap items-center justify-end gap-4"
        >
            <p
                v-if="report.ready"
                class="text-success-strong flex items-center gap-1.5 text-sm font-medium"
            >
                <CircleCheck class="size-4" /> Ready to reconcile
            </p>
            <Button
                type="submit"
                size="lg"
                :disabled="!report.ready || processing"
            >
                <template v-if="processing">
                    <LoaderCircle class="animate-spin" /> Reconciling…
                </template>
                <template v-else>Reconcile <ArrowRight /></template>
            </Button>
            <InputError class="w-full text-right" :message="reconcileError" />
        </Form>
    </div>
</template>
