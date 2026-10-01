<script setup lang="ts">
import { router, useForm } from '@inertiajs/vue3';
import { ArrowRight, BookmarkPlus, CircleCheck } from '@lucide/vue';
import { ref } from 'vue';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { click, store } from '@/routes/supplier-reconciliation/interest';
import type { Intent } from '../types';

// "Do this every month?" (spec §43): measures interest in the paid product
// without pretending it exists.
const props = defineProps<{
    runId: string;
    intent: Intent;
    analyzedLines: number;
    reviewedItems: number;
}>();

const open = ref(false);

const form = useForm({
    suppliers_per_month: '',
    accounting_software: '',
    accounting_software_other: '',
    price_answer: '',
    email: '',
});

const selectClass =
    'border-input bg-background dark:bg-input/30 h-10 w-full rounded-md border px-2.5 text-sm shadow-xs focus-visible:border-ring focus-visible:ring-ring/50 focus-visible:ring-[3px] outline-none';

function start(): void {
    open.value = true;
    router.post(
        click.url(props.runId),
        {},
        { preserveScroll: true, preserveState: true },
    );
}

function submit(): void {
    form.post(store.url(props.runId), { preserveScroll: true });
}
</script>

<template>
    <section
        class="border-primary/20 bg-accent/50 rounded-xl border p-6 sm:p-8"
    >
        <div
            v-if="intent.sent"
            class="text-success-strong flex items-start gap-3"
        >
            <CircleCheck class="mt-0.5 size-5 shrink-0" />
            <div>
                <p class="font-semibold">Thank you for your answers.</p>
                <p class="text-muted-foreground mt-1 text-sm">
                    They help us decide whether to build saved suppliers and
                    mappings. If you left an email, we will only use it to tell
                    you when it is available.
                </p>
            </div>
        </div>

        <template v-else>
            <div
                class="flex flex-col gap-5 md:flex-row md:items-center md:justify-between"
            >
                <div class="max-w-2xl">
                    <p
                        class="font-display text-xl font-semibold tracking-tight"
                    >
                        You reconciled
                        {{ analyzedLines.toLocaleString() }} lines and only
                        needed to review {{ reviewedItems }}. Do this every
                        month?
                    </p>
                    <p class="text-muted-foreground mt-2">
                        Save this supplier and its mapping so you don't have to
                        configure it again next period.
                    </p>
                </div>
                <Button v-if="!open" size="lg" class="shrink-0" @click="start">
                    <BookmarkPlus />
                    Save this supplier
                </Button>
            </div>

            <form
                v-if="open"
                class="bg-card mt-6 rounded-lg border p-5 sm:p-6"
                @submit.prevent="submit"
            >
                <p class="font-semibold">This is not available yet.</p>
                <p class="text-muted-foreground mt-1 text-sm">
                    We are deciding whether to build it. Three quick questions
                    tell us if it would really help you — nothing is sold or
                    charged here.
                </p>

                <div class="mt-5 grid gap-5 md:grid-cols-2">
                    <label class="block text-sm">
                        <span class="mb-1 block font-medium"
                            >How many supplier statements do you reconcile per
                            month?</span
                        >
                        <select
                            v-model="form.suppliers_per_month"
                            :class="selectClass"
                            required
                        >
                            <option value="" disabled>Choose…</option>
                            <option
                                v-for="option in intent.suppliers_per_month"
                                :key="option.value"
                                :value="option.value"
                            >
                                {{ option.label }}
                            </option>
                        </select>
                        <InputError
                            :message="form.errors.suppliers_per_month"
                        />
                    </label>

                    <label class="block text-sm">
                        <span class="mb-1 block font-medium"
                            >Which accounting software do you use?</span
                        >
                        <select
                            v-model="form.accounting_software"
                            :class="selectClass"
                            required
                        >
                            <option value="" disabled>Choose…</option>
                            <option
                                v-for="option in intent.accounting_software"
                                :key="option.value"
                                :value="option.value"
                            >
                                {{ option.label }}
                            </option>
                        </select>
                        <input
                            v-if="form.accounting_software === 'other'"
                            v-model="form.accounting_software_other"
                            type="text"
                            maxlength="100"
                            placeholder="Which one?"
                            :class="[selectClass, 'mt-2']"
                        />
                        <InputError
                            :message="form.errors.accounting_software"
                        />
                    </label>
                </div>

                <fieldset class="mt-5">
                    <legend class="mb-2 text-sm font-medium">
                        Would you pay
                        <strong class="font-semibold">{{
                            intent.price
                        }}</strong>
                        to save your suppliers, their mappings and your history?
                    </legend>
                    <div class="flex flex-wrap gap-2 text-sm">
                        <label
                            v-for="option in intent.price_answers"
                            :key="option.value"
                            class="has-[:checked]:border-primary has-[:checked]:bg-accent has-[:focus-visible]:ring-ring/50 flex cursor-pointer items-center gap-2 rounded-md border px-3 py-1.5 has-[:focus-visible]:ring-[3px]"
                        >
                            <input
                                v-model="form.price_answer"
                                type="radio"
                                name="price_answer"
                                class="accent-primary"
                                :value="option.value"
                                required
                            />
                            {{ option.label }}
                        </label>
                    </div>
                    <InputError :message="form.errors.price_answer" />
                </fieldset>

                <label class="mt-5 block max-w-md text-sm">
                    <span class="mb-1 block font-medium"
                        >Email
                        <span class="text-muted-foreground font-normal"
                            >(optional)</span
                        ></span
                    >
                    <input
                        v-model="form.email"
                        type="email"
                        autocomplete="email"
                        :class="selectClass"
                    />
                    <span class="text-muted-foreground mt-1 block text-xs">
                        Only to tell you when saving suppliers is available.
                        Never linked to your files.
                    </span>
                    <InputError :message="form.errors.email" />
                </label>

                <div class="mt-6 flex justify-end">
                    <Button type="submit" :disabled="form.processing">
                        Send my answers
                        <ArrowRight />
                    </Button>
                </div>
            </form>
        </template>
    </section>
</template>
