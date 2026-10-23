<script setup>
import { ref } from 'vue';
import { Link } from '@inertiajs/vue3';
import ApplicationLogo from '@/Components/ApplicationLogo.vue';
import IconMenu from '@/Components/Icons/IconMenu.vue';
import IconClose from '@/Components/Icons/IconClose.vue';

const isSidebarOpen = ref(false);

const links = [
    { label: 'Dashboard', route: 'admin.dashboard', match: 'admin.dashboard' },
    { label: 'Categories', route: 'admin.categories.index', match: 'admin.categories.*' },
    { label: 'Products', route: 'admin.products.index', match: 'admin.products.*' },
    { label: 'Pages', route: 'admin.pages.index', match: 'admin.pages.*' },
    { label: 'Settings', route: 'admin.settings.edit', match: 'admin.settings.*' },
];
</script>

<template>
    <div class="min-h-screen bg-gray-100 md:flex">
        <div class="flex items-center justify-between border-b bg-white px-4 py-3 md:hidden">
            <Link :href="route('admin.dashboard')" class="flex items-center gap-2 font-semibold">
                <ApplicationLogo class="h-8 w-auto" />
                Admin
            </Link>
            <button
                type="button"
                class="rounded p-2 text-gray-600 hover:bg-gray-100"
                :aria-label="isSidebarOpen ? 'Close menu' : 'Open menu'"
                :aria-expanded="isSidebarOpen"
                @click="isSidebarOpen = !isSidebarOpen"
            >
                <IconClose v-if="isSidebarOpen" class="h-6 w-6" />
                <IconMenu v-else class="h-6 w-6" />
            </button>
        </div>

        <aside
            :class="isSidebarOpen ? 'block' : 'hidden'"
            class="w-full border-r bg-white md:block md:min-h-screen md:w-60"
        >
            <div class="hidden px-6 py-5 md:block">
                <Link :href="route('admin.dashboard')" class="flex items-center gap-2 font-semibold">
                    <ApplicationLogo class="h-8 w-auto" />
                    Admin
                </Link>
            </div>
            <nav class="space-y-1 px-3 pb-4">
                <Link
                    v-for="link in links"
                    :key="link.route"
                    :href="route(link.route)"
                    :class="route().current(link.match) ? 'bg-gray-100 text-gray-900' : 'text-gray-600 hover:bg-gray-50'"
                    class="block rounded px-3 py-2 text-sm font-medium"
                >
                    {{ link.label }}
                </Link>
                <hr class="my-3" />
                <Link href="/" class="block rounded px-3 py-2 text-sm text-gray-600 hover:bg-gray-50">View store</Link>
                <Link
                    :href="route('logout')"
                    method="post"
                    as="button"
                    class="block w-full rounded px-3 py-2 text-left text-sm text-gray-600 hover:bg-gray-50"
                >
                    Log out
                </Link>
            </nav>
        </aside>

        <main class="flex-1 p-4 md:p-8">
            <header v-if="$slots.header" class="mb-6">
                <slot name="header" />
            </header>
            <p
                v-if="$page.props.flash?.status"
                class="mb-4 rounded bg-green-50 px-4 py-2 text-sm text-green-800"
                role="status"
            >
                {{ $page.props.flash.status }}
            </p>
            <slot />
        </main>
    </div>
</template>
