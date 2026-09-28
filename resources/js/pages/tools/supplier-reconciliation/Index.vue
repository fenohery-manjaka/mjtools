<script setup lang="ts">
import { Form, Head } from '@inertiajs/vue3';
import {
    ArrowRight,
    FileSpreadsheet,
    ListChecks,
    SearchCheck,
} from '@lucide/vue';
import { Button } from '@/components/ui/button';
import { store } from '@/routes/supplier-reconciliation/runs';
import PrivacyNote from '@/tools/supplier-reconciliation/components/PrivacyNote.vue';
import type { Limits } from '@/tools/supplier-reconciliation/types';

defineProps<{ retentionHours: number; limits: Limits }>();

const steps = [
    {
        icon: FileSpreadsheet,
        title: 'Upload both sides',
        text: 'Your supplier statement and your AP / supplier ledger, as CSV or Excel (XLSX).',
    },
    {
        icon: SearchCheck,
        title: 'We match what is certain',
        text: 'References, amounts and dates are compared, tolerating formatting differences — never guessing.',
    },
    {
        icon: ListChecks,
        title: 'You review only the differences',
        text: 'Possible matches, missing invoices and credits, amount mismatches and duplicates — each one explained.',
    },
];
</script>

<template>
    <Head title="Supplier Statement Reconciliation Checker" />

    <section class="mx-auto max-w-3xl py-8 text-center">
        <p
            class="text-muted-foreground mb-3 text-sm font-medium tracking-wide uppercase"
        >
            Free supplier statement reconciliation checker
        </p>
        <h1 class="text-3xl font-semibold tracking-tight sm:text-4xl">
            Compare your supplier statement with your ledger and review only the
            differences.
        </h1>
        <p class="text-muted-foreground mx-auto mt-4 max-w-2xl">
            Upload your supplier statement and ledger. See what matches and what
            needs attention. No account needed.
        </p>

        <Form v-bind="store.form()" class="mt-8" v-slot="{ processing }">
            <Button size="lg" type="submit" :disabled="processing">
                Reconcile a statement
                <ArrowRight />
            </Button>
        </Form>
        <p class="text-muted-foreground mt-3 text-xs">
            CSV or XLSX · up to {{ limits.max_file_mb }} MB and
            {{ limits.max_rows.toLocaleString() }} rows per file
        </p>
    </section>

    <section class="mx-auto mt-6 grid max-w-5xl gap-4 md:grid-cols-3">
        <div
            v-for="step in steps"
            :key="step.title"
            class="bg-background rounded-xl border p-5"
        >
            <component :is="step.icon" class="text-primary mb-3 size-6" />
            <h2 class="font-medium">{{ step.title }}</h2>
            <p class="text-muted-foreground mt-1 text-sm">{{ step.text }}</p>
        </div>
    </section>

    <section class="mx-auto mt-6 max-w-5xl">
        <PrivacyNote :retention-hours="retentionHours" />
    </section>

    <section
        class="text-muted-foreground mx-auto mt-6 max-w-3xl text-center text-sm"
    >
        A missing match is better than a wrong one: when several interpretations
        are possible, the checker asks you instead of deciding. It never changes
        your accounts.
    </section>
</template>
