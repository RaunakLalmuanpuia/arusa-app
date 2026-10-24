<?php

namespace App\Enums;

enum PageBlockType: string
{
    case Hero = 'hero';
    case Text = 'text';
    case ImageText = 'image_text';
    case Features = 'features';
    case Table = 'table';
    case Faq = 'faq';
    case ContactCards = 'contact_cards';
    case CallToAction = 'call_to_action';

    /**
     * Icon names a feature item may use; each maps to a component in resources/js/Components/Icons/.
     *
     * @var list<string>
     */
    public const ICONS = ['cotton', 'feather', 'puff-print', 'shield', 'chat', 'bag'];

    /**
     * @var list<string>
     */
    public const CONTACT_KINDS = ['phone', 'whatsapp', 'email', 'address', 'hours'];
}
