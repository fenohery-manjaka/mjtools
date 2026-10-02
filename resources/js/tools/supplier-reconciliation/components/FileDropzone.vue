<script setup lang="ts">
import { useForm } from '@inertiajs/vue3';
import { LoaderCircle, Upload } from '@lucide/vue';
import { ref } from 'vue';
import InputError from '@/components/InputError.vue';
import { store } from '@/routes/supplier-reconciliation/files';
import type { Side } from '../types';

const props = defineProps<{
    runId: string;
    side: Side;
    maxFileMb: number;
    /** Worksheet to read in a workbook; the most relevant one when empty. */
    sheet?: string;
}>();

const form = useForm<{ file: File | null; sheet: string }>({
    file: null,
    sheet: '',
});
const dragging = ref(false);
const input = ref<HTMLInputElement | null>(null);

function upload(file: File | undefined): void {
    if (!file) {
        return;
    }

    form.file = file;
    form.sheet = props.sheet ?? '';
    form.post(store.url({ run: props.runId, side: props.side }), {
        preserveScroll: true,
        onFinish: () => {
            form.reset();

            if (input.value) {
                input.value.value = '';
            }
        },
    });
}

function onDrop(event: DragEvent): void {
    dragging.value = false;
    upload(event.dataTransfer?.files[0]);
}

function onChange(event: Event): void {
    upload((event.target as HTMLInputElement).files?.[0]);
}
</script>

<template>
    <div>
        <label
            class="focus-within:ring-ring/50 flex cursor-pointer flex-col items-center justify-center gap-2 rounded-lg border-2 border-dashed px-4 py-10 text-center transition-colors focus-within:ring-[3px]"
            :class="
                dragging
                    ? 'border-primary bg-accent'
                    : 'border-foreground/15 hover:border-primary/50 hover:bg-accent/50'
            "
            @dragover.prevent="dragging = true"
            @dragleave.prevent="dragging = false"
            @drop.prevent="onDrop"
        >
            <span
                class="bg-accent text-primary flex size-11 items-center justify-center rounded-full"
            >
                <LoaderCircle
                    v-if="form.processing"
                    class="size-5 animate-spin"
                />
                <Upload v-else class="size-5" />
            </span>
            <span class="mt-1 text-sm font-medium">
                <template v-if="form.processing">Reading the file…</template>
                <template v-else
                    >Drop a file here or
                    <span class="text-primary underline underline-offset-4"
                        >choose one</span
                    ></template
                >
            </span>
            <span class="text-muted-foreground text-xs">
                CSV or XLSX, up to {{ maxFileMb }} MB
            </span>
            <span
                v-if="form.progress"
                class="bg-muted mt-1 h-1.5 w-40 overflow-hidden rounded-full"
            >
                <span
                    class="bg-primary block h-full transition-[width]"
                    :style="{ width: `${form.progress.percentage ?? 0}%` }"
                />
            </span>
            <input
                ref="input"
                type="file"
                class="sr-only"
                accept=".csv,.txt,.tsv,.xlsx,text/csv,application/vnd.openxmlformats-officedocument.spreadsheetml.sheet"
                :disabled="form.processing"
                @change="onChange"
            />
        </label>
        <InputError class="mt-2" :message="form.errors.file" />
    </div>
</template>
