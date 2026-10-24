<?php

namespace App\Enums;

enum Gender: string
{
    case Men = 'men';
    case Women = 'women';
    case Unisex = 'unisex';

    public function label(): string
    {
        return match ($this) {
            self::Men => 'Men',
            self::Women => 'Women',
            self::Unisex => 'Unisex',
        };
    }

    /**
     * Genders a shopper filtering by this one should see: unisex items fit everyone.
     *
     * @return list<string>
     */
    public function matching(): array
    {
        return $this === self::Unisex ? [$this->value] : [$this->value, self::Unisex->value];
    }

    /**
     * @return list<array{value: string, label: string}>
     */
    public static function options(): array
    {
        return array_map(fn (self $gender): array => ['value' => $gender->value, 'label' => $gender->label()], self::cases());
    }

    /**
     * Genders a shopper picks between: unisex items already show under both.
     *
     * @return list<array{value: string, label: string}>
     */
    public static function shopperOptions(): array
    {
        return array_values(array_filter(self::options(), fn (array $option): bool => $option['value'] !== self::Unisex->value));
    }
}
