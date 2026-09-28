<script setup lang="ts">
// Colour coding only; the label always comes from the server.
defineProps<{ status: string; label: string }>();

const colours: Record<string, string> = {
    matched:
        'bg-emerald-100 text-emerald-800 dark:bg-emerald-950 dark:text-emerald-300',
    possible_match: 'bg-sky-100 text-sky-800 dark:bg-sky-950 dark:text-sky-300',
    ambiguous:
        'bg-amber-100 text-amber-900 dark:bg-amber-950 dark:text-amber-300',
    review_required:
        'bg-amber-100 text-amber-900 dark:bg-amber-950 dark:text-amber-300',
    missing_in_ledger:
        'bg-rose-100 text-rose-800 dark:bg-rose-950 dark:text-rose-300',
    ledger_only:
        'bg-orange-100 text-orange-800 dark:bg-orange-950 dark:text-orange-300',
    amount_mismatch:
        'bg-rose-100 text-rose-800 dark:bg-rose-950 dark:text-rose-300',
    duplicate_suspected:
        'bg-violet-100 text-violet-800 dark:bg-violet-950 dark:text-violet-300',
    excluded: 'bg-muted text-muted-foreground',
};
</script>

<template>
    <span
        class="inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-medium whitespace-nowrap"
        :class="colours[status] ?? 'bg-muted text-muted-foreground'"
    >
        {{ label }}
    </span>
</template>
