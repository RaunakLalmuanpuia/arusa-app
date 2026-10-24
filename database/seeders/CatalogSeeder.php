<?php

namespace Database\Seeders;

use App\Actions\StoreProductImage;
use App\Enums\Gender;
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
        $hoodies = Category::factory()->create(['name' => 'Hoodies', 'slug' => 'hoodies', 'parent_id' => $apparel->id]);
        $sweatshirts = Category::factory()->create(['name' => 'Sweatshirts', 'slug' => 'sweatshirts', 'parent_id' => $apparel->id]);
        $headwear = Category::factory()->create(['name' => 'Headwear', 'slug' => 'headwear', 'parent_id' => $apparel->id]);
        $goods = Category::factory()->create(['name' => 'Goods', 'slug' => 'goods', 'sort_order' => 2]);

        foreach (['Wolf Badge Tee', 'Heritage Logo Tee', 'Puff Print Tee'] as $index => $name) {
            $tee = Product::factory()->for($tees)->create(['name' => $name, 'slug' => str($name)->slug(), 'gender' => Gender::Unisex, 'base_price' => 99900]);

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

        $this->seedProducts([
            ['Essential Pullover Hoodie', $hoodies, Gender::Unisex, 189900, ['Black']],
            ['Street Hoodie', $hoodies, Gender::Men, 199900, ['Black']],
            ['Relaxed Hoodie', $hoodies, Gender::Women, 189900, ['Black']],
            ['Crewneck Sweatshirt', $sweatshirts, Gender::Men, 159900, ['Grey']],
            ['Mock Neck Long-Sleeve Tee', $tees, Gender::Men, 129900, ['Grey']],
            ['Oversized Tee', $tees, Gender::Men, 89900, ['Black']],
            ['Everyday Tee', $tees, Gender::Men, 69900, ['White']],
            ['Ribbed Crop Top', $tees, Gender::Women, 79900, ['White']],
            ['Polo Crop Top', $tees, Gender::Women, 89900, ['Pink']],
            ['Knit Beanie', $headwear, Gender::Unisex, 59900, ['White', 'Grey'], [null]],
            ['Canvas Tote Bag', $goods, null, 54900, ['Black', 'Natural'], [null]],
            ['Insulated Bottle', $goods, null, 89900, [null], [null]],
            ['Spiral Notebook', $goods, null, 24900, [null], [null]],
            ['Soy Candle', $goods, null, 64900, [null], [null]],
            ['Brass Keyring', $goods, null, 34900, [null], [null]],
            ['Ceramic Cup', $goods, null, 39900, [null], [null]],
        ]);

        $mug = Product::factory()->for($goods)->create(['name' => 'Enamel Mug', 'slug' => 'enamel-mug', 'base_price' => 49900]);
        $mug->variants()->create(['sku' => 'ENAMEL-MUG', 'stock' => 12]);
        $this->attachImage($mug, 'enamel-mug');
    }

    /**
     * One photo per colour, named {slug}-{colour}.jpg (or {slug}.jpg without a colour); every size in stock.
     *
     * @param  list<array{0: string, 1: Category, 2: Gender|null, 3: int, 4: list<string|null>, 5?: list<string|null>}>  $products
     */
    private function seedProducts(array $products): void
    {
        foreach ($products as $entry) {
            [$name, $category, $gender, $price, $colours, $sizes] = $entry + [5 => ['S', 'M', 'L', 'XL']];

            $product = Product::factory()->for($category)->create(['name' => $name, 'slug' => str($name)->slug(), 'gender' => $gender, 'base_price' => $price]);

            foreach ($colours as $colour) {
                $this->attachImage($product, implode('-', array_filter([$product->slug, strtolower((string) $colour)])), $colour);

                foreach ($sizes as $size) {
                    $product->variants()->create([
                        'sku' => strtoupper(implode('-', array_filter([$product->slug, $colour, $size]))),
                        'size' => $size,
                        'colour' => $colour,
                        'stock' => 8,
                    ]);
                }
            }
        }
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
