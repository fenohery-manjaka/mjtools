<script setup lang="ts">
import { Check, ShieldCheck } from '@lucide/vue';

defineProps<{ retentionHours: number }>();
</script>

<template>
    <div class="bg-card rounded-xl border p-6">
        <div class="flex items-center gap-2 font-semibold">
            <ShieldCheck class="text-success size-5" />
            Your data, handled carefully
        </div>
        <ul class="text-muted-foreground mt-4 space-y-2.5 text-sm">
            <li class="flex gap-2.5">
                <Check class="text-success mt-0.5 size-4 shrink-0" />
                <span
                    >Your files are used only to reconcile them against each
                    other.</span
                >
            </li>
            <li class="flex gap-2.5">
                <Check class="text-success mt-0.5 size-4 shrink-0" />
                <span
                    >The uploaded file itself is never stored: we keep only the
                    rows read from it, the results and your decisions.</span
                >
            </li>
            <li class="flex gap-2.5">
                <Check class="text-success mt-0.5 size-4 shrink-0" />
                <span
                    >Everything is deleted automatically after
                    <strong class="text-foreground font-medium"
                        >{{ retentionHours }} hours</strong
                    >, and you can delete it yourself at any time.</span
                >
            </li>
            <li class="flex gap-2.5">
                <Check class="text-success mt-0.5 size-4 shrink-0" />
                <span
                    >Results are tied to this browser: nobody else can see them.
                    Your data is never sold or made public.</span
                >
            </li>
        </ul>
    </div>
</template>
