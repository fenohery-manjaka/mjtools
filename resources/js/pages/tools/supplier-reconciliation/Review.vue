<script setup lang="ts">
import { Head, Link, usePage } from '@inertiajs/vue3';
import { Download } from '@lucide/vue';
import { computed, ref } from 'vue';
import { Button } from '@/components/ui/button';
import { exportMethod, review } from '@/routes/supplier-reconciliation';
import ManualMatchDialog from '@/tools/supplier-reconciliation/components/ManualMatchDialog.vue';
import ReviewItemCard from '@/tools/supplier-reconciliation/components/ReviewItemCard.vue';
import StepNav from '@/tools/supplier-reconciliation/components/StepNav.vue';
import type {
    Filter,
    MatchOptions,
    ReviewItem,
    Run,
} from '@/tools/supplier-reconciliation/types';

const props = defineProps<{
    run: Run;
    filter: string;
    filters: Filter[];
    items: ReviewItem[];
    pagination: { page: number; last_page: number; total: number };
    attentionCount: number;
    matchOptions?: MatchOptions;
}>();

const page = usePage();
const decisionError = computed(
    () => page.props.errors?.decision as string | undefined,
);

// "Matched" and "Not transactions" are secondary views, shown apart from the exceptions.
const secondary = ['matched', 'excluded'];
const primaryFilters = computed(() =>
    props.filters.filter(
        (f) => !secondary.includes(f.key) && (f.count > 0 || f.key === 'all'),
    ),
);
const secondaryFilters = computed(() =>
    props.filters.filter((f) => secondary.includes(f.key) && f.count > 0),
);

const matching = ref<ReviewItem | null>(null);

function link(filter: string, pageNumber = 1) {
    return review(props.run.id, {
        query: { filter, ...(pageNumber > 1 ? { page: pageNumber } : {}) },
    });
}
</script>

<template>
    <Head title="Review — Supplier reconciliation" />

    <StepNav :run="run" current="review" />

    <div class="flex flex-wrap items-end justify-between gap-4">
        <div>
            <h1 class="text-2xl font-semibold tracking-tight">
                <template v-if="attentionCount"
                    >Review {{ attentionCount }}
                    {{ attentionCount === 1 ? 'item' : 'items' }}</template
                >
                <template v-else>Everything has been reviewed</template>
            </h1>
            <p class="text-muted-foreground mt-1">
                Nothing here changes your accounts. Your decisions are recorded
                separately and included in the export.
            </p>
        </div>
        <div class="flex gap-2">
            <Button as-child variant="outline" size="sm">
                <a :href="exportMethod.url({ run: run.id, format: 'xlsx' })"
                    ><Download /> Excel</a
                >
            </Button>
            <Button as-child variant="outline" size="sm">
                <a :href="exportMethod.url({ run: run.id, format: 'csv' })"
                    ><Download /> CSV</a
                >
            </Button>
        </div>
    </div>

    <nav class="mt-6 flex flex-wrap items-center gap-2" aria-label="Filters">
        <Link
            v-for="f in primaryFilters"
            :key="f.key"
            :href="link(f.key)"
            preserve-scroll
            class="rounded-full border px-3 py-1 text-sm"
            :class="
                f.key === filter
                    ? 'bg-primary text-primary-foreground border-primary'
                    : 'bg-background hover:bg-accent'
            "
        >
            {{ f.label }} <span class="opacity-70">{{ f.count }}</span>
        </Link>
        <span v-if="secondaryFilters.length" class="text-muted-foreground mx-1"
            >|</span
        >
        <Link
            v-for="f in secondaryFilters"
            :key="f.key"
            :href="link(f.key)"
            preserve-scroll
            class="rounded-full border border-dashed px-3 py-1 text-sm"
            :class="
                f.key === filter
                    ? 'bg-primary text-primary-foreground border-primary'
                    : 'text-muted-foreground hover:bg-accent'
            "
        >
            {{ f.label }} <span class="opacity-70">{{ f.count }}</span>
        </Link>
    </nav>

    <p
        v-if="decisionError"
        class="mt-4 rounded-md border border-rose-300 bg-rose-50 px-3 py-2 text-sm text-rose-900 dark:border-rose-900 dark:bg-rose-950 dark:text-rose-200"
        role="alert"
    >
        {{ decisionError }}
    </p>

    <div class="mt-6 space-y-4">
        <ReviewItemCard
            v-for="item in items"
            :key="item.id"
            :run-id="run.id"
            :item="item"
            @manual-match="matching = $event"
        />
        <p
            v-if="!items.length"
            class="text-muted-foreground bg-background rounded-xl border p-8 text-center"
        >
            Nothing to show here.
        </p>
    </div>

    <nav
        v-if="pagination.last_page > 1"
        class="mt-6 flex items-center justify-center gap-4 text-sm"
        aria-label="Pagination"
    >
        <Link
            v-if="pagination.page > 1"
            :href="link(filter, pagination.page - 1)"
            class="hover:underline"
        >
            ← Previous
        </Link>
        <span class="text-muted-foreground">
            Page {{ pagination.page }} of {{ pagination.last_page }}
        </span>
        <Link
            v-if="pagination.page < pagination.last_page"
            :href="link(filter, pagination.page + 1)"
            class="hover:underline"
        >
            Next →
        </Link>
    </nav>

    <ManualMatchDialog
        :run-id="run.id"
        :item="matching"
        :options="matchOptions ?? null"
        @close="matching = null"
    />
</template>
