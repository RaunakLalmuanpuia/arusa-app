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

    /**
     * The one status an admin may move an order to next; null at the end of the line.
     * Cancelling is separate (CancelOrder).
     */
    public function next(): ?self
    {
        return match ($this) {
            self::Pending => self::Confirmed,
            self::Confirmed => self::Packed,
            self::Packed => self::Dispatched,
            self::Dispatched => self::Delivered,
            self::Delivered, self::Cancelled => null,
        };
    }

    public function isCancellable(): bool
    {
        return in_array($this, [self::Pending, self::Confirmed, self::Packed], true);
    }
}
