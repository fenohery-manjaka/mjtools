<script setup lang="ts">
import { Link, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';
import AppLogoIcon from '@/components/AppLogoIcon.vue';
import { Toaster } from '@/components/ui/sonner';
import { dashboard, home, login } from '@/routes';

// Public layout shared by every mjtools tool: tools are usable without an account.
const page = usePage();
const user = computed(() => page.props.auth.user);
</script>

<template>
    <div class="bg-muted/30 flex min-h-screen flex-col">
        <header class="bg-background border-b">
            <div
                class="mx-auto flex h-14 w-full max-w-6xl items-center justify-between px-4"
            >
                <Link
                    :href="home()"
                    class="flex items-center gap-2 font-semibold"
                >
                    <span
                        class="bg-primary text-primary-foreground flex size-7 items-center justify-center rounded-md"
                    >
                        <AppLogoIcon class="size-4 fill-current" />
                    </span>
                    {{ page.props.name }}
                </Link>
                <nav class="flex items-center gap-4 text-sm">
                    <Link
                        v-if="user"
                        :href="dashboard()"
                        class="text-muted-foreground hover:text-foreground"
                    >
                        Dashboard
                    </Link>
                    <Link
                        v-else
                        :href="login()"
                        class="text-muted-foreground hover:text-foreground"
                    >
                        Log in
                    </Link>
                </nav>
            </div>
        </header>

        <main class="mx-auto w-full max-w-6xl flex-1 px-4 py-8">
            <slot />
        </main>

        <footer class="text-muted-foreground border-t py-6 text-center text-xs">
            {{ page.props.name }}
        </footer>
        <Toaster />
    </div>
</template>
