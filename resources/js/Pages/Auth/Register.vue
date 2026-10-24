<script setup>
import GuestLayout from '@/Layouts/GuestLayout.vue';
import InputError from '@/Components/InputError.vue';
import StoreInput from '@/Components/Store/StoreInput.vue';
import { Head, Link, useForm } from '@inertiajs/vue3';

const form = useForm({
    name: '',
    email: '',
    password: '',
    password_confirmation: '',
});

const submit = () => {
    form.post(route('register'), {
        onFinish: () => form.reset('password', 'password_confirmation'),
    });
};
</script>

<template>
    <GuestLayout title="Create an account">
        <Head title="Register" />

        <form @submit.prevent="submit">
            <div>
                <label for="name" class="store-label">Name</label>

                <StoreInput
                    id="name"
                    type="text"
                    v-model="form.name"
                    required
                    autofocus
                    autocomplete="name"
                />

                <InputError class="mt-2" :message="form.errors.name" />
            </div>

            <div class="mt-6">
                <label for="email" class="store-label">Email</label>

                <StoreInput
                    id="email"
                    type="email"
                    v-model="form.email"
                    required
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
                    autocomplete="new-password"
                />

                <InputError class="mt-2" :message="form.errors.password" />
            </div>

            <div class="mt-6">
                <label for="password_confirmation" class="store-label">Confirm Password</label>

                <StoreInput
                    id="password_confirmation"
                    type="password"
                    v-model="form.password_confirmation"
                    required
                    autocomplete="new-password"
                />

                <InputError
                    class="mt-2"
                    :message="form.errors.password_confirmation"
                />
            </div>

            <div class="mt-8 flex flex-wrap items-center justify-end gap-4">
                <Link
                    :href="route('login')"
                    class="store-link"
                >
                    Already registered?
                </Link>

                <button class="store-button" :disabled="form.processing">
                    Register
                </button>
            </div>
        </form>
    </GuestLayout>
</template>
