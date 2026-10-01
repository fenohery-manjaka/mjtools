<script setup lang="ts">
import { usePage } from '@inertiajs/vue3';
import AppLogoIcon from '@/components/AppLogoIcon.vue';

withDefaults(defineProps<{ size?: 'sm' | 'md' }>(), { size: 'md' });

const name = usePage().props.name;
</script>

<template>
    <span class="inline-flex items-center gap-2.5">
        <span
            class="bg-primary text-primary-foreground flex items-center justify-center rounded-md shadow-xs"
            :class="size === 'sm' ? 'size-6' : 'size-8'"
        >
            <AppLogoIcon :class="size === 'sm' ? 'size-4' : 'size-5'" />
        </span>
        <span
            class="font-display font-semibold tracking-tight"
            :class="size === 'sm' ? 'text-base' : 'text-xl'"
            >{{ name }}</span
        >
    </span>
</template>
