<script setup lang="ts">
import { Monitor, Moon, Sun } from '@lucide/vue';
import { computed } from 'vue';
import { useAppearance } from '@/composables/useAppearance';

// Compact light / dark / system switch for public pages.
const { appearance, updateAppearance } = useAppearance();

const order = ['light', 'dark', 'system'] as const;
const labels = { light: 'Light', dark: 'Dark', system: 'System' } as const;
const icons = { light: Sun, dark: Moon, system: Monitor } as const;

const next = computed(
    () => order[(order.indexOf(appearance.value) + 1) % order.length],
);
</script>

<template>
    <button
        type="button"
        class="text-muted-foreground hover:text-foreground hover:bg-accent focus-visible:ring-ring/50 inline-flex size-8 items-center justify-center rounded-md transition-colors outline-none focus-visible:ring-[3px]"
        :title="`Theme: ${labels[appearance]} (switch to ${labels[next].toLowerCase()})`"
        :aria-label="`Theme: ${labels[appearance]}. Switch to ${labels[next].toLowerCase()}`"
        @click="updateAppearance(next)"
    >
        <component :is="icons[appearance]" class="size-4" />
    </button>
</template>
