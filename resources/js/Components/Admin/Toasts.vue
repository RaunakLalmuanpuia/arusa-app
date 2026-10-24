<script setup>
import { onMounted } from 'vue';
import { usePage } from '@inertiajs/vue3';
import IconAlert from '@/Components/Icons/IconAlert.vue';
import IconCheck from '@/Components/Icons/IconCheck.vue';
import IconClose from '@/Components/Icons/IconClose.vue';
import { dismissToast, pauseToast, readInitialPage, resumeToast, toasts } from './toasts.js';

const page = usePage();

onMounted(() => readInitialPage(page.props));
</script>

<template>
    <div class="pointer-events-none fixed inset-x-4 bottom-24 z-[60] flex flex-col items-center gap-2 md:inset-x-auto md:bottom-6 md:right-6 md:items-end" aria-live="polite">
        <TransitionGroup
            enter-active-class="transition duration-200 ease-out motion-reduce:transition-none"
            enter-from-class="translate-y-2 opacity-0"
            leave-active-class="transition duration-150 ease-in motion-reduce:transition-none"
            leave-to-class="opacity-0"
        >
            <div
                v-for="toast in toasts"
                :key="toast.id"
                :role="toast.tone === 'error' ? 'alert' : 'status'"
                class="pointer-events-auto flex w-full max-w-sm items-start gap-3 rounded-xl border bg-white px-4 py-3 text-sm text-ink shadow-lg shadow-ink/5"
                :class="toast.tone === 'error' ? 'border-red-200' : 'border-brand-200'"
                @mouseenter="pauseToast(toast.id)"
                @mouseleave="resumeToast(toast.id)"
                @focusin="pauseToast(toast.id)"
                @focusout="resumeToast(toast.id)"
            >
                <IconAlert v-if="toast.tone === 'error'" class="mt-0.5 h-5 w-5 shrink-0 text-red-600" />
                <IconCheck v-else class="mt-0.5 h-5 w-5 shrink-0 text-emerald-600" />
                <p class="flex-1">{{ toast.message }}</p>
                <button type="button" class="focus-ring -m-1 rounded p-1 text-brand-500 hover:text-ink" aria-label="Dismiss" @click="dismissToast(toast.id)">
                    <IconClose class="h-4 w-4" />
                </button>
            </div>
        </TransitionGroup>
    </div>
</template>
