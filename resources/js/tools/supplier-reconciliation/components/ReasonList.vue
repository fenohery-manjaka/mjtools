<script setup lang="ts">
import type { Polarity, Reason } from '../types';

defineProps<{ reasons: Reason[] }>();

const symbols: Record<
    Polarity,
    { mark: string; class: string; label: string }
> = {
    agrees: {
        mark: '✓',
        class: 'text-emerald-600 dark:text-emerald-400',
        label: 'agrees',
    },
    partial: {
        mark: '~',
        class: 'text-amber-600 dark:text-amber-400',
        label: 'partly agrees',
    },
    differs: {
        mark: '✗',
        class: 'text-rose-600 dark:text-rose-400',
        label: 'differs',
    },
    info: { mark: 'i', class: 'text-muted-foreground', label: 'information' },
};
</script>

<template>
    <ul class="space-y-1 text-sm">
        <li
            v-for="(reason, index) in reasons"
            :key="`${reason.code}-${index}`"
            class="flex gap-2"
        >
            <span
                class="w-4 shrink-0 text-center font-semibold"
                :class="symbols[reason.polarity].class"
                :title="symbols[reason.polarity].label"
                aria-hidden="true"
                >{{ symbols[reason.polarity].mark }}</span
            >
            <span class="sr-only">{{ symbols[reason.polarity].label }}:</span>
            <span>{{ reason.message }}</span>
        </li>
    </ul>
</template>
