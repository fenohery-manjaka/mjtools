<script setup lang="ts">
import { Form } from '@inertiajs/vue3';
import { Trash2 } from '@lucide/vue';
import { Button } from '@/components/ui/button';
import { destroy } from '@/routes/supplier-reconciliation/runs';

defineProps<{ runId: string }>();

function confirmDeletion(): boolean {
    return window.confirm(
        'Delete your files and results now? This cannot be undone.',
    );
}
</script>

<template>
    <Form
        v-bind="destroy.form(runId)"
        v-slot="{ processing }"
        :on-before="confirmDeletion"
    >
        <Button
            type="submit"
            variant="ghost"
            size="sm"
            class="text-muted-foreground hover:text-danger"
            :disabled="processing"
        >
            <Trash2 />
            Delete my data now
        </Button>
    </Form>
</template>
