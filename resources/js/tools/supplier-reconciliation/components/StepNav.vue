<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { Check } from '@lucide/vue';
import { computed } from 'vue';
import {
    check,
    index,
    review,
    summary,
} from '@/routes/supplier-reconciliation';
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
            label: 'Columns',
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
    <div class="mb-8 sm:mb-10">
        <Link
            :href="index()"
            class="text-muted-foreground hover:text-foreground text-xs font-medium tracking-[0.16em] uppercase"
        >
            Supplier Statement Checker
        </Link>
        <nav aria-label="Reconciliation steps" class="mt-3 overflow-x-auto">
            <ol
                class="flex min-w-max items-center gap-1 text-sm sm:min-w-0 sm:gap-2"
            >
                <li
                    v-for="(step, stepIndex) in steps"
                    :key="step.key"
                    class="flex items-center gap-1 sm:flex-1 sm:gap-2 sm:last:flex-none"
                >
                    <component
                        :is="
                            step.enabled && step.key !== current ? Link : 'span'
                        "
                        :href="step.enabled ? step.href : undefined"
                        :aria-current="
                            step.key === current ? 'step' : undefined
                        "
                        class="group flex items-center gap-2 rounded-md py-1 pr-1"
                        :class="
                            step.key === current
                                ? 'text-foreground font-semibold'
                                : step.enabled
                                  ? 'text-foreground/80 hover:text-foreground'
                                  : 'text-muted-foreground/70'
                        "
                    >
                        <span
                            class="figure flex size-7 shrink-0 items-center justify-center rounded-full text-xs transition-colors"
                            :class="
                                step.key === current
                                    ? 'bg-primary text-primary-foreground shadow-xs'
                                    : stepIndex < currentIndex && step.enabled
                                      ? 'bg-success-soft text-success-strong'
                                      : step.enabled
                                        ? 'bg-card group-hover:border-foreground/40 border'
                                        : 'border border-dashed'
                            "
                        >
                            <Check
                                v-if="
                                    stepIndex < currentIndex &&
                                    step.enabled &&
                                    step.key !== current
                                "
                                class="size-3.5"
                            />
                            <template v-else>{{ stepIndex + 1 }}</template>
                        </span>
                        <span
                            :class="
                                step.key === current
                                    ? ''
                                    : 'sr-only sm:not-sr-only'
                            "
                            >{{ step.label }}</span
                        >
                    </component>
                    <span
                        v-if="stepIndex < steps.length - 1"
                        class="h-px w-3 sm:w-auto sm:flex-1"
                        :class="
                            stepIndex < currentIndex
                                ? 'bg-success/60'
                                : 'bg-border'
                        "
                        aria-hidden="true"
                    />
                </li>
            </ol>
        </nav>
    </div>
</template>
