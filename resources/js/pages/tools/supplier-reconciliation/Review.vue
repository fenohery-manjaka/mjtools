<script setup lang="ts">
import { Head, Link, usePage } from '@inertiajs/vue3';
import { CircleCheck, Download } from '@lucide/vue';
import { computed, ref } from 'vue';
import { Button } from '@/components/ui/button';
import { exportMethod, review } from '@/routes/supplier-reconciliation';
import ManualMatchDialog from '@/tools/supplier-reconciliation/components/ManualMatchDialog.vue';
import PageHeading from '@/tools/supplier-reconciliation/components/PageHeading.vue';
import ReviewItemCard from '@/tools/supplier-reconciliation/components/ReviewItemCard.vue';
import StepNav from '@/tools/supplier-reconciliation/components/StepNav.vue';
import { dotClasses, toneOf } from '@/tools/supplier-reconciliation/tones';
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
    <Head title="Review — Supplier Statement Checker" />

    <StepNav :run="run" current="review" />

    <PageHeading
        :title="
            attentionCount
                ? `Review ${attentionCount} ${attentionCount === 1 ? 'item' : 'items'}`
                : 'Everything has been reviewed'
        "
    >
        Nothing here changes your accounts. Your decisions are recorded
        separately and included in the export.
        <template #actions>
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
        </template>
    </PageHeading>

    <nav
        class="mt-8 flex flex-wrap items-center gap-2 border-b pb-5"
        aria-label="Filters"
    >
        <Link
            v-for="f in primaryFilters"
            :key="f.key"
            :href="link(f.key)"
            preserve-scroll
            :aria-current="f.key === filter ? 'page' : undefined"
            class="inline-flex items-center gap-2 rounded-full border px-3 py-1.5 text-sm transition-colors"
            :class="
                f.key === filter
                    ? 'bg-primary text-primary-foreground border-primary shadow-xs'
                    : 'bg-card hover:bg-accent'
            "
        >
            <span
                v-if="f.key !== 'all'"
                class="size-2 rounded-full"
                :class="dotClasses[toneOf(f.key)]"
            />
            {{ f.label }}
            <span class="figure text-xs opacity-75">{{ f.count }}</span>
        </Link>
        <span
            v-if="secondaryFilters.length"
            class="bg-border mx-1 h-5 w-px"
            aria-hidden="true"
        />
        <Link
            v-for="f in secondaryFilters"
            :key="f.key"
            :href="link(f.key)"
            preserve-scroll
            :aria-current="f.key === filter ? 'page' : undefined"
            class="inline-flex items-center gap-2 rounded-full border border-dashed px-3 py-1.5 text-sm transition-colors"
            :class="
                f.key === filter
                    ? 'bg-primary text-primary-foreground border-primary'
                    : 'text-muted-foreground border-foreground/20 hover:bg-accent'
            "
        >
            {{ f.label }}
            <span class="figure text-xs opacity-75">{{ f.count }}</span>
        </Link>
    </nav>

    <p
        v-if="decisionError"
        class="bg-danger-soft text-danger-strong border-danger/40 mt-4 rounded-lg border px-3.5 py-2.5 text-sm"
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
        <div
            v-if="!items.length"
            class="text-muted-foreground border-foreground/15 flex flex-col items-center gap-2 rounded-xl border border-dashed p-10 text-center"
        >
            <CircleCheck class="text-success size-6" />
            Nothing to show here.
        </div>
    </div>

    <nav
        v-if="pagination.last_page > 1"
        class="mt-8 flex items-center justify-center gap-4 text-sm"
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
            Page <span class="figure">{{ pagination.page }}</span> of
            <span class="figure">{{ pagination.last_page }}</span>
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
