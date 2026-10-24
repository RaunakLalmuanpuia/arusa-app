<script setup>
import PageHeading from '@/Components/Store/PageHeading.vue';
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
            <PageHeading eyebrow="Your account" title="Profile & password" class="mt-4" />

            <div class="mt-10 max-w-xl space-y-8">
                <UpdateProfileInformationForm :must-verify-email="mustVerifyEmail" :status="status" class="border border-brand-200 p-6 sm:p-8" />
                <UpdatePasswordForm class="border border-brand-200 p-6 sm:p-8" />
                <DeleteUserForm class="border border-red-200 p-6 sm:p-8" />
            </div>
        </div>
    </StoreLayout>
</template>
