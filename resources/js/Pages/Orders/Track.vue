<script setup>
import GuestLayout from '@/Layouts/GuestLayout.vue';
import InputError from '@/Components/InputError.vue';
import StoreInput from '@/Components/Store/StoreInput.vue';
import { Head, Link, useForm } from '@inertiajs/vue3';

const form = useForm({
    number: '',
    phone: '',
});

const submit = () => {
    form.post(route('orders.track.store'));
};
</script>

<template>
    <GuestLayout title="Track your order">
        <Head title="Track your order" />

        <form @submit.prevent="submit">
            <div>
                <label for="number" class="store-label">Order number</label>
                <StoreInput id="number" v-model="form.number" required autofocus autocomplete="off" aria-describedby="number-hint" />
                <p id="number-hint" class="mt-1 text-xs text-brand-600">On your confirmation page and emails, e.g. ARU-000123.</p>
                <InputError class="mt-2" :message="form.errors.number" />
            </div>

            <div class="mt-6">
                <label for="phone" class="store-label">Phone used at checkout</label>
                <StoreInput id="phone" type="tel" inputmode="numeric" v-model="form.phone" required autocomplete="tel-national" maxlength="10" />
                <InputError class="mt-2" :message="form.errors.phone" />
            </div>

            <div class="mt-8 flex justify-end">
                <button class="store-button" :disabled="form.processing">Track order</button>
            </div>
        </form>

        <p v-if="$page.props.auth.user" class="mt-10 border-t border-brand-200 pt-6 text-sm text-brand-700">
            Signed in? <Link :href="route('account')" class="store-link">See all your orders in your account</Link>
        </p>
    </GuestLayout>
</template>
