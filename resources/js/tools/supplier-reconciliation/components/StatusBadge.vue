<script setup lang="ts">
import { computed } from 'vue';
import { dotClasses, softClasses, toneOf } from '../tones';

// Colour coding only; the label always comes from the server.
const props = defineProps<{ status: string; label: string }>();

const tone = computed(() => toneOf(props.status));
</script>

<template>
    <span
        class="inline-flex items-center gap-1.5 rounded-full px-2.5 py-0.5 text-xs font-medium whitespace-nowrap"
        :class="softClasses[tone]"
    >
        <span class="size-1.5 rounded-full" :class="dotClasses[tone]" />
        {{ label }}
    </span>
</template>
