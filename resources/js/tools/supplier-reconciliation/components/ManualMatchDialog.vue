<script setup lang="ts">
import { router } from '@inertiajs/vue3';
import { useDebounceFn } from '@vueuse/core';
import { computed, ref, watch } from 'vue';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { store } from '@/routes/supplier-reconciliation/decisions';
import type { MatchOptions, ReviewItem, Transaction } from '../types';

const props = defineProps<{
    runId: string;
    item: ReviewItem | null;
    options: MatchOptions;
}>();

const emit = defineEmits<{ close: [] }>();

const query = ref('');
const loading = ref(false);

const open = computed({
    get: () => props.item !== null,
    set: (value: boolean) => {
        if (!value) {
            emit('close');
        }
    },
});

const ownSide = computed(() =>
    props.item && props.item.statement.length ? 'statement' : 'ledger',
);

const choices = computed<Transaction[]>(() =>
    props.item && props.options?.item_id === props.item.id
        ? props.options.options
        : [],
);

function load(): void {
    if (!props.item) {
        return;
    }

    loading.value = true;
    router.reload({
        only: ['matchOptions'],
        data: { match_for: props.item.id, q: query.value || undefined },
        onFinish: () => (loading.value = false),
    });
}

const search = useDebounceFn(load, 300);

watch(
    () => props.item?.id,
    (id) => {
        query.value = '';

        if (id) {
            load();
        }
    },
);

function match(option: Transaction): void {
    if (!props.item) {
        return;
    }

    const own = [...props.item.statement, ...props.item.ledger].map(
        (t) => t.id,
    );

    router.post(
        store.url(props.runId),
        {
            action: 'match',
            item_id: props.item.id,
            statement_ids: ownSide.value === 'statement' ? own : [option.id],
            ledger_ids: ownSide.value === 'statement' ? [option.id] : own,
        },
        { preserveScroll: true, onSuccess: () => emit('close') },
    );
}
</script>

<template>
    <Dialog v-model:open="open">
        <DialogContent class="sm:max-w-2xl">
            <DialogHeader>
                <DialogTitle>Match manually</DialogTitle>
                <DialogDescription>
                    Choose the
                    {{ ownSide === 'statement' ? 'ledger' : 'statement' }}
                    line that corresponds. Lines with the same amount are listed
                    first. Only lines not matched yet are shown.
                </DialogDescription>
            </DialogHeader>

            <Input
                v-model="query"
                placeholder="Search a reference, amount or description"
                @update:model-value="search"
            />

            <div class="max-h-96 space-y-2 overflow-y-auto">
                <p v-if="loading" class="text-muted-foreground text-sm">
                    Loading…
                </p>
                <p
                    v-else-if="!choices.length"
                    class="text-muted-foreground text-sm"
                >
                    No available line.
                </p>
                <div
                    v-for="option in choices"
                    :key="option.id"
                    class="hover:bg-accent/50 flex items-center justify-between gap-3 rounded-md border p-2.5 text-sm"
                >
                    <div class="min-w-0">
                        <p class="figure truncate font-medium">
                            {{ option.reference ?? '(no reference)' }}
                        </p>
                        <p class="text-muted-foreground text-xs">
                            {{
                                option.date_normalized ??
                                option.date ??
                                'No date'
                            }}
                            · row {{ option.row }}
                            <template v-if="option.description">
                                · {{ option.description }}</template
                            >
                        </p>
                    </div>
                    <span class="figure font-medium">{{
                        option.amount_normalized ?? '—'
                    }}</span>
                    <Button size="sm" @click="match(option)">Match</Button>
                </div>
            </div>
        </DialogContent>
    </Dialog>
</template>
