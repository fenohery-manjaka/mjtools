<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { Check } from '@lucide/vue';
import { computed } from 'vue';
import { check, review, summary } from '@/routes/supplier-reconciliation';
import { edit as filesEdit } from '@/routes/supplier-reconciliation/files';
import { edit as mappingEdit } from '@/routes/supplier-reconciliation/mapping';
import type { Run } from '../types';

type StepKey = 'files' | 'mapping' | 'check' | 'summary' | 'review';

const props = defineProps<{ run: Run; current: StepKey }>();

const steps = computed(() => {
    const files = props.run.has_statement && props.run.has_ledger;

    return [
        {
            key: 'files',
            label: 'Files',
            href: filesEdit(props.run.id),
            enabled: true,
        },
        {
            key: 'mapping',
            label: 'Mapping',
            href: mappingEdit(props.run.id),
            enabled: files,
        },
        {
            key: 'check',
            label: 'Check',
            href: check(props.run.id),
            enabled: files,
        },
        {
            key: 'summary',
            label: 'Summary',
            href: summary(props.run.id),
            enabled: props.run.reconciled,
        },
        {
            key: 'review',
            label: 'Review',
            href: review(props.run.id),
            enabled: props.run.reconciled,
        },
    ] as const;
});

const currentIndex = computed(() =>
    steps.value.findIndex((step) => step.key === props.current),
);
</script>

<template>
    <nav aria-label="Reconciliation steps" class="mb-8">
        <ol class="flex flex-wrap items-center gap-2 text-sm">
            <li
                v-for="(step, index) in steps"
                :key="step.key"
                class="flex items-center gap-2"
            >
                <component
                    :is="step.enabled && step.key !== current ? Link : 'span'"
                    :href="step.enabled ? step.href : undefined"
                    :aria-current="step.key === current ? 'step' : undefined"
                    class="flex items-center gap-2 rounded-full px-3 py-1"
                    :class="[
                        step.key === current
                            ? 'bg-primary text-primary-foreground'
                            : step.enabled
                              ? 'bg-background hover:bg-accent border'
                              : 'text-muted-foreground border border-dashed',
                    ]"
                >
                    <Check v-if="index < currentIndex" class="size-3.5" />
                    <span v-else class="text-xs opacity-70">{{
                        index + 1
                    }}</span>
                    {{ step.label }}
                </component>
                <span
                    v-if="index < steps.length - 1"
                    class="text-muted-foreground"
                    aria-hidden="true"
                    >→</span
                >
            </li>
        </ol>
    </nav>
</template>
