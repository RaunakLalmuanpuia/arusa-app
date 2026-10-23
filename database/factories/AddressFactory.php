<?php

namespace Database\Factories;

use App\Models\Address;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Address>
 */
class AddressFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'name' => fake()->name(),
            'phone' => fake()->numerify('98########'),
            'line1' => fake()->streetAddress(),
            'locality' => 'Zarkawt',
            'district' => 'Aizawl',
            'pincode' => '796001',
        ];
    }
}
