<script setup lang="ts">
import { CircleCheck, CircleHelp, TriangleAlert } from '@lucide/vue';
import { computed } from 'vue';
import type { BalanceResult } from '../types';

const props = defineProps<{
    balance: BalanceResult;
    currency?: string | null;
}>();

const look = computed(
    () =>
        ({
            verified: {
                icon: CircleCheck,
                title: 'Statement balance verified',
                class: 'text-success-strong',
            },
            inconsistent: {
                icon: TriangleAlert,
                title: 'Statement balance inconsistent',
                class: 'text-warning-strong',
            },
            unavailable: {
                icon: CircleHelp,
                title: 'Statement balance not checked',
                class: 'text-muted-foreground',
            },
        })[props.balance.status],
);
</script>

<template>
    <div class="bg-card rounded-xl border p-5 shadow-xs">
        <p class="flex items-center gap-2 font-semibold" :class="look.class">
            <component :is="look.icon" class="size-5 shrink-0" />
            {{ look.title }}
        </p>
        <p class="text-muted-foreground mt-1.5 text-sm">
            {{ balance.message }}
        </p>
        <dl
            v-if="balance.status !== 'unavailable'"
            class="figure mt-4 grid grid-cols-2 gap-x-6 gap-y-1.5 text-sm sm:grid-cols-4"
        >
            <div>
                <dt class="text-muted-foreground font-sans text-xs">Opening</dt>
                <dd>{{ balance.opening ?? '0.00' }}</dd>
            </div>
            <div>
                <dt class="text-muted-foreground font-sans text-xs">+ Lines</dt>
                <dd>{{ balance.movements }}</dd>
            </div>
            <div>
                <dt class="text-muted-foreground font-sans text-xs">
                    = Expected
                </dt>
                <dd>{{ balance.expected }}</dd>
            </div>
            <div>
                <dt class="text-muted-foreground font-sans text-xs">
                    Closing on statement
                </dt>
                <dd
                    :class="
                        balance.status === 'inconsistent'
                            ? 'text-warning-strong font-medium'
                            : ''
                    "
                >
                    {{ balance.closing }}
                    <span
                        v-if="currency"
                        class="text-muted-foreground text-xs"
                        >{{ currency }}</span
                    >
                </dd>
            </div>
        </dl>
    </div>
</template>
