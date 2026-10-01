<script setup lang="ts">
import { Head, useForm } from '@inertiajs/vue3';
import { ArrowRight, Coins, TriangleAlert } from '@lucide/vue';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { update } from '@/routes/supplier-reconciliation/mapping';
import MappingSideForm from '@/tools/supplier-reconciliation/components/MappingSideForm.vue';
import PageHeading from '@/tools/supplier-reconciliation/components/PageHeading.vue';
import StepNav from '@/tools/supplier-reconciliation/components/StepNav.vue';
import type {
    CurrencyChoice,
    MappingSide,
    MappingValues,
    Run,
    Side,
} from '@/tools/supplier-reconciliation/types';

const props = defineProps<{
    run: Run;
    sides: Record<Side, MappingSide>;
    currency: CurrencyChoice;
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
    currency: props.currency.value,
});

const selectClass =
    'border-input bg-background dark:bg-input/30 h-10 w-full rounded-md border px-2.5 text-sm shadow-xs focus-visible:border-ring focus-visible:ring-ring/50 focus-visible:ring-[3px] outline-none';

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
        <section
            class="bg-card mb-6 grid gap-5 rounded-xl border p-6 shadow-xs md:grid-cols-[1fr_18rem] md:items-center"
        >
            <div class="flex gap-3">
                <span
                    class="bg-accent text-primary flex size-9 shrink-0 items-center justify-center rounded-lg"
                >
                    <Coins class="size-4.5" />
                </span>
                <div>
                    <h2 class="font-semibold">
                        Currency of this reconciliation
                    </h2>
                    <p class="text-muted-foreground text-sm">
                        {{ currency.message }} Both files must be in this
                        currency: amounts are never converted, and lines in
                        another currency block the check.
                    </p>
                </div>
            </div>
            <label class="block text-sm">
                <span class="sr-only">Currency</span>
                <select
                    v-model="form.currency"
                    :class="selectClass"
                    required
                    aria-label="Currency of this reconciliation"
                >
                    <option :value="null" disabled>Choose a currency…</option>
                    <option
                        v-for="option in currency.options"
                        :key="option.value"
                        :value="option.value"
                    >
                        {{ option.label }}
                    </option>
                </select>
                <span
                    v-if="
                        currency.proposed &&
                        form.currency === currency.proposed &&
                        !currency.confirmed
                    "
                    class="text-success-strong mt-1 block text-xs"
                    >Detected in your files — confirm by saving.</span
                >
                <InputError :message="form.errors.currency" />
            </label>
        </section>

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
