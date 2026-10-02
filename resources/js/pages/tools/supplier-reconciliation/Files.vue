<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import {
    ArrowRight,
    BookOpen,
    Download,
    FileSpreadsheet,
    FlaskConical,
    ReceiptText,
} from '@lucide/vue';
import { computed, reactive } from 'vue';
import { Button } from '@/components/ui/button';
import { edit as mappingEdit } from '@/routes/supplier-reconciliation/mapping';
import { download as sampleDownload } from '@/routes/supplier-reconciliation/sample';
import DeleteRunButton from '@/tools/supplier-reconciliation/components/DeleteRunButton.vue';
import FileDropzone from '@/tools/supplier-reconciliation/components/FileDropzone.vue';
import PageHeading from '@/tools/supplier-reconciliation/components/PageHeading.vue';
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

const sides = [
    {
        side: 'statement',
        title: 'Supplier statement',
        description: 'The statement of account sent by your supplier.',
        icon: ReceiptText,
    },
    {
        side: 'ledger',
        title: 'Your ledger',
        description:
            'Your AP / supplier ledger export for this supplier (or all suppliers — you can filter later).',
        icon: BookOpen,
    },
] as const;

const usingSample = computed(
    () =>
        props.files.statement?.sample === true ||
        props.files.ledger?.sample === true,
);

// Worksheet chosen to re-read a workbook, by side ('' = the most relevant one).
const sheetChoice = reactive<Record<Side, string>>({
    statement: '',
    ledger: '',
});

const selectClass =
    'border-input bg-background dark:bg-input/30 h-9 w-full rounded-md border px-2.5 text-sm shadow-xs focus-visible:border-ring focus-visible:ring-ring/50 focus-visible:ring-[3px] outline-none';

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
    <Head title="Files — Supplier Statement Checker" />

    <StepNav :run="run" current="files" />

    <PageHeading title="Import both files">
        Upload the supplier statement and your ledger. You will check the
        columns in the next step.
    </PageHeading>

    <div
        v-if="usingSample"
        class="bg-info-soft text-info-strong border-info/30 mt-6 flex flex-wrap items-center justify-between gap-3 rounded-lg border px-4 py-3 text-sm"
    >
        <p class="flex items-center gap-2">
            <FlaskConical class="size-4 shrink-0" />
            You are using fictional sample files: an office supplier's statement
            and the matching ledger export.
        </p>
        <span class="flex gap-3 font-medium">
            <a
                :href="sampleDownload.url('statement')"
                class="inline-flex items-center gap-1 underline-offset-4 hover:underline"
                ><Download class="size-3.5" /> Statement</a
            >
            <a
                :href="sampleDownload.url('ledger')"
                class="inline-flex items-center gap-1 underline-offset-4 hover:underline"
                ><Download class="size-3.5" /> Ledger</a
            >
        </span>
    </div>

    <div class="mt-8 grid gap-6 lg:grid-cols-2">
        <section
            v-for="entry in sides"
            :key="entry.side"
            class="bg-card flex flex-col rounded-xl border p-6 shadow-xs"
        >
            <div class="flex items-start gap-3">
                <span
                    class="bg-accent text-primary flex size-9 shrink-0 items-center justify-center rounded-lg"
                >
                    <component :is="entry.icon" class="size-4.5" />
                </span>
                <div>
                    <h2 class="font-semibold">{{ entry.title }}</h2>
                    <p class="text-muted-foreground text-sm">
                        {{ entry.description }}
                    </p>
                </div>
            </div>

            <div class="mt-5 flex-1">
                <template v-if="files[entry.side]">
                    <div
                        class="bg-success-soft/60 border-success/25 mb-4 flex items-start gap-3 rounded-lg border px-3.5 py-3"
                    >
                        <FileSpreadsheet
                            class="text-success-strong mt-0.5 size-5 shrink-0"
                        />
                        <div class="min-w-0 text-sm">
                            <p class="truncate font-medium">
                                {{ files[entry.side]!.name }}
                            </p>
                            <p class="text-muted-foreground">
                                {{ files[entry.side]!.format_label }} ·
                                {{ sizeLabel(files[entry.side]!.size) }} ·
                                <span class="figure text-foreground">{{
                                    files[entry.side]!.rows.toLocaleString()
                                }}</span>
                                rows detected · headers on row
                                <span class="figure text-foreground">{{
                                    files[entry.side]!.header_row
                                }}</span>
                            </p>
                            <p
                                v-if="
                                    Object.keys(files[entry.side]!.details)
                                        .length
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
                    <details class="group mt-4">
                        <summary
                            class="text-muted-foreground hover:text-foreground cursor-pointer text-sm"
                        >
                            <template
                                v-if="
                                    (files[entry.side]!.sheets ?? []).length > 1
                                "
                                >Replace this file or read another
                                sheet</template
                            >
                            <template v-else>Replace this file</template>
                        </summary>
                        <div class="mt-3 space-y-3">
                            <label
                                v-if="
                                    (files[entry.side]!.sheets ?? []).length > 1
                                "
                                class="block text-sm"
                            >
                                <span class="mb-1 block font-medium"
                                    >Sheet to read</span
                                >
                                <select
                                    v-model="sheetChoice[entry.side]"
                                    :class="selectClass"
                                >
                                    <option value="">
                                        The sheet that looks like transactions
                                    </option>
                                    <option
                                        v-for="name in files[entry.side]!
                                            .sheets"
                                        :key="name"
                                        :value="name"
                                    >
                                        {{ name }}
                                    </option>
                                </select>
                                <span
                                    class="text-muted-foreground mt-1 block text-xs"
                                    >The file itself is never kept: choose it
                                    again below to read this sheet.</span
                                >
                            </label>
                            <FileDropzone
                                :run-id="run.id"
                                :side="entry.side"
                                :max-file-mb="limits.max_file_mb"
                                :sheet="sheetChoice[entry.side]"
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
            </div>
        </section>
    </div>

    <div
        class="mt-8 flex flex-wrap items-center justify-between gap-4 border-t pt-6"
    >
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
