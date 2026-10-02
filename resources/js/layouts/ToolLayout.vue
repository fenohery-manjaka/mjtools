<script setup lang="ts">
import { Link, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';
import BrandMark from '@/components/BrandMark.vue';
import ThemeToggle from '@/components/ThemeToggle.vue';
import { Toaster } from '@/components/ui/sonner';
import { dashboard, home, login } from '@/routes';

// Public layout shared by the mjtools home and every tool: tools are usable without an account.
const page = usePage();
const user = computed(() => page.props.auth.user);
</script>

<template>
    <div class="flex min-h-screen flex-col">
        <header
            class="bg-background/85 supports-[backdrop-filter]:bg-background/70 sticky top-0 z-30 border-b backdrop-blur"
        >
            <div
                class="mx-auto flex h-16 w-full max-w-6xl items-center justify-between px-4 sm:px-6"
            >
                <Link
                    :href="home()"
                    class="rounded-md"
                    aria-label="mjtools home"
                >
                    <BrandMark />
                </Link>
                <nav class="flex items-center gap-1 text-sm sm:gap-3">
                    <ThemeToggle />
                    <Link
                        v-if="user"
                        :href="dashboard()"
                        class="text-muted-foreground hover:text-foreground rounded-md px-2 py-1 font-medium"
                    >
                        Dashboard
                    </Link>
                    <Link
                        v-else
                        :href="login()"
                        class="text-muted-foreground hover:text-foreground rounded-md px-2 py-1 font-medium"
                    >
                        Log in
                    </Link>
                </nav>
            </div>
        </header>

        <main
            class="mx-auto w-full max-w-6xl flex-1 px-4 py-8 sm:px-6 sm:py-10"
        >
            <slot />
        </main>

        <footer class="border-t">
            <div
                class="text-muted-foreground mx-auto flex w-full max-w-6xl flex-col gap-3 px-4 py-8 text-sm sm:flex-row sm:items-center sm:justify-between sm:px-6"
            >
                <BrandMark size="sm" class="text-foreground" />
                <p class="max-w-xl sm:text-right">
                    Accept imperfect data. Never hide uncertainty. Explain every
                    result.
                </p>
            </div>
        </footer>
        <Toaster />
    </div>
</template>
