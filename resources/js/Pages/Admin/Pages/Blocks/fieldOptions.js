export const fieldClass = 'block w-full rounded-md border-gray-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500';

export const smallButton = 'rounded border border-gray-300 px-3 py-1.5 text-sm disabled:opacity-40';

export const removeButton = 'text-sm text-red-600 hover:underline disabled:opacity-40 disabled:no-underline';

// Must match PageBlockType::ICONS.
export const featureIcons = [
    { value: 'cotton', label: 'Cotton' },
    { value: 'feather', label: 'Feather' },
    { value: 'puff-print', label: 'Puff print' },
    { value: 'shield', label: 'Shield' },
    { value: 'chat', label: 'Chat' },
    { value: 'bag', label: 'Bag' },
];

// Must match PageBlockType::CONTACT_KINDS.
export const contactKinds = [
    { value: 'phone', label: 'Phone', hint: '10-digit mobile number' },
    { value: 'whatsapp', label: 'WhatsApp', hint: '10-digit mobile number' },
    { value: 'email', label: 'Email', hint: 'Email address' },
    { value: 'address', label: 'Address', hint: 'Address' },
    { value: 'hours', label: 'Opening hours', hint: 'e.g. Monday to Saturday, 10am – 6pm' },
];
