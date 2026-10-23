<?php

namespace Database\Seeders;

use App\Models\ShippingZone;
use Illuminate\Database\Seeder;

class ShippingZoneSeeder extends Seeder
{
    /**
     * Demo zones for local checks. The pincode lists are placeholders; the owner enters the real ones in admin.
     */
    public function run(): void
    {
        ShippingZone::factory()
            ->withPincodes(...array_map(fn (int $n): string => (string) (796000 + $n), range(1, 15)))
            ->create(['name' => 'Aizawl', 'rate' => 4000, 'free_above' => 149900, 'cod_allowed' => true, 'cod_max' => 500000]);

        ShippingZone::factory()
            ->withPincodes('796321', '796701', '796181')
            ->create(['name' => 'District towns', 'rate' => 10000, 'free_above' => null, 'cod_allowed' => false]);
    }
}
