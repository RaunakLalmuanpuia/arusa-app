<script setup>
import { ref } from 'vue';
import { Link, usePage } from '@inertiajs/vue3';
import ApplicationLogo from '@/Components/ApplicationLogo.vue';
import IconMenu from '@/Components/Icons/IconMenu.vue';
import IconClose from '@/Components/Icons/IconClose.vue';
import IconUser from '@/Components/Icons/IconUser.vue';
import IconSearch from '@/Components/Icons/IconSearch.vue';
import IconChat from '@/Components/Icons/IconChat.vue';
import IconBag from '@/Components/Icons/IconBag.vue';
import CartDrawer from '@/Components/Store/CartDrawer.vue';
import { isCartOpen } from '@/cartDrawer';

const page = usePage();
const isMenuOpen = ref(false);

const infoLinks = [
    { label: 'About', slug: 'about' },
    { label: 'Size Guide', slug: 'size-guide' },
    { label: 'Shipping & Returns', slug: 'shipping-returns' },
    { label: 'Contact', slug: 'contact' },
];

const focusRing = 'focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-brand-200';
</script>

<template>
    <div class="flex min-h-dvh flex-col bg-brand-50 font-sans text-ink antialiased">
        <a
            href="#main"
            class="sr-only z-50 bg-brand-50 px-4 py-2 text-sm text-ink focus:not-sr-only focus:fixed focus:left-4 focus:top-4"
        >
            Skip to content
        </a>

        <header class="sticky top-0 z-30 bg-brand-900 text-brand-50">
            <div class="mx-auto grid h-16 max-w-6xl grid-cols-[1fr_auto_1fr] items-center px-2 sm:px-4 md:flex md:justify-between">
                <button
                    type="button"
                    class="inline-flex h-11 w-11 items-center justify-center rounded md:hidden"
                    :class="focusRing"
                    :aria-label="isMenuOpen ? 'Close menu' : 'Open menu'"
                    :aria-expanded="isMenuOpen"
                    aria-controls="mobile-menu"
                    @click="isMenuOpen = !isMenuOpen"
                >
                    <IconClose v-if="isMenuOpen" class="h-6 w-6" />
                    <IconMenu v-else class="h-6 w-6" />
                </button>

                <Link :href="route('home')" class="flex items-center gap-3 rounded" :class="focusRing">
                    <ApplicationLogo on-dark class="h-11 w-auto" />
                    <span class="hidden font-display text-xl font-semibold uppercase tracking-[0.2em] sm:inline">
                        {{ page.props.store.store_name }}
                    </span>
                </Link>

                <nav aria-label="Shop" class="hidden gap-8 md:flex">
                    <Link
                        v-for="category in page.props.categories"
                        :key="category.slug"
                        :href="route('categories.show', category.slug)"
                        class="rounded py-2 text-xs font-medium uppercase tracking-[0.2em] text-brand-200 transition-colors duration-200 hover:text-brand-50"
                        :class="focusRing"
                    >
                        {{ category.name }}
                    </Link>
                </nav>

                <div class="flex items-center justify-end gap-2">
                    <Link
                        v-if="page.props.auth.user?.is_admin"
                        :href="route('admin.dashboard')"
                        class="hidden rounded px-2 py-2 text-xs font-medium uppercase tracking-[0.2em] text-brand-200 underline-offset-4 hover:underline md:inline"
                        :class="focusRing"
                    >
                        Admin
                    </Link>
                    <Link
                        :href="route('search')"
                        class="inline-flex h-11 w-11 items-center justify-center rounded transition-colors duration-200 hover:text-brand-200"
                        :class="focusRing"
                        aria-label="Search"
                    >
                        <IconSearch class="h-6 w-6" />
                    </Link>
                    <button
                        type="button"
                        class="relative inline-flex h-11 w-11 items-center justify-center rounded transition-colors duration-200 hover:text-brand-200"
                        :class="focusRing"
                        :aria-label="`Bag, ${page.props.cart.count} items`"
                        @click="isCartOpen = true"
                    >
                        <IconBag class="h-6 w-6" />
                        <span
                            v-if="page.props.cart.count"
                            class="absolute right-1 top-1 flex h-4 min-w-4 items-center justify-center rounded-full bg-brand-50 px-1 text-[10px] font-semibold text-ink"
                            aria-hidden="true"
                        >
                            {{ page.props.cart.count }}
                        </span>
                    </button>
                    <Link
                        :href="page.props.auth.user ? route('dashboard') : route('login')"
                        class="inline-flex h-11 w-11 items-center justify-center rounded transition-colors duration-200 hover:text-brand-200"
                        :class="focusRing"
                        :aria-label="page.props.auth.user ? 'My account' : 'Log in'"
                    >
                        <IconUser class="h-6 w-6" />
                    </Link>
                </div>
            </div>

            <Transition
                enter-active-class="transition duration-200 ease-out motion-reduce:transition-none"
                enter-from-class="-translate-y-2 opacity-0"
                leave-active-class="transition duration-150 ease-in motion-reduce:transition-none"
                leave-to-class="-translate-y-2 opacity-0"
            >
                <nav
                    v-if="isMenuOpen"
                    id="mobile-menu"
                    aria-label="Menu"
                    class="absolute inset-x-0 top-full border-t border-brand-800 bg-brand-900 px-4 pb-4 md:hidden"
                >
                    <Link
                        v-for="category in page.props.categories"
                        :key="category.slug"
                        :href="route('categories.show', category.slug)"
                        class="flex min-h-11 items-center border-b border-brand-800 text-sm font-medium uppercase tracking-[0.2em]"
                        :class="focusRing"
                        @click="isMenuOpen = false"
                    >
                        {{ category.name }}
                    </Link>
                    <div v-if="page.props.categories.length" class="border-b border-brand-700" aria-hidden="true" />
                    <Link
                        v-for="link in infoLinks"
                        :key="link.slug"
                        :href="route('pages.show', link.slug)"
                        class="flex min-h-11 items-center border-b border-brand-800 text-sm font-medium uppercase tracking-[0.2em]"
                        :class="focusRing"
                        @click="isMenuOpen = false"
                    >
                        {{ link.label }}
                    </Link>
                    <Link
                        v-if="page.props.auth.user?.is_admin"
                        :href="route('admin.dashboard')"
                        class="flex min-h-11 items-center text-sm font-medium uppercase tracking-[0.2em] text-brand-200"
                        :class="focusRing"
                    >
                        Admin
                    </Link>
                </nav>
            </Transition>
        </header>

        <main id="main" class="flex-1">
            <slot />
        </main>

        <footer class="bg-brand-900 text-brand-100">
            <div class="mx-auto grid max-w-6xl gap-10 px-4 py-12 text-sm sm:grid-cols-2">
                <div>
                    <p class="font-display text-lg font-semibold uppercase tracking-[0.2em] text-brand-50">
                        {{ page.props.store.store_name }}
                    </p>
                    <p class="mt-1 text-xs uppercase tracking-[0.2em] text-brand-300">Aizawl, Mizoram</p>
                    <div class="mt-6 space-y-1">
                        <p v-if="page.props.store.contact_phone">
                            <a :href="`tel:+91${page.props.store.contact_phone}`" class="rounded hover:text-brand-50" :class="focusRing">
                                +91 {{ page.props.store.contact_phone }}
                            </a>
                        </p>
                        <p v-if="page.props.store.contact_email">
                            <a :href="`mailto:${page.props.store.contact_email}`" class="rounded hover:text-brand-50" :class="focusRing">
                                {{ page.props.store.contact_email }}
                            </a>
                        </p>
                    </div>
                    <a
                        v-if="page.props.store.whatsapp_number"
                        :href="`https://wa.me/91${page.props.store.whatsapp_number}`"
                        target="_blank"
                        rel="noopener"
                        class="mt-6 inline-flex min-h-11 items-center gap-2 rounded-full border border-brand-600 px-5 text-xs font-medium uppercase tracking-[0.2em] text-brand-50 transition-colors duration-200 hover:bg-brand-800"
                        :class="focusRing"
                    >
                        <IconChat class="h-5 w-5" />
                        Chat on WhatsApp
                    </a>
                </div>
                <nav aria-label="Footer" class="flex flex-col sm:items-end">
                    <Link
                        v-for="link in infoLinks"
                        :key="link.slug"
                        :href="route('pages.show', link.slug)"
                        class="flex min-h-11 items-center rounded text-xs uppercase tracking-[0.2em] hover:text-brand-50"
                        :class="focusRing"
                    >
                        {{ link.label }}
                    </Link>
                </nav>
            </div>
        </footer>

        <CartDrawer />
    </div>
</template>
