<script setup>
import StoreLayout from '@/Layouts/StoreLayout.vue';
import DeleteUserForm from './Partials/DeleteUserForm.vue';
import UpdatePasswordForm from './Partials/UpdatePasswordForm.vue';
import UpdateProfileInformationForm from './Partials/UpdateProfileInformationForm.vue';
import { Head, Link } from '@inertiajs/vue3';

defineProps({
    mustVerifyEmail: {
        type: Boolean,
    },
    status: {
        type: String,
    },
});
</script>

<template>
    <Head title="Profile & password" />

    <StoreLayout>
        <div class="mx-auto max-w-3xl px-4 py-8 sm:py-12">
            <Link :href="route('account')" class="store-link">← Your account</Link>
            <h1 class="mt-4 font-display text-3xl font-semibold sm:text-4xl">Profile & password</h1>

            <div class="mt-10 max-w-xl divide-y divide-brand-200">
                <UpdateProfileInformationForm :must-verify-email="mustVerifyEmail" :status="status" class="pb-10" />
                <UpdatePasswordForm class="py-10" />
                <DeleteUserForm class="pt-10" />
            </div>
        </div>
    </StoreLayout>
</template>
