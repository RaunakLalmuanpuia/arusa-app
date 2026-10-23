<?php

namespace App\Enums;

enum PaymentMethod: string
{
    case Cod = 'cod';
    case Razorpay = 'razorpay';

    public function label(): string
    {
        return match ($this) {
            self::Cod => 'Cash on delivery',
            self::Razorpay => 'Online payment',
        };
    }
}
