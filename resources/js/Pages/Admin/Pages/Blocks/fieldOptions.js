export const fieldClass = 'admin-field';

export const smallButton = 'admin-btn-secondary min-h-8 px-3';

export const removeButton = 'admin-btn-danger min-h-8 px-2';

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
