<script setup lang="ts">
import { Form, Head, Link, usePage } from '@inertiajs/vue3';
import { AlertTriangle, ArrowRight, CircleCheck, CircleX } from '@lucide/vue';
import { computed } from 'vue';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { reconcile, summary } from '@/routes/supplier-reconciliation';
import { edit as mappingEdit } from '@/routes/supplier-reconciliation/mapping';
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
    <Head title="Check — Supplier reconciliation" />

    <StepNav :run="run" current="check" />

    <h1 class="text-2xl font-semibold tracking-tight">Before reconciling</h1>
    <p class="text-muted-foreground mt-1">
        A quick check of what was read from both files.
    </p>

    <div class="mt-6 grid gap-6 lg:grid-cols-2">
        <section
            v-for="entry in sides"
            :key="entry.side"
            class="bg-background rounded-xl border p-5"
        >
            <h2 class="font-medium">{{ entry.title }}</h2>
            <p class="mt-1 text-2xl font-semibold">
                {{ report.sides[entry.side].transactions.toLocaleString() }}
                <span class="text-muted-foreground text-base font-normal"
                    >lines to reconcile</span
                >
            </p>

            <ul class="mt-4 flex flex-wrap gap-4 text-sm">
                <li
                    v-for="(present, field) in report.sides[entry.side].fields"
                    :key="field"
                    class="flex items-center gap-1"
                >
                    <CircleCheck
                        v-if="present"
                        class="size-4 text-emerald-600"
                    />
                    <CircleX v-else class="size-4 text-rose-600" />
                    {{ fieldLabels[field] }}
                </li>
            </ul>

            <ul class="text-muted-foreground mt-4 space-y-1 text-sm">
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
                <summary
                    class="cursor-pointer text-amber-700 dark:text-amber-400"
                >
                    {{ report.sides[entry.side].row_issues.length }} row(s) with
                    values that could not be read
                </summary>
                <ul class="mt-2 space-y-1">
                    <li
                        v-for="issue in report.sides[entry.side].row_issues"
                        :key="issue.row"
                    >
                        <span class="font-medium">Row {{ issue.row }}:</span>
                        {{ issue.issues.join(' ') }}
                    </li>
                </ul>
            </details>
        </section>
    </div>

    <div
        v-if="report.blocking.length"
        class="mt-6 rounded-xl border border-rose-300 bg-rose-50 p-5 dark:border-rose-900 dark:bg-rose-950"
    >
        <h2
            class="flex items-center gap-2 font-medium text-rose-900 dark:text-rose-200"
        >
            <CircleX class="size-5" />
            Review required before reconciliation
        </h2>
        <ul
            class="mt-2 list-disc space-y-1 pl-6 text-sm text-rose-900 dark:text-rose-200"
        >
            <li v-for="message in report.blocking" :key="message">
                {{ message }}
            </li>
        </ul>
    </div>

    <div
        v-if="report.warnings.length"
        class="mt-6 rounded-xl border border-amber-300 bg-amber-50 p-5 dark:border-amber-900 dark:bg-amber-950"
    >
        <h2
            class="flex items-center gap-2 font-medium text-amber-900 dark:text-amber-200"
        >
            <AlertTriangle class="size-5" />
            Worth checking
        </h2>
        <ul
            class="mt-2 list-disc space-y-1 pl-6 text-sm text-amber-900 dark:text-amber-200"
        >
            <li v-for="message in report.warnings" :key="message">
                {{ message }}
            </li>
        </ul>
    </div>

    <div class="mt-8 flex flex-wrap items-center justify-between gap-4">
        <Button variant="outline" as-child>
            <Link :href="mappingEdit(run.id)">Adjust the mapping</Link>
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
            class="flex flex-col items-end gap-2"
        >
            <p
                v-if="report.ready"
                class="flex items-center gap-1 text-sm text-emerald-700 dark:text-emerald-400"
            >
                <CircleCheck class="size-4" /> Ready to reconcile
            </p>
            <Button
                type="submit"
                size="lg"
                :disabled="!report.ready || processing"
            >
                <template v-if="processing">Reconciling…</template>
                <template v-else>Reconcile</template>
                <ArrowRight />
            </Button>
            <InputError :message="reconcileError" />
        </Form>
    </div>
</template>
