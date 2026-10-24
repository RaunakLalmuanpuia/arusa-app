<?php

namespace App\Models;

use App\Enums\FulfilmentStatus;
use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use Database\Factories\OrderFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\URL;

#[Fillable([
    'user_id', 'customer_name', 'customer_phone', 'customer_email',
    'ship_line1', 'ship_locality', 'ship_district', 'ship_pincode', 'shipping_zone_id',
    'subtotal', 'shipping', 'total', 'payment_method', 'payment_status', 'fulfilment_status',
    'tracking_note', 'placed_at',
])]
class Order extends Model
{
    /** @use HasFactory<OrderFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'subtotal' => 'integer',
            'shipping' => 'integer',
            'total' => 'integer',
            'payment_method' => PaymentMethod::class,
            'payment_status' => PaymentStatus::class,
            'fulfilment_status' => FulfilmentStatus::class,
            'placed_at' => 'datetime',
        ];
    }

    /**
     * Every status change lands on the timeline, whichever action made it.
     */
    protected static function booted(): void
    {
        static::created(fn (Order $order) => $order->recordEvent());
        static::updated(function (Order $order): void {
            if ($order->wasChanged(['fulfilment_status', 'payment_status'])) {
                $order->recordEvent();
            }
        });
    }

    private function recordEvent(): void
    {
        $this->events()->create([
            'fulfilment_status' => $this->fulfilment_status,
            'payment_status' => $this->payment_status,
            'user_id' => auth()->id(),
        ]);
    }

    /**
     * Orders holding a captured payment that should go back: a cancelled order still paid, or a second capture on one order.
     *
     * @param  Builder<Order>  $query
     */
    #[Scope]
    protected function refundOwed(Builder $query): void
    {
        $paid = fn (Builder $payment) => $payment->where('status', PaymentStatus::Paid);

        $query->where(fn (Builder $query) => $query
            ->where(fn (Builder $query) => $query->where('fulfilment_status', FulfilmentStatus::Cancelled)->whereHas('payments', $paid))
            ->orWhereHas('payments', $paid, '>', 1));
    }

    /**
     * Customer-facing order number, derived from the id: ARU-000123.
     *
     * @return Attribute<string, never>
     */
    protected function number(): Attribute
    {
        return Attribute::get(fn (): string => sprintf('ARU-%06d', $this->id));
    }

    /**
     * @return HasMany<OrderItem, $this>
     */
    public function items(): HasMany
    {
        return $this->hasMany(OrderItem::class)->orderBy('id');
    }

    /**
     * Payment attempts, oldest first. The first row is the one created when the order was placed.
     *
     * @return HasMany<Payment, $this>
     */
    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class)->orderBy('id');
    }

    /**
     * Status timeline, oldest first.
     *
     * @return HasMany<OrderEvent, $this>
     */
    public function events(): HasMany
    {
        return $this->hasMany(OrderEvent::class)->orderBy('id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return BelongsTo<ShippingZone, $this>
     */
    public function shippingZone(): BelongsTo
    {
        return $this->belongsTo(ShippingZone::class);
    }

    /**
     * Permanent signed link to the order status page, safe to email to a guest.
     */
    public function trackingUrl(): string
    {
        return URL::signedRoute('orders.show', $this);
    }
}
