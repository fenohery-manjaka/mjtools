<script setup lang="ts">
import { ShieldCheck } from '@lucide/vue';

defineProps<{ retentionHours: number }>();
</script>

<template>
    <div class="bg-background rounded-xl border p-5">
        <div class="mb-3 flex items-center gap-2 font-medium">
            <ShieldCheck class="size-5 text-emerald-600" />
            Your data, handled carefully
        </div>
        <ul class="text-muted-foreground list-disc space-y-1 pl-5 text-sm">
            <li>
                Your files are used only to reconcile them against each other.
            </li>
            <li>
                The uploaded file itself is never stored: we keep only the rows
                read from it, the results and your decisions.
            </li>
            <li>
                Everything is deleted automatically after
                {{ retentionHours }} hours, and you can delete it yourself at
                any time.
            </li>
            <li>
                Results are tied to your browser session: nobody else can see
                them. Your data is never sold or made public.
            </li>
        </ul>
    </div>
</template>
