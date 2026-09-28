<script setup lang="ts">
import type { Comparison, Polarity } from '../types';

defineProps<{ comparisons: Comparison[] }>();

const fieldLabels: Record<string, string> = {
    reference: 'Reference',
    amount: 'Amount',
    date: 'Date',
};

const marks: Record<Polarity, { mark: string; class: string }> = {
    agrees: { mark: '✓', class: 'text-emerald-600 dark:text-emerald-400' },
    partial: { mark: '~', class: 'text-amber-600 dark:text-amber-400' },
    differs: { mark: '✗', class: 'text-rose-600 dark:text-rose-400' },
    info: { mark: '–', class: 'text-muted-foreground' },
};

function showNormalized(
    original: string | null,
    normalized: string | null,
): boolean {
    return normalized !== null && normalized !== original;
}
</script>

<template>
    <div class="overflow-x-auto">
        <table class="w-full text-left text-sm">
            <thead class="text-muted-foreground text-xs">
                <tr>
                    <th class="py-1 pr-3 font-medium">Field</th>
                    <th class="py-1 pr-3 font-medium">Supplier statement</th>
                    <th class="py-1 pr-3 font-medium">Ledger</th>
                    <th class="py-1 font-medium">Comparison</th>
                </tr>
            </thead>
            <tbody>
                <tr
                    v-for="row in comparisons"
                    :key="row.field"
                    class="border-t align-top"
                >
                    <td class="py-1.5 pr-3 font-medium">
                        {{ fieldLabels[row.field] }}
                    </td>
                    <td class="py-1.5 pr-3">
                        <code>{{ row.statement_original ?? '—' }}</code>
                        <span
                            v-if="
                                showNormalized(
                                    row.statement_original,
                                    row.statement_normalized,
                                )
                            "
                            class="text-muted-foreground block text-xs"
                        >
                            compared as
                            <code>{{ row.statement_normalized }}</code>
                        </span>
                    </td>
                    <td class="py-1.5 pr-3">
                        <code>{{ row.ledger_original ?? '—' }}</code>
                        <span
                            v-if="
                                showNormalized(
                                    row.ledger_original,
                                    row.ledger_normalized,
                                )
                            "
                            class="text-muted-foreground block text-xs"
                        >
                            compared as <code>{{ row.ledger_normalized }}</code>
                        </span>
                    </td>
                    <td class="py-1.5">
                        <span
                            :class="marks[row.polarity].class"
                            aria-hidden="true"
                            >{{ marks[row.polarity].mark }}</span
                        >
                        {{ row.relation }}
                    </td>
                </tr>
            </tbody>
        </table>
    </div>
</template>
