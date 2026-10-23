<?php

namespace App\Models;

use Database\Factories\ShippingZoneFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['name', 'rate', 'free_above', 'cod_allowed', 'cod_max', 'is_active'])]
class ShippingZone extends Model
{
    /** @use HasFactory<ShippingZoneFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'rate' => 'integer',
            'free_above' => 'integer',
            'cod_allowed' => 'boolean',
            'cod_max' => 'integer',
            'is_active' => 'boolean',
        ];
    }

    /**
     * @return HasMany<ShippingZonePincode, $this>
     */
    public function pincodes(): HasMany
    {
        return $this->hasMany(ShippingZonePincode::class)->orderBy('pincode');
    }

    /**
     * @return HasMany<Order, $this>
     */
    public function orders(): HasMany
    {
        return $this->hasMany(Order::class);
    }

    /**
     * The active zone that delivers to this pincode, if any.
     */
    public static function forPincode(string $pincode): ?self
    {
        return static::where('is_active', true)
            ->whereHas('pincodes', fn ($query) => $query->where('pincode', $pincode))
            ->first();
    }

    /**
     * Shipping in paise for an order subtotal: free at or above free_above, otherwise the zone rate.
     */
    public function shippingFor(int $subtotal): int
    {
        return $this->free_above !== null && $subtotal >= $this->free_above ? 0 : $this->rate;
    }

    /**
     * Cash on delivery needs the store-wide switch, the zone's flag, and a total within the zone's COD limit.
     */
    public function allowsCod(int $total): bool
    {
        return (bool) Setting::values()['cod_enabled']
            && $this->cod_allowed
            && ($this->cod_max === null || $total <= $this->cod_max);
    }
}
