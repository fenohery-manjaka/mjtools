<script setup lang="ts">
import { Head, useForm } from '@inertiajs/vue3';
import { ArrowRight, TriangleAlert } from '@lucide/vue';
import { Button } from '@/components/ui/button';
import { update } from '@/routes/supplier-reconciliation/mapping';
import MappingSideForm from '@/tools/supplier-reconciliation/components/MappingSideForm.vue';
import PageHeading from '@/tools/supplier-reconciliation/components/PageHeading.vue';
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
    <Head title="Columns — Supplier Statement Checker" />

    <StepNav :run="run" current="mapping" />

    <PageHeading title="Check the columns">
        We detected the columns below. Correct anything that is wrong: the
        original values are never modified, these settings only tell us how to
        read them.
    </PageHeading>
    <p
        v-if="run.reconciled"
        class="bg-warning-soft text-warning-strong border-warning/40 mt-5 flex items-start gap-2 rounded-lg border px-3.5 py-2.5 text-sm"
    >
        <TriangleAlert class="mt-0.5 size-4 shrink-0" />
        Changing the mapping will discard the current results and your decisions
        on them.
    </p>

    <form class="mt-8" @submit.prevent="submit">
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

        <div class="mt-8 flex justify-end border-t pt-6">
            <Button type="submit" size="lg" :disabled="form.processing">
                Save and check
                <ArrowRight />
            </Button>
        </div>
    </form>
</template>
