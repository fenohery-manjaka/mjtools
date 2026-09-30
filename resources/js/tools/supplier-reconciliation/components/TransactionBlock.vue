<script setup lang="ts">
import type { Transaction } from '../types';

defineProps<{ transaction: Transaction }>();

function originalAmount(t: Transaction): string | null {
    if (t.amount !== null) {
        return t.amount;
    }

    const parts = [
        t.debit !== null ? `Debit ${t.debit}` : null,
        t.credit !== null ? `Credit ${t.credit}` : null,
    ].filter((part) => part !== null);

    return parts.length ? parts.join(' / ') : null;
}
</script>

<template>
    <div class="rounded-md border p-3 text-sm">
        <div class="flex items-baseline justify-between gap-2">
            <span class="font-medium break-all">
                {{ transaction.reference ?? '(no reference)' }}
            </span>
            <span class="font-semibold whitespace-nowrap tabular-nums">
                {{ transaction.amount_normalized ?? '—' }}
            </span>
        </div>
        <div
            class="text-muted-foreground mt-1 flex flex-wrap justify-between gap-x-3 text-xs"
        >
            <span>{{
                transaction.date_normalized ?? transaction.date ?? 'No date'
            }}</span>
            <span>{{ transaction.type }} · row {{ transaction.row }}</span>
        </div>
        <p
            v-if="transaction.description"
            class="text-muted-foreground mt-1 truncate text-xs"
            :title="transaction.description"
        >
            {{ transaction.description }}
        </p>
        <p
            v-if="
                originalAmount(transaction) &&
                originalAmount(transaction) !== transaction.amount_normalized
            "
            class="text-muted-foreground mt-1 text-xs"
        >
            Original amount: <code>{{ originalAmount(transaction) }}</code>
        </p>
        <p
            v-for="issue in transaction.issues"
            :key="issue"
            class="mt-1 text-xs text-amber-700 dark:text-amber-400"
        >
            {{ issue }}
        </p>
    </div>
</template>
