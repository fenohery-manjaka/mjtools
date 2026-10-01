<script setup lang="ts">
import type { HTMLAttributes } from 'vue';

defineOptions({
    inheritAttrs: false,
});

type Props = {
    className?: HTMLAttributes['class'];
};

defineProps<Props>();
</script>

<template>
    <!-- mjtools mark: ruled ledger lines closed by an auditor's tick. -->
    <svg
        xmlns="http://www.w3.org/2000/svg"
        viewBox="0 0 24 24"
        :class="className"
        v-bind="$attrs"
        aria-hidden="true"
    >
        <g
            fill="none"
            stroke="currentColor"
            stroke-width="2.25"
            stroke-linecap="round"
            stroke-linejoin="round"
        >
            <path d="M4 6.5h11" />
            <path d="M4 11.5h7" />
            <path d="M4 16.5h3.5" />
            <path d="m11 16 3 3 6.5-8" />
        </g>
    </svg>
</template>
