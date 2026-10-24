<script setup>
import { nextTick, onBeforeUnmount, onMounted, ref } from 'vue';
import { Link } from '@inertiajs/vue3';
import Toasts from '@/Components/Admin/Toasts.vue';
import IconBag from '@/Components/Icons/IconBag.vue';
import IconBox from '@/Components/Icons/IconBox.vue';
import IconClose from '@/Components/Icons/IconClose.vue';
import IconDocument from '@/Components/Icons/IconDocument.vue';
import IconExternal from '@/Components/Icons/IconExternal.vue';
import IconHome from '@/Components/Icons/IconHome.vue';
import IconLogout from '@/Components/Icons/IconLogout.vue';
import IconMenu from '@/Components/Icons/IconMenu.vue';
import IconSliders from '@/Components/Icons/IconSliders.vue';
import IconTag from '@/Components/Icons/IconTag.vue';
import IconTruck from '@/Components/Icons/IconTruck.vue';
import logo256 from '../../images/logo-256.jpg';
import logo512 from '../../images/logo-512.jpg';

const isDrawerOpen = ref(false);
const menuButton = ref(null);
const nav = ref(null);

const links = [
    { label: 'Dashboard', route: 'admin.dashboard', match: 'admin.dashboard', icon: IconHome },
    { label: 'Orders', route: 'admin.orders.index', match: 'admin.orders.*', icon: IconBag },
    { label: 'Categories', route: 'admin.categories.index', match: 'admin.categories.*', icon: IconTag },
    { label: 'Products', route: 'admin.products.index', match: 'admin.products.*', icon: IconBox },
    { label: 'Shipping zones', route: 'admin.shipping-zones.index', match: 'admin.shipping-zones.*', icon: IconTruck },
    { label: 'Pages', route: 'admin.pages.index', match: 'admin.pages.*', icon: IconDocument },
    { label: 'Settings', route: 'admin.settings.edit', match: 'admin.settings.*', icon: IconSliders },
];

const linkClass = 'focus-ring flex items-center gap-3 rounded-lg border px-3 py-2 text-sm font-medium transition-colors duration-150 motion-reduce:transition-none';

const openDrawer = async () => {
    isDrawerOpen.value = true;
    await nextTick();
    nav.value?.querySelector('a')?.focus();
};

const closeDrawer = () => {
    isDrawerOpen.value = false;
    menuButton.value?.focus();
};

const onKeydown = (event) => {
    if (event.key === 'Escape' && isDrawerOpen.value) {
        closeDrawer();
    }
};

onMounted(() => window.addEventListener('keydown', onKeydown));
onBeforeUnmount(() => window.removeEventListener('keydown', onKeydown));
</script>

<template>
    <div class="min-h-screen bg-brand-50 font-sans text-ink antialiased md:flex">
        <header class="sticky top-0 z-30 flex items-center justify-between border-b border-brand-200 bg-brand-50/90 px-4 py-2 backdrop-blur md:hidden">
            <Link :href="route('admin.dashboard')" class="focus-ring flex items-center gap-2 rounded font-semibold">
                <!-- JPG has a white background: multiply blends it into the cream surface -->
                <img :src="logo256" :srcset="`${logo256} 256w, ${logo512} 512w`" sizes="32px" width="256" height="256" alt="" class="h-8 w-8 mix-blend-multiply" />
                Arusa
                <span class="text-xs font-normal text-brand-500">Admin</span>
            </Link>
            <button
                ref="menuButton"
                type="button"
                class="focus-ring rounded-lg p-2 text-brand-700 hover:bg-white"
                aria-label="Open menu"
                aria-controls="admin-sidebar"
                :aria-expanded="isDrawerOpen"
                @click="openDrawer"
            >
                <IconMenu class="h-6 w-6" />
            </button>
        </header>

        <Transition
            enter-active-class="transition-opacity duration-200 motion-reduce:transition-none"
            enter-from-class="opacity-0"
            leave-active-class="transition-opacity duration-200 motion-reduce:transition-none"
            leave-to-class="opacity-0"
        >
            <div v-if="isDrawerOpen" class="fixed inset-0 z-40 bg-ink/30 md:hidden" aria-hidden="true" @click="closeDrawer" />
        </Transition>

        <aside
            id="admin-sidebar"
            :role="isDrawerOpen ? 'dialog' : null"
            :aria-modal="isDrawerOpen ? 'true' : null"
            aria-label="Admin menu"
            :class="isDrawerOpen ? 'visible translate-x-0' : 'invisible -translate-x-full'"
            class="fixed inset-y-0 left-0 z-50 flex w-72 flex-col border-r border-brand-200 bg-brand-50 transition-[transform,visibility] duration-200 ease-out motion-reduce:transition-none md:visible md:sticky md:top-0 md:z-auto md:h-screen md:w-60 md:translate-x-0"
        >
            <div class="flex items-center justify-between px-5 py-5">
                <Link :href="route('admin.dashboard')" class="focus-ring flex items-center gap-3 rounded">
                    <img :src="logo256" :srcset="`${logo256} 256w, ${logo512} 512w`" sizes="36px" width="256" height="256" alt="" class="h-9 w-9 mix-blend-multiply" />
                    <span class="leading-tight">
                        <span class="block font-semibold">Arusa</span>
                        <span class="block text-xs text-brand-500">Admin</span>
                    </span>
                </Link>
                <button type="button" class="focus-ring rounded-lg p-2 text-brand-700 hover:bg-white md:hidden" aria-label="Close menu" @click="closeDrawer">
                    <IconClose class="h-5 w-5" />
                </button>
            </div>

            <nav ref="nav" class="flex-1 space-y-0.5 overflow-y-auto px-3" aria-label="Admin">
                <Link
                    v-for="link in links"
                    :key="link.route"
                    :href="route(link.route)"
                    :aria-current="route().current(link.match) ? 'page' : null"
                    :class="[linkClass, route().current(link.match) ? 'border-brand-200 bg-white text-ink shadow-sm' : 'border-transparent text-brand-600 hover:bg-white/60 hover:text-ink']"
                >
                    <component :is="link.icon" class="h-5 w-5 shrink-0" />
                    {{ link.label }}
                </Link>
            </nav>

            <div class="space-y-0.5 border-t border-brand-200 px-3 py-4">
                <a href="/" target="_blank" rel="noopener" :class="[linkClass, 'border-transparent text-brand-600 hover:bg-white/60 hover:text-ink']">
                    <IconExternal class="h-5 w-5 shrink-0" />
                    View store
                </a>
                <Link
                    :href="route('logout')"
                    method="post"
                    as="button"
                    :class="[linkClass, 'w-full border-transparent text-left text-brand-600 hover:bg-white/60 hover:text-ink']"
                >
                    <IconLogout class="h-5 w-5 shrink-0" />
                    Log out
                </Link>
            </div>
        </aside>

        <main class="min-w-0 flex-1">
            <div class="mx-auto max-w-6xl px-4 py-6 md:px-8 md:py-8">
                <header v-if="$slots.header" class="mb-6">
                    <slot name="header" />
                </header>
                <slot />
            </div>
        </main>

        <Toasts />
    </div>
</template>
