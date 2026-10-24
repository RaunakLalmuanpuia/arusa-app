<script setup>
import GuestLayout from '@/Layouts/GuestLayout.vue';
import InputError from '@/Components/InputError.vue';
import StoreInput from '@/Components/Store/StoreInput.vue';
import { Head, Link, useForm } from '@inertiajs/vue3';

defineProps({
    canResetPassword: {
        type: Boolean,
    },
    status: {
        type: String,
    },
});

const form = useForm({
    email: '',
    password: '',
    remember: false,
});

const submit = () => {
    form.post(route('login'), {
        onFinish: () => form.reset('password'),
    });
};
</script>

<template>
    <GuestLayout title="Log in">
        <Head title="Log in" />

        <div v-if="status" class="mb-4 text-sm font-medium text-brand-800">
            {{ status }}
        </div>

        <form @submit.prevent="submit">
            <div>
                <label for="email" class="store-label">Email</label>

                <StoreInput
                    id="email"
                    type="email"
                    v-model="form.email"
                    required
                    autofocus
                    autocomplete="username"
                />

                <InputError class="mt-2" :message="form.errors.email" />
            </div>

            <div class="mt-6">
                <label for="password" class="store-label">Password</label>

                <StoreInput
                    id="password"
                    type="password"
                    v-model="form.password"
                    required
                    autocomplete="current-password"
                />

                <InputError class="mt-2" :message="form.errors.password" />
            </div>

            <div class="mt-4 block">
                <label class="flex min-h-11 items-center">
                    <input v-model="form.remember" type="checkbox" name="remember" class="h-5 w-5 border-brand-300 text-ink focus:ring-ink" />
                    <span class="ms-2 text-sm text-brand-700">Remember me</span>
                </label>
            </div>

            <div class="mt-8 flex flex-wrap items-center justify-end gap-4">
                <Link
                    v-if="canResetPassword"
                    :href="route('password.request')"
                    class="store-link"
                >
                    Forgot your password?
                </Link>

                <button class="store-button" :disabled="form.processing">
                    Log in
                </button>
            </div>
        </form>
    </GuestLayout>
</template>
