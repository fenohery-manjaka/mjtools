<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { ArrowRight, Check, FileCheck2 } from '@lucide/vue';
import { index as supplierReconciliation } from '@/routes/supplier-reconciliation';

// The mjtools home page lists the available tools. Each tool owns its own pages.
const tools = [
    {
        name: 'Supplier Statement Checker',
        description:
            'Compare a supplier statement with your ledger and review only the differences.',
        points: [
            'CSV or Excel, imperfect files welcome',
            'Certain matches cleared, doubtful ones shown to you',
            'Every result explained, exportable',
        ],
        href: supplierReconciliation(),
        icon: FileCheck2,
        badge: 'Free · no account',
    },
];

const principles = [
    {
        title: 'Accept imperfect data',
        text: 'Real exports have odd headers, mixed formats and stray total lines. The tools read them as they are instead of asking you to clean them first.',
    },
    {
        title: 'Never hide uncertainty',
        text: 'When two interpretations are possible, the tool asks you. A missing answer is better than a wrong one presented as certain.',
    },
    {
        title: 'Explain every result',
        text: 'You can always see which values were compared, how they were read and why a result was given.',
    },
];

// Purely decorative specimen of a checked ledger.
const specimen = [
    { ref: 'INV-004581', amount: '1,240.00', state: 'ok' },
    { ref: 'INV-004582', amount: '318.50', state: 'ok' },
    { ref: 'CN-00824', amount: '-420.00', state: 'flag' },
    { ref: 'INV-004583', amount: '2,075.00', state: 'ok' },
    { ref: 'INV-004590', amount: '96.10', state: 'ok' },
] as const;
</script>

<template>
    <Head title="Small tools for tedious checks" />

    <section
        class="grid items-center gap-10 py-6 lg:grid-cols-[1.15fr_1fr] lg:py-12"
    >
        <div>
            <p
                class="text-muted-foreground text-sm font-medium tracking-[0.18em] uppercase"
            >
                mjtools
            </p>
            <h1
                class="font-display mt-4 text-4xl leading-[1.08] font-semibold tracking-tight sm:text-5xl"
            >
                Small tools for the checks nobody enjoys.
            </h1>
            <p class="text-muted-foreground mt-5 max-w-xl text-lg">
                Each tool does one repetitive job properly: it reads your files
                as they are, keeps what it is unsure about in front of you and
                explains every result. Free to try, no account needed.
            </p>
            <div class="mt-8 flex flex-wrap items-center gap-4">
                <Link
                    :href="supplierReconciliation()"
                    class="bg-primary text-primary-foreground hover:bg-primary/90 focus-visible:ring-ring/50 inline-flex h-11 items-center gap-2 rounded-md px-5 font-medium shadow-xs transition-colors outline-none focus-visible:ring-[3px]"
                >
                    Check a supplier statement
                    <ArrowRight class="size-4" />
                </Link>
                <span class="text-muted-foreground text-sm">
                    Two files in, only the differences out.
                </span>
            </div>
        </div>

        <div aria-hidden="true" class="relative mx-auto w-full max-w-md">
            <div
                class="bg-card ledger-paper relative overflow-hidden rounded-xl border pt-8 pb-6 shadow-[0_1px_0_0_var(--border),0_24px_48px_-24px_color-mix(in_oklch,var(--foreground)_25%,transparent)]"
            >
                <p
                    class="text-muted-foreground absolute top-2 left-16 text-[0.65rem] tracking-[0.2em] uppercase"
                >
                    Statement · checked
                </p>
                <ul class="figure text-sm">
                    <li
                        v-for="row in specimen"
                        :key="row.ref"
                        class="flex h-8 items-center gap-3 pr-5 pl-16"
                    >
                        <span class="flex-1 truncate">{{ row.ref }}</span>
                        <span class="tabular-nums">{{ row.amount }}</span>
                        <span
                            class="flex size-5 items-center justify-center rounded-full"
                            :class="
                                row.state === 'ok'
                                    ? 'text-success'
                                    : 'bg-warning-soft text-warning-strong ring-warning/50 ring-1'
                            "
                        >
                            <Check v-if="row.state === 'ok'" class="size-4" />
                            <span v-else class="text-xs font-semibold">?</span>
                        </span>
                    </li>
                </ul>
                <p
                    class="font-display text-foreground mt-3 pr-5 pl-16 text-lg leading-snug"
                >
                    1 line needs your attention instead of 5.
                </p>
            </div>
        </div>
    </section>

    <section class="mt-10">
        <h2 class="font-display text-2xl font-semibold tracking-tight">
            Tools
        </h2>
        <div class="mt-5 grid gap-5 md:grid-cols-2">
            <Link
                v-for="tool in tools"
                :key="tool.name"
                :href="tool.href"
                class="group bg-card hover:border-primary/40 focus-visible:ring-ring/50 rounded-xl border p-6 shadow-xs transition-colors outline-none focus-visible:ring-[3px]"
            >
                <div class="flex items-center justify-between gap-3">
                    <span
                        class="bg-accent text-primary flex size-10 items-center justify-center rounded-lg"
                    >
                        <component :is="tool.icon" class="size-5" />
                    </span>
                    <span
                        class="bg-success-soft text-success-strong rounded-full px-2.5 py-0.5 text-xs font-medium"
                    >
                        {{ tool.badge }}
                    </span>
                </div>
                <h3
                    class="font-display mt-5 text-xl font-semibold tracking-tight"
                >
                    {{ tool.name }}
                </h3>
                <p class="text-muted-foreground mt-1">
                    {{ tool.description }}
                </p>
                <ul class="mt-4 space-y-1.5 text-sm">
                    <li
                        v-for="point in tool.points"
                        :key="point"
                        class="flex gap-2"
                    >
                        <Check class="text-success mt-0.5 size-4 shrink-0" />
                        {{ point }}
                    </li>
                </ul>
                <span
                    class="text-primary mt-6 inline-flex items-center gap-1 text-sm font-medium"
                >
                    Open the tool
                    <ArrowRight
                        class="size-4 transition-transform group-hover:translate-x-0.5"
                    />
                </span>
            </Link>

            <div
                class="text-muted-foreground border-foreground/15 flex flex-col justify-center rounded-xl border border-dashed p-6"
            >
                <p class="text-foreground font-medium">The next tool</p>
                <p class="mt-1 text-sm">
                    New tools are added only when a real, repeated chore shows
                    up — not because a feature looks interesting.
                </p>
            </div>
        </div>
    </section>

    <section class="mt-16 border-t pt-10">
        <h2 class="font-display text-2xl font-semibold tracking-tight">
            How every mjtools tool behaves
        </h2>
        <ol class="mt-6 grid gap-8 md:grid-cols-3">
            <li v-for="(principle, index) in principles" :key="principle.title">
                <span class="figure text-muted-foreground text-sm"
                    >0{{ index + 1 }}</span
                >
                <h3 class="mt-2 font-semibold">{{ principle.title }}</h3>
                <p class="text-muted-foreground mt-1 text-sm leading-relaxed">
                    {{ principle.text }}
                </p>
            </li>
        </ol>
    </section>
</template>
