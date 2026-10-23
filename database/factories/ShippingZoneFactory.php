<?php

namespace Database\Factories;

use App\Models\ShippingZone;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ShippingZone>
 */
class ShippingZoneFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->unique()->city(),
            'rate' => 5000,
            'free_above' => null,
            'cod_allowed' => true,
            'cod_max' => null,
            'is_active' => true,
        ];
    }

    public function withPincodes(string ...$pincodes): static
    {
        return $this->afterCreating(function (ShippingZone $zone) use ($pincodes): void {
            $zone->pincodes()->createMany(array_map(fn (string $pincode): array => ['pincode' => $pincode], $pincodes));
        });
    }
}
