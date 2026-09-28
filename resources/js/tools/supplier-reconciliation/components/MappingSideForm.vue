<script setup lang="ts">
import { router } from '@inertiajs/vue3';
import { computed } from 'vue';
import InputError from '@/components/InputError.vue';
import { header } from '@/routes/supplier-reconciliation/mapping';
import type { MappingSide, MappingValues } from '../types';

const props = defineProps<{
    runId: string;
    data: MappingSide;
    errors: Record<string, string>;
}>();

const mapping = defineModel<MappingValues>({ required: true });

const selectClass =
    'border-input bg-background dark:bg-input/30 h-9 w-full rounded-md border px-2 text-sm shadow-xs focus-visible:ring-ring/50 focus-visible:ring-[3px] outline-none';

const visibleFields = computed(() =>
    props.data.fields.filter((field) => {
        if (field.key === 'supplier' && props.data.side !== 'ledger') {
            return false;
        }

        if (mapping.value.amount_mode === 'signed') {
            return field.key !== 'debit' && field.key !== 'credit';
        }

        return field.key !== 'amount';
    }),
);

const required = ['reference', 'amount', 'debit', 'credit'];

function samples(column: number | null | undefined): string[] {
    return column === null || column === undefined
        ? []
        : (props.data.columns[column]?.samples ?? []);
}

function error(key: string): string | undefined {
    return props.errors[`${props.data.side}.${key}`];
}

function changeHeader(event: Event): void {
    const index = Number((event.target as HTMLSelectElement).value);

    if (
        window.confirm(
            'Use this row as the header row? The columns of this file will be detected again.',
        )
    ) {
        router.put(
            header.url({ run: props.runId, side: props.data.side }),
            { header_index: index },
            { preserveScroll: true },
        );
    } else {
        (event.target as HTMLSelectElement).value = String(
            props.data.header_index,
        );
    }
}
</script>

<template>
    <section class="bg-background rounded-xl border p-5">
        <div class="mb-4">
            <h2 class="font-medium">{{ data.label }}</h2>
            <p class="text-muted-foreground truncate text-sm">
                {{ data.file_name }}
            </p>
        </div>

        <label class="mb-5 block text-sm">
            <span class="mb-1 block font-medium">Header row</span>
            <select
                :class="selectClass"
                :value="data.header_index"
                @change="changeHeader"
            >
                <option
                    v-for="choice in data.header_choices"
                    :key="choice.index"
                    :value="choice.index"
                >
                    Row {{ choice.row_number }}: {{ choice.text || '(empty)' }}
                </option>
            </select>
        </label>

        <fieldset class="mb-5">
            <legend class="mb-2 text-sm font-medium">Amounts are in</legend>
            <div class="flex flex-wrap gap-4 text-sm">
                <label
                    v-for="mode in data.options.amount_modes"
                    :key="mode.value"
                    class="flex items-center gap-2"
                >
                    <input
                        v-model="mapping.amount_mode"
                        type="radio"
                        :value="mode.value"
                    />
                    {{ mode.label }}
                </label>
            </div>
        </fieldset>

        <div class="grid gap-4 sm:grid-cols-2">
            <label
                v-for="field in visibleFields"
                :key="field.key"
                class="block text-sm"
            >
                <span class="mb-1 block font-medium">
                    {{ field.label }}
                    <span
                        v-if="required.includes(field.key)"
                        class="text-rose-600"
                        >*</span
                    >
                </span>
                <select
                    v-model="mapping.columns[field.key]"
                    :class="selectClass"
                >
                    <option :value="null">— Not used —</option>
                    <option
                        v-for="column in data.columns"
                        :key="column.index"
                        :value="column.index"
                    >
                        {{ column.letter }} · {{ column.name }}
                    </option>
                </select>
                <span class="text-muted-foreground mt-1 block text-xs">
                    {{ field.help }}
                </span>
                <span
                    v-if="samples(mapping.columns[field.key]).length"
                    class="mt-1 flex flex-wrap gap-1"
                >
                    <code
                        v-for="(sample, index) in samples(
                            mapping.columns[field.key],
                        )"
                        :key="index"
                        class="bg-muted rounded px-1.5 py-0.5 text-xs"
                        >{{ sample }}</code
                    >
                </span>
                <InputError :message="error(`columns.${field.key}`)" />
            </label>
        </div>

        <div class="mt-6 grid gap-4 border-t pt-5 sm:grid-cols-2">
            <label
                v-if="mapping.amount_mode === 'signed'"
                class="block text-sm"
            >
                <span class="mb-1 block font-medium">Invoices appear as</span>
                <select v-model="mapping.invoice_sign" :class="selectClass">
                    <option
                        v-for="option in data.options.invoice_signs"
                        :key="option.value"
                        :value="option.value"
                    >
                        {{ option.label }}
                    </option>
                </select>
            </label>
            <label v-else class="block text-sm">
                <span class="mb-1 block font-medium">Invoices are in the</span>
                <select v-model="mapping.invoice_column" :class="selectClass">
                    <option
                        v-for="option in data.options.invoice_columns"
                        :key="option.value"
                        :value="option.value"
                    >
                        {{ option.label }}
                    </option>
                </select>
            </label>

            <label class="block text-sm">
                <span class="mb-1 block font-medium">Decimal separator</span>
                <select
                    v-model="mapping.decimal_separator"
                    :class="selectClass"
                >
                    <option
                        v-for="option in data.options.decimal_separators"
                        :key="option.value"
                        :value="option.value"
                    >
                        {{ option.label }}
                    </option>
                </select>
            </label>

            <label v-if="mapping.columns.date !== null" class="block text-sm">
                <span class="mb-1 block font-medium">Date format</span>
                <select v-model="mapping.date_order" :class="selectClass">
                    <option
                        v-for="option in data.options.date_orders"
                        :key="option.value"
                        :value="option.value"
                    >
                        {{ option.label }}
                    </option>
                </select>
            </label>

            <label
                v-if="
                    data.side === 'ledger' && mapping.columns.supplier !== null
                "
                class="block text-sm"
            >
                <span class="mb-1 block font-medium">Only this supplier</span>
                <select v-model="mapping.supplier_filter" :class="selectClass">
                    <option :value="null">All suppliers</option>
                    <option
                        v-for="value in data.supplier_values"
                        :key="value"
                        :value="value"
                    >
                        {{ value }}
                    </option>
                </select>
            </label>

            <label
                v-if="mapping.columns.type !== null"
                class="flex items-start gap-2 text-sm sm:col-span-2"
            >
                <input
                    v-model="mapping.sign_from_type"
                    type="checkbox"
                    class="mt-1"
                />
                <span>
                    Credit notes and payments are listed as positive amounts:
                    take the sign from the Type column
                </span>
            </label>
        </div>
    </section>
</template>
