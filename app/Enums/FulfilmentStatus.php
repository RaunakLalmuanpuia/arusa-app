<?php

namespace App\Enums;

use Illuminate\Support\Str;

enum FulfilmentStatus: string
{
    case Pending = 'pending';
    case Confirmed = 'confirmed';
    case Packed = 'packed';
    case Dispatched = 'dispatched';
    case Delivered = 'delivered';
    case Cancelled = 'cancelled';

    public function label(): string
    {
        return Str::headline($this->name);
    }
}
