import { reactive } from 'vue';
import { router } from '@inertiajs/vue3';

// Error keys controllers use for whole-page messages; none of them is a form field name.
const toastErrorKeys = ['status', 'order', 'payment', 'category', 'product', 'zone', 'search', 'from', 'to', 'payment_method', 'shipping_zone_id'];

const timers = new Map();
let nextId = 0;
let hasReadInitialPage = false;

export const toasts = reactive([]);

export const dismissToast = (id) => {
    clearTimeout(timers.get(id));
    timers.delete(id);
    const index = toasts.findIndex((toast) => toast.id === id);

    if (index !== -1) {
        toasts.splice(index, 1);
    }
};

export const pauseToast = (id) => clearTimeout(timers.get(id));

export const resumeToast = (id) => {
    clearTimeout(timers.get(id));
    timers.set(id, setTimeout(() => dismissToast(id), 5000));
};

const push = (message, tone) => {
    if (toasts.some((toast) => toast.message === message)) {
        return;
    }

    const id = nextId++;
    toasts.push({ id, message, tone });
    resumeToast(id);
};

const pushErrors = (errors) => {
    Object.entries(errors ?? {})
        .filter(([key]) => toastErrorKeys.includes(key))
        .forEach(([, message]) => push(message, 'error'));
};

const isAdminPath = () => window.location.pathname.startsWith('/admin');

// AdminLayout remounts on every visit, so toasts live here and listen to the router once.
router.on('success', (event) => {
    const status = event.detail.page.props.flash?.status;

    if (isAdminPath() && status) {
        push(status, 'success');
    }
});

router.on('error', (event) => {
    if (isAdminPath()) {
        pushErrors(event.detail.errors);
    }
});

/**
 * Show the flash and errors of a full page load, which fires no router event.
 */
export const readInitialPage = (props) => {
    if (hasReadInitialPage) {
        return;
    }

    hasReadInitialPage = true;

    if (props.flash?.status) {
        push(props.flash.status, 'success');
    }

    pushErrors(props.errors);
};
