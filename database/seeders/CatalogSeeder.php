<?php

namespace Database\Seeders;

use App\Actions\StoreProductImage;
use App\Models\Category;
use App\Models\Product;
use Illuminate\Database\Seeder;
use Illuminate\Http\UploadedFile;

class CatalogSeeder extends Seeder
{
    /**
     * A small demo catalog, with stock photos from database/seeders/images, for checking the storefront locally.
     */
    public function run(): void
    {
        $apparel = Category::factory()->create(['name' => 'Apparel', 'slug' => 'apparel', 'sort_order' => 1]);
        $tees = Category::factory()->create(['name' => 'T-Shirts', 'slug' => 't-shirts', 'parent_id' => $apparel->id]);
        $goods = Category::factory()->create(['name' => 'Goods', 'slug' => 'goods', 'sort_order' => 2]);

        foreach (['Wolf Badge Tee', 'Heritage Logo Tee', 'Puff Print Tee'] as $index => $name) {
            $tee = Product::factory()->for($tees)->create(['name' => $name, 'slug' => str($name)->slug(), 'base_price' => 99900]);

            foreach (['Black', 'Cream'] as $colour) {
                $this->attachImage($tee, $tee->slug.'-'.strtolower($colour), $colour);

                foreach (['S', 'M', 'L', 'XL'] as $size) {
                    $tee->variants()->create([
                        'sku' => strtoupper("{$tee->slug}-{$colour}-{$size}"),
                        'size' => $size,
                        'colour' => $colour,
                        'price_override' => $size === 'XL' ? 109900 : null,
                        'stock' => $index === 2 && $colour === 'Cream' ? 0 : 5,
                    ]);
                }
            }
        }

        $mug = Product::factory()->for($goods)->create(['name' => 'Enamel Mug', 'slug' => 'enamel-mug', 'base_price' => 49900]);
        $mug->variants()->create(['sku' => 'ENAMEL-MUG', 'stock' => 12]);
        $this->attachImage($mug, 'enamel-mug');
    }

    /**
     * Run a bundled photo through the real upload pipeline; skip quietly if the file is missing.
     */
    private function attachImage(Product $product, string $name, ?string $colour = null): void
    {
        $path = database_path("seeders/images/{$name}.jpg");

        if (! is_file($path)) {
            return;
        }

        app(StoreProductImage::class)
            ->handle($product, new UploadedFile($path, basename($path), test: true))
            ->update(['colour' => $colour]);
    }
}
