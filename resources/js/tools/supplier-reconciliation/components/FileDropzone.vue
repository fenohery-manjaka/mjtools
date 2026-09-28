<script setup lang="ts">
import { useForm } from '@inertiajs/vue3';
import { Upload } from '@lucide/vue';
import { ref } from 'vue';
import InputError from '@/components/InputError.vue';
import { store } from '@/routes/supplier-reconciliation/files';
import type { Side } from '../types';

const props = defineProps<{
    runId: string;
    side: Side;
    maxFileMb: number;
}>();

const form = useForm<{ file: File | null }>({ file: null });
const dragging = ref(false);
const input = ref<HTMLInputElement | null>(null);

function upload(file: File | undefined): void {
    if (!file) {
        return;
    }

    form.file = file;
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
            class="flex cursor-pointer flex-col items-center justify-center gap-2 rounded-lg border-2 border-dashed px-4 py-8 text-center transition-colors"
            :class="
                dragging
                    ? 'border-primary bg-primary/5'
                    : 'hover:border-primary/60 border-muted-foreground/25'
            "
            @dragover.prevent="dragging = true"
            @dragleave.prevent="dragging = false"
            @drop.prevent="onDrop"
        >
            <Upload class="text-muted-foreground size-6" />
            <span class="text-sm font-medium">
                <template v-if="form.processing">Reading the file…</template>
                <template v-else>Drop a file here or click to choose</template>
            </span>
            <span class="text-muted-foreground text-xs">
                CSV or XLSX, up to {{ maxFileMb }} MB
            </span>
            <progress
                v-if="form.progress"
                :value="form.progress.percentage"
                max="100"
                class="w-40"
            />
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
