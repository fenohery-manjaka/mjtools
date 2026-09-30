<script setup lang="ts">
import { Head, useForm } from '@inertiajs/vue3';
import { ArrowRight } from '@lucide/vue';
import { Button } from '@/components/ui/button';
import { update } from '@/routes/supplier-reconciliation/mapping';
import MappingSideForm from '@/tools/supplier-reconciliation/components/MappingSideForm.vue';
import StepNav from '@/tools/supplier-reconciliation/components/StepNav.vue';
import type {
    MappingSide,
    MappingValues,
    Run,
    Side,
} from '@/tools/supplier-reconciliation/types';

const props = defineProps<{
    run: Run;
    sides: Record<Side, MappingSide>;
}>();

function initial(side: MappingSide): MappingValues {
    const columns: Record<string, number | null> = {};

    for (const field of side.fields) {
        columns[field.key] = side.mapping.columns[field.key] ?? null;
    }

    return { ...side.mapping, columns };
}

const form = useForm({
    statement: initial(props.sides.statement),
    ledger: initial(props.sides.ledger),
});

function submit(): void {
    form.put(update.url(props.run.id), { preserveScroll: true });
}
</script>

<template>
    <Head title="Mapping — Supplier reconciliation" />

    <StepNav :run="run" current="mapping" />

    <h1 class="text-2xl font-semibold tracking-tight">Check the columns</h1>
    <p class="text-muted-foreground mt-1">
        We detected the columns below. Correct anything that is wrong: the
        original values are never modified, these settings only tell us how to
        read them.
    </p>
    <p
        v-if="run.reconciled"
        class="mt-3 rounded-md border border-amber-300 bg-amber-50 px-3 py-2 text-sm text-amber-900 dark:border-amber-800 dark:bg-amber-950 dark:text-amber-200"
    >
        Changing the mapping will discard the current results and your decisions
        on them.
    </p>

    <form class="mt-6" @submit.prevent="submit">
        <div class="grid gap-6 lg:grid-cols-2">
            <MappingSideForm
                v-model="form.statement"
                :run-id="run.id"
                :data="sides.statement"
                :errors="form.errors"
            />
            <MappingSideForm
                v-model="form.ledger"
                :run-id="run.id"
                :data="sides.ledger"
                :errors="form.errors"
            />
        </div>

        <div class="mt-8 flex justify-end">
            <Button type="submit" size="lg" :disabled="form.processing">
                Save and check
                <ArrowRight />
            </Button>
        </div>
    </form>
</template>
