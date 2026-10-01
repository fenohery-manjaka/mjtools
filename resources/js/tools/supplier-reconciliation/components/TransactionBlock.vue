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
    <div class="bg-background/60 rounded-md border p-3 text-sm">
        <div class="flex items-baseline justify-between gap-3">
            <span class="figure font-medium break-all">
                {{ transaction.reference ?? '(no reference)' }}
            </span>
            <span class="figure font-medium whitespace-nowrap">
                {{ transaction.amount_normalized ?? '—' }}
            </span>
        </div>
        <div
            class="text-muted-foreground mt-1 flex flex-wrap justify-between gap-x-3 text-xs"
        >
            <span class="figure">{{
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
            class="text-warning-strong mt-1 text-xs"
        >
            {{ issue }}
        </p>
    </div>
</template>
