<?php

namespace App\Models;

use App\Enums\FulfilmentStatus;
use App\Enums\PaymentStatus;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One timeline entry: the order's statuses right after a change, and who made it (null for the webhook, the sweep or a guest).
 */
#[Fillable(['fulfilment_status', 'payment_status', 'user_id'])]
class OrderEvent extends Model
{
    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'fulfilment_status' => FulfilmentStatus::class,
            'payment_status' => PaymentStatus::class,
        ];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
