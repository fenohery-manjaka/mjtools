<script setup lang="ts">
import { dotClasses, toneOf } from '../tones';

// Decorative example of a result, clearly labelled as such.
const rows = [
    { status: 'possible_match', label: 'Possible matches', count: 12 },
    { status: 'missing_in_ledger', label: 'Missing in ledger', count: 9 },
    { status: 'amount_mismatch', label: 'Amount mismatches', count: 3 },
    { status: 'review_required', label: 'Need review', count: 5 },
];
</script>

<template>
    <figure
        class="bg-card relative rounded-xl border p-6 shadow-[0_1px_0_0_var(--border),0_24px_48px_-24px_color-mix(in_oklch,var(--foreground)_25%,transparent)]"
        aria-label="Example of a result"
    >
        <figcaption
            class="text-muted-foreground flex items-center justify-between text-xs"
        >
            <span class="font-medium tracking-[0.16em] uppercase"
                >Example result</span
            >
            <span class="figure">347 lines analysed</span>
        </figcaption>
        <p
            class="font-display mt-4 text-2xl leading-snug font-semibold tracking-tight"
        >
            29 items need your attention instead of 347.
        </p>
        <div class="mt-5">
            <div class="bg-muted flex h-2 overflow-hidden rounded-full">
                <span class="bg-success h-full" style="width: 91.6%" />
                <span class="bg-warning h-full flex-1" />
            </div>
            <p class="text-muted-foreground mt-2 text-xs">
                <span class="figure text-success-strong font-medium"
                    >91.6%</span
                >
                cleared automatically
            </p>
        </div>
        <ul class="mt-5 divide-y border-t text-sm">
            <li class="flex items-center gap-2 py-2">
                <span class="bg-success size-2 rounded-full" />
                <span class="flex-1">Matched automatically</span>
                <span class="figure">318</span>
            </li>
            <li
                v-for="row in rows"
                :key="row.status"
                class="flex items-center gap-2 py-2"
            >
                <span
                    class="size-2 rounded-full"
                    :class="dotClasses[toneOf(row.status)]"
                />
                <span class="flex-1">{{ row.label }}</span>
                <span class="figure">{{ row.count }}</span>
            </li>
        </ul>
    </figure>
</template>
