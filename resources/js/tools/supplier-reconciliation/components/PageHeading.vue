<script setup lang="ts">
defineProps<{ title: string }>();
</script>

<template>
    <div class="flex flex-wrap items-end justify-between gap-4">
        <div class="max-w-3xl">
            <h1
                class="font-display text-3xl font-semibold tracking-tight sm:text-[2.1rem]"
            >
                {{ title }}
            </h1>
            <p v-if="$slots.default" class="text-muted-foreground mt-2">
                <slot />
            </p>
        </div>
        <div v-if="$slots.actions" class="flex flex-wrap gap-2">
            <slot name="actions" />
        </div>
    </div>
</template>
