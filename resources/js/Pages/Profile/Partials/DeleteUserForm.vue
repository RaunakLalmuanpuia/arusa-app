<script setup>
import InputError from '@/Components/InputError.vue';
import Modal from '@/Components/Modal.vue';
import StoreInput from '@/Components/Store/StoreInput.vue';
import { useForm } from '@inertiajs/vue3';
import { nextTick, ref } from 'vue';

const confirmingUserDeletion = ref(false);
const passwordInput = ref(null);

const form = useForm({
    password: '',
});

const confirmUserDeletion = () => {
    confirmingUserDeletion.value = true;

    nextTick(() => passwordInput.value.focus());
};

const deleteUser = () => {
    form.delete(route('profile.destroy'), {
        preserveScroll: true,
        onSuccess: () => closeModal(),
        onError: () => passwordInput.value.focus(),
        onFinish: () => form.reset(),
    });
};

const closeModal = () => {
    confirmingUserDeletion.value = false;

    form.clearErrors();
    form.reset();
};
</script>

<template>
    <section class="space-y-6">
        <header>
            <h2 class="font-display text-2xl font-semibold">
                Delete Account
            </h2>

            <p class="mt-1 text-sm text-brand-700">
                Once your account is deleted, all of its resources and data will
                be permanently deleted. Before deleting your account, please
                download any data or information that you wish to retain.
            </p>
        </header>

        <button type="button" class="store-button bg-red-700" @click="confirmUserDeletion">Delete account</button>

        <Modal :show="confirmingUserDeletion" @close="closeModal">
            <div class="p-6">
                <h2
                    class="font-display text-2xl font-semibold"
                >
                    Are you sure you want to delete your account?
                </h2>

                <p class="mt-1 text-sm text-brand-700">
                    Once your account is deleted, all of its resources and data
                    will be permanently deleted. Please enter your password to
                    confirm you would like to permanently delete your account.
                </p>

                <div class="mt-6">
                    <label for="password" class="store-label sr-only">Password</label>

                    <StoreInput
                        id="password"
                        ref="passwordInput"
                        v-model="form.password"
                        type="password"
                        placeholder="Password"
                        @keyup.enter="deleteUser"
                    />

                    <InputError :message="form.errors.password" class="mt-2" />
                </div>

                <div class="mt-6 flex flex-wrap justify-end gap-3">
                    <button type="button" class="store-button border border-ink bg-transparent text-ink" @click="closeModal">Cancel</button>

                    <button
                        type="button"
                        class="store-button bg-red-700"
                        :disabled="form.processing"
                        @click="deleteUser"
                    >
                        Delete account
                    </button>
                </div>
            </div>
        </Modal>
    </section>
</template>
