<script setup lang="ts">
defineProps<{ headers: string[]; rows: string[][] }>();
</script>

<template>
    <div class="overflow-x-auto rounded-md border">
        <table class="w-full text-left text-xs">
            <thead class="bg-muted/60">
                <tr>
                    <th
                        v-for="(header, index) in headers"
                        :key="index"
                        class="px-2 py-1.5 font-medium whitespace-nowrap"
                    >
                        {{ header }}
                    </th>
                </tr>
            </thead>
            <tbody>
                <tr
                    v-for="(row, rowIndex) in rows"
                    :key="rowIndex"
                    class="border-t"
                >
                    <td
                        v-for="(_, cellIndex) in headers"
                        :key="cellIndex"
                        class="max-w-48 truncate px-2 py-1 whitespace-nowrap"
                        :title="row[cellIndex]"
                    >
                        {{ row[cellIndex] }}
                    </td>
                </tr>
            </tbody>
        </table>
    </div>
</template>
