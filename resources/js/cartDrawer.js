import { ref, watch } from 'vue';

export const isCartOpen = ref(false);

// The open drawer owns one history entry, so the browser back button closes it instead of leaving the page.
let ownsHistoryEntry = false;

watch(isCartOpen, (open) => {
    if (open && !ownsHistoryEntry) {
        history.pushState(history.state, '', location.href);
        ownsHistoryEntry = true;
    } else if (!open && ownsHistoryEntry) {
        history.back();
    }
});

// Capture runs before Inertia's listener, which would otherwise re-render the page from its older history snapshot.
window.addEventListener('popstate', (event) => {
    if (!ownsHistoryEntry) {
        return;
    }
    event.stopImmediatePropagation();
    ownsHistoryEntry = false;
    isCartOpen.value = false;
}, { capture: true });

/** Close the drawer for a visit that replaces the drawer's history entry. */
export const closeCartForVisit = () => {
    ownsHistoryEntry = false;
    isCartOpen.value = false;
};
