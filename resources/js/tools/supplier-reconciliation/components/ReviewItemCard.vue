<script setup lang="ts">
import { router } from '@inertiajs/vue3';
import { ArrowLeftRight } from '@lucide/vue';
import { computed } from 'vue';
import { Button } from '@/components/ui/button';
import { destroy, store } from '@/routes/supplier-reconciliation/decisions';
import { edgeClasses, toneOf } from '../tones';
import type { Candidate, ReviewItem, Transaction } from '../types';
import ComparisonTable from './ComparisonTable.vue';
import ReasonList from './ReasonList.vue';
import StatusBadge from './StatusBadge.vue';
import TransactionBlock from './TransactionBlock.vue';

const props = defineProps<{ runId: string; item: ReviewItem }>();
const emit = defineEmits<{ manualMatch: [item: ReviewItem] }>();

const choosing = computed(
    () =>
        props.item.actions.includes('choose') &&
        props.item.candidates.length > 0,
);

const linked = computed(
    () => props.item.statement.length > 0 && props.item.ledger.length > 0,
);

const single = computed<Candidate | null>(() =>
    !choosing.value && props.item.candidates.length === 1
        ? props.item.candidates[0]
        : null,
);

const transactions = computed(() => {
    const byId = new Map<string, Transaction>();

    for (const t of [...props.item.statement, ...props.item.ledger]) {
        byId.set(t.id, t);
    }

    return byId;
});

function decide(payload: Record<string, unknown>): void {
    router.post(
        store.url(props.runId),
        { item_id: props.item.id, ...payload },
        { preserveScroll: true },
    );
}

function reject(): void {
    if (
        props.item.resolution !== 'automatic' ||
        window.confirm(
            'Reject this automatic match? Both lines will need your review.',
        )
    ) {
        decide({ action: 'reject' });
    }
}

function choose(candidate: Candidate): void {
    decide({
        action: 'match',
        statement_ids: candidate.statement_ids,
        ledger_ids: candidate.ledger_ids,
    });
}

function undo(): void {
    if (props.item.decision) {
        router.delete(
            destroy.url({ run: props.runId, decision: props.item.decision.id }),
            { preserveScroll: true },
        );
    }
}

function label(id: string): string {
    const t = transactions.value.get(id);

    return t ? `${t.reference ?? 'no reference'} (row ${t.row})` : id;
}
</script>

<template>
    <article
        class="bg-card rounded-xl border border-l-4 p-5 shadow-xs sm:p-6"
        :class="edgeClasses[toneOf(item.status)]"
    >
        <header class="flex flex-wrap items-start justify-between gap-3">
            <div class="flex min-w-0 flex-col gap-2">
                <StatusBadge
                    class="self-start"
                    :status="item.status"
                    :label="item.status_label"
                />
                <h3 class="font-semibold">{{ item.headline }}</h3>
            </div>
            <div class="text-muted-foreground space-y-0.5 text-right text-xs">
                <p v-if="item.confidence && item.needs_attention">
                    Engine: {{ item.confidence }}
                </p>
                <p v-if="item.resolution !== 'open'">
                    {{ item.resolution_label }}
                </p>
                <p v-if="item.status !== item.engine_status">
                    Engine result: {{ item.engine_status_label }}
                </p>
            </div>
        </header>

        <div class="mt-5 grid gap-4 md:grid-cols-[1fr_auto_1fr] md:items-start">
            <div>
                <p
                    class="text-muted-foreground mb-2 text-xs font-medium tracking-[0.14em] uppercase"
                >
                    Supplier statement
                </p>
                <div class="space-y-2">
                    <TransactionBlock
                        v-for="t in item.statement"
                        :key="t.id"
                        :transaction="t"
                    />
                    <p
                        v-if="!item.statement.length"
                        class="text-muted-foreground border-foreground/15 rounded-md border border-dashed p-3 text-sm italic"
                    >
                        Nothing on the statement
                    </p>
                </div>
            </div>
            <ArrowLeftRight
                class="text-muted-foreground mt-9 hidden size-4 md:block"
                aria-hidden="true"
            />
            <div>
                <p
                    class="text-muted-foreground mb-2 text-xs font-medium tracking-[0.14em] uppercase"
                >
                    Ledger
                </p>
                <div class="space-y-2">
                    <TransactionBlock
                        v-for="t in item.ledger"
                        :key="t.id"
                        :transaction="t"
                    />
                    <p
                        v-if="!item.ledger.length"
                        class="text-muted-foreground border-foreground/15 rounded-md border border-dashed p-3 text-sm italic"
                    >
                        Nothing in the ledger
                    </p>
                </div>
            </div>
        </div>

        <p
            v-if="item.difference && item.status === 'amount_mismatch'"
            class="bg-danger-soft text-danger-strong mt-4 flex flex-wrap items-baseline justify-between gap-2 rounded-md px-3.5 py-2.5 text-sm"
        >
            Difference (statement − ledger)
            <strong class="figure text-base">{{ item.difference }}</strong>
        </p>

        <div class="bg-muted/45 mt-5 rounded-lg p-4">
            <p class="mb-2.5 text-sm font-semibold">
                {{
                    linked
                        ? 'Why we linked these'
                        : 'Why this needs your attention'
                }}
            </p>
            <ComparisonTable
                v-if="single && single.comparisons.length"
                :comparisons="single.comparisons"
                class="mb-3"
            />
            <ReasonList :reasons="item.reasons" />
        </div>

        <div v-if="choosing" class="mt-5 space-y-3">
            <p class="text-sm font-semibold">Candidates</p>
            <div
                v-for="(candidate, index) in item.candidates"
                :key="index"
                class="rounded-lg border p-4"
            >
                <div
                    class="mb-3 flex flex-wrap items-center justify-between gap-2 text-sm"
                >
                    <span class="figure">
                        {{ candidate.statement_ids.map(label).join(' + ') }}
                        ↔
                        {{ candidate.ledger_ids.map(label).join(' + ') }}
                    </span>
                    <Button
                        size="sm"
                        variant="outline"
                        @click="choose(candidate)"
                    >
                        Choose this match
                    </Button>
                </div>
                <ComparisonTable :comparisons="candidate.comparisons" />
            </div>
        </div>

        <footer
            v-if="item.actions.length"
            class="mt-5 flex flex-wrap items-center gap-2 border-t pt-4"
        >
            <Button
                v-if="item.actions.includes('confirm')"
                size="sm"
                @click="decide({ action: 'confirm' })"
            >
                Confirm match
            </Button>
            <Button
                v-if="item.actions.includes('reject')"
                size="sm"
                :variant="item.resolution === 'automatic' ? 'ghost' : 'outline'"
                @click="reject"
            >
                Reject match
            </Button>
            <Button
                v-if="item.actions.includes('manual_match')"
                size="sm"
                variant="outline"
                @click="emit('manualMatch', item)"
            >
                Match manually…
            </Button>
            <Button
                v-if="item.actions.includes('defer')"
                size="sm"
                variant="ghost"
                @click="decide({ action: 'defer' })"
            >
                Leave for review
            </Button>
            <Button
                v-if="item.actions.includes('undo')"
                size="sm"
                variant="ghost"
                @click="undo"
            >
                Undo: {{ item.decision?.label.toLowerCase() }}
            </Button>
        </footer>
    </article>
</template>
