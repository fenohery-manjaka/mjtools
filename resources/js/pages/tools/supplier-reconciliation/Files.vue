<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { ArrowRight, FileSpreadsheet } from '@lucide/vue';
import { computed } from 'vue';
import { Button } from '@/components/ui/button';
import { edit as mappingEdit } from '@/routes/supplier-reconciliation/mapping';
import DeleteRunButton from '@/tools/supplier-reconciliation/components/DeleteRunButton.vue';
import FileDropzone from '@/tools/supplier-reconciliation/components/FileDropzone.vue';
import StepNav from '@/tools/supplier-reconciliation/components/StepNav.vue';
import TablePreview from '@/tools/supplier-reconciliation/components/TablePreview.vue';
import type {
    FileSummary,
    Limits,
    Run,
    Side,
} from '@/tools/supplier-reconciliation/types';

const props = defineProps<{
    run: Run;
    files: Record<Side, FileSummary | null>;
    limits: Limits;
}>();

const sides: { side: Side; title: string; description: string }[] = [
    {
        side: 'statement',
        title: 'Supplier statement',
        description: 'The statement of account sent by your supplier.',
    },
    {
        side: 'ledger',
        title: 'Your ledger',
        description:
            'Your AP / supplier ledger export for this supplier (or all suppliers — you can filter later).',
    },
];

const ready = computed(
    () => props.files.statement !== null && props.files.ledger !== null,
);

function sizeLabel(bytes: number): string {
    return bytes >= 1_048_576
        ? `${(bytes / 1_048_576).toFixed(1)} MB`
        : `${Math.max(1, Math.round(bytes / 1024))} KB`;
}

function detailsLabel(details: Record<string, string>): string {
    return Object.entries(details)
        .map(([key, value]) => `${key}: ${value}`)
        .join(' · ');
}
</script>

<template>
    <Head title="Files — Supplier reconciliation" />

    <StepNav :run="run" current="files" />

    <h1 class="text-2xl font-semibold tracking-tight">Import both files</h1>
    <p class="text-muted-foreground mt-1">
        Upload the supplier statement and your ledger. You will check the
        columns in the next step.
    </p>

    <div class="mt-6 grid gap-6 lg:grid-cols-2">
        <section
            v-for="entry in sides"
            :key="entry.side"
            class="bg-background rounded-xl border p-5"
        >
            <h2 class="font-medium">{{ entry.title }}</h2>
            <p class="text-muted-foreground mb-4 text-sm">
                {{ entry.description }}
            </p>

            <template v-if="files[entry.side]">
                <div class="mb-4 flex items-start gap-3">
                    <FileSpreadsheet
                        class="text-primary mt-0.5 size-5 shrink-0"
                    />
                    <div class="min-w-0 text-sm">
                        <p class="truncate font-medium">
                            {{ files[entry.side]!.name }}
                        </p>
                        <p class="text-muted-foreground">
                            {{ files[entry.side]!.format_label }} ·
                            {{ sizeLabel(files[entry.side]!.size) }} ·
                            {{ files[entry.side]!.rows.toLocaleString() }} rows
                            detected · headers on row
                            {{ files[entry.side]!.header_row }}
                        </p>
                        <p
                            v-if="
                                Object.keys(files[entry.side]!.details).length
                            "
                            class="text-muted-foreground text-xs"
                        >
                            {{ detailsLabel(files[entry.side]!.details) }}
                        </p>
                    </div>
                </div>
                <TablePreview
                    :headers="files[entry.side]!.preview.headers"
                    :rows="files[entry.side]!.preview.rows"
                />
                <details class="mt-4">
                    <summary
                        class="text-muted-foreground cursor-pointer text-sm"
                    >
                        Replace this file
                    </summary>
                    <div class="mt-3">
                        <FileDropzone
                            :run-id="run.id"
                            :side="entry.side"
                            :max-file-mb="limits.max_file_mb"
                        />
                    </div>
                </details>
            </template>

            <FileDropzone
                v-else
                :run-id="run.id"
                :side="entry.side"
                :max-file-mb="limits.max_file_mb"
            />
        </section>
    </div>

    <div class="mt-8 flex flex-wrap items-center justify-between gap-4">
        <DeleteRunButton :run-id="run.id" />
        <Button v-if="ready" as-child size="lg">
            <Link :href="mappingEdit(run.id)">
                Check the columns
                <ArrowRight />
            </Link>
        </Button>
        <Button v-else size="lg" disabled>Upload both files to continue</Button>
    </div>
</template>
