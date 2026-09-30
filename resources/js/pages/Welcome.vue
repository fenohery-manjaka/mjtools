<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { ArrowRight, FileCheck2 } from '@lucide/vue';
import AppLogoIcon from '@/components/AppLogoIcon.vue';
import { dashboard, login, register } from '@/routes';
import { index as supplierReconciliation } from '@/routes/supplier-reconciliation';

// The mjtools home page lists the available tools. Each tool owns its own pages.
const tools = [
    {
        name: 'Supplier Statement Reconciliation Checker',
        description:
            'Compare your supplier statement with your ledger and review only the differences.',
        href: supplierReconciliation(),
        icon: FileCheck2,
        badge: 'Free',
    },
];
</script>

<template>
    <Head title="Tools for finance teams" />

    <div class="bg-muted/30 flex min-h-screen flex-col">
        <header class="bg-background border-b">
            <div
                class="mx-auto flex h-14 w-full max-w-5xl items-center justify-between px-4"
            >
                <div class="flex items-center gap-2 font-semibold">
                    <span
                        class="bg-primary text-primary-foreground flex size-7 items-center justify-center rounded-md"
                    >
                        <AppLogoIcon class="size-4 fill-current" />
                    </span>
                    {{ $page.props.name }}
                </div>
                <nav class="flex items-center gap-4 text-sm">
                    <Link
                        v-if="$page.props.auth.user"
                        :href="dashboard()"
                        class="hover:underline"
                    >
                        Dashboard
                    </Link>
                    <template v-else>
                        <Link :href="login()" class="hover:underline"
                            >Log in</Link
                        >
                        <Link :href="register()" class="hover:underline"
                            >Register</Link
                        >
                    </template>
                </nav>
            </div>
        </header>

        <main class="mx-auto w-full max-w-5xl flex-1 px-4 py-12">
            <h1 class="text-3xl font-semibold tracking-tight">
                Tools for finance teams
            </h1>
            <p class="text-muted-foreground mt-2">
                Practical tools that remove repetitive checks and leave you only
                what needs your judgement.
            </p>

            <div class="mt-8 grid gap-4 md:grid-cols-2">
                <Link
                    v-for="tool in tools"
                    :key="tool.name"
                    :href="tool.href"
                    class="bg-background hover:border-primary/50 group rounded-xl border p-6 transition-colors"
                >
                    <div class="flex items-center justify-between">
                        <component
                            :is="tool.icon"
                            class="text-primary size-6"
                        />
                        <span
                            class="rounded-full bg-emerald-100 px-2 py-0.5 text-xs font-medium text-emerald-800 dark:bg-emerald-950 dark:text-emerald-300"
                        >
                            {{ tool.badge }}
                        </span>
                    </div>
                    <h2 class="mt-4 font-medium">{{ tool.name }}</h2>
                    <p class="text-muted-foreground mt-1 text-sm">
                        {{ tool.description }}
                    </p>
                    <span
                        class="text-primary mt-4 inline-flex items-center gap-1 text-sm font-medium"
                    >
                        Open
                        <ArrowRight
                            class="size-4 transition-transform group-hover:translate-x-0.5"
                        />
                    </span>
                </Link>
            </div>
        </main>
    </div>
</template>
