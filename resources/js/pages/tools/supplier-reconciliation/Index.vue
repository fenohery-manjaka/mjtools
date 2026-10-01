<script setup lang="ts">
import { Form, Head } from '@inertiajs/vue3';
import { ArrowRight, Ban, FlaskConical } from '@lucide/vue';
import { Button } from '@/components/ui/button';
import { sample, store } from '@/routes/supplier-reconciliation/runs';
import PrivacyNote from '@/tools/supplier-reconciliation/components/PrivacyNote.vue';
import ResultSpecimen from '@/tools/supplier-reconciliation/components/ResultSpecimen.vue';
import type { Limits } from '@/tools/supplier-reconciliation/types';

defineProps<{ retentionHours: number; limits: Limits }>();

const steps = [
    {
        title: 'Upload both sides',
        text: 'Your supplier statement and your AP / supplier ledger, as CSV or Excel (XLSX). Odd headers and total lines are fine.',
    },
    {
        title: 'We clear what is certain',
        text: 'References, amounts and dates are compared, tolerating formatting differences — never guessing.',
    },
    {
        title: 'You review only the differences',
        text: 'Possible matches, missing invoices and credits, amount mismatches and duplicates — each one explained.',
    },
];

const nevers = [
    'Change your accounts or your files',
    'Force a match when several are possible',
    'Pick which side is right when amounts differ',
    'Use an AI to guess correspondences',
];
</script>

<template>
    <Head title="Supplier Statement Checker" />

    <section
        class="grid items-center gap-10 pb-6 lg:grid-cols-[1.2fr_1fr] lg:pt-6"
    >
        <div>
            <p
                class="bg-success-soft text-success-strong inline-flex rounded-full px-3 py-1 text-xs font-medium"
            >
                Free · no account needed
            </p>
            <h1
                class="font-display mt-5 text-4xl leading-[1.08] font-semibold tracking-tight sm:text-5xl"
            >
                Compare your supplier statement with your ledger and review only
                the differences.
            </h1>
            <p class="text-muted-foreground mt-5 max-w-xl text-lg">
                Upload your supplier statement and ledger. See what matches and
                what needs attention — with the reason for every result.
            </p>

            <div class="mt-8 flex flex-wrap items-center gap-3">
                <Form v-bind="store.form()" v-slot="{ processing }">
                    <Button
                        size="lg"
                        type="submit"
                        class="h-11 px-5 text-base"
                        :disabled="processing"
                    >
                        Reconcile a statement
                        <ArrowRight />
                    </Button>
                </Form>
                <Form v-bind="sample.form()" v-slot="{ processing }">
                    <Button
                        size="lg"
                        type="submit"
                        variant="outline"
                        class="bg-card h-11 px-5 text-base"
                        :disabled="processing"
                    >
                        <FlaskConical />
                        Try with sample files
                    </Button>
                </Form>
            </div>
            <p class="text-muted-foreground mt-3 text-xs">
                CSV or XLSX · up to {{ limits.max_file_mb }} MB and
                {{ limits.max_rows.toLocaleString() }} rows per file · deleted
                after {{ retentionHours }} h
            </p>
        </div>

        <ResultSpecimen class="mx-auto w-full max-w-md" />
    </section>

    <section class="mt-14">
        <h2 class="font-display text-2xl font-semibold tracking-tight">
            How it works
        </h2>
        <ol class="mt-6 grid gap-5 md:grid-cols-3">
            <li
                v-for="(step, index) in steps"
                :key="step.title"
                class="bg-card rounded-xl border p-6"
            >
                <span
                    class="figure bg-accent text-primary flex size-8 items-center justify-center rounded-full text-sm font-medium"
                    >{{ index + 1 }}</span
                >
                <h3 class="mt-4 font-semibold">{{ step.title }}</h3>
                <p class="text-muted-foreground mt-1 text-sm leading-relaxed">
                    {{ step.text }}
                </p>
            </li>
        </ol>
    </section>

    <section class="mt-10 grid gap-5 lg:grid-cols-2">
        <PrivacyNote :retention-hours="retentionHours" />

        <div class="bg-card rounded-xl border p-6">
            <div class="flex items-center gap-2 font-semibold">
                <Ban class="text-danger size-5" />
                What the checker will never do
            </div>
            <ul class="text-muted-foreground mt-4 space-y-2.5 text-sm">
                <li v-for="never in nevers" :key="never" class="flex gap-2.5">
                    <span
                        class="bg-muted-foreground/50 mt-2 size-1.5 shrink-0 rounded-full"
                    />
                    {{ never }}
                </li>
            </ul>
        </div>
    </section>

    <blockquote
        class="font-display text-muted-foreground mx-auto mt-14 max-w-2xl text-center text-xl leading-relaxed"
    >
        “A missing match is better than a wrong one. When several
        interpretations are possible, the checker asks you instead of deciding.”
    </blockquote>
</template>
