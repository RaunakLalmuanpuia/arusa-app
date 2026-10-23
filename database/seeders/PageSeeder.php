<?php

namespace Database\Seeders;

use App\Models\Page;
use Illuminate\Database\Seeder;

class PageSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $pages = [
            'about' => ['About Arusa', 'Arusa is an apparel and lifestyle store from Aizawl, Mizoram.

Edit this page in the admin.'],
            'contact' => ['Contact', 'Reach us by phone or WhatsApp. Edit this page in the admin.'],
            'size-guide' => ['Size Guide', '| Size | Chest (in) |
|---|---|
| S | 36 |
| M | 38 |
| L | 40 |
| XL | 42 |

Edit this page in the admin.'],
            'shipping-returns' => ['Shipping & Returns', 'We deliver across Mizoram.

Edit this page in the admin.'],
        ];

        foreach ($pages as $slug => [$title, $body]) {
            Page::firstOrCreate(['slug' => $slug], ['title' => $title, 'body' => $body]);
        }
    }
}
