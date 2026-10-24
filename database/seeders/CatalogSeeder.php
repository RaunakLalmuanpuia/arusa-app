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
     * Product page copy, keyed by product slug.
     *
     * @var array<string, string>
     */
    private const DESCRIPTIONS = [
        'wolf-badge-tee' => 'A heavyweight cotton tee with our wolf badge printed on the chest. Relaxed fit with a ribbed collar that keeps its shape.',
        'heritage-logo-tee' => 'Soft combed-cotton tee with the Arusa heritage logo across the chest. A relaxed fit that holds up wash after wash.',
        'puff-print-tee' => 'Our logo in raised puff print on a heavyweight cotton tee. The print has a soft, textured feel you can run your fingers over.',
        'essential-pullover-hoodie' => 'A brushed-fleece pullover with a lined hood, kangaroo pocket and ribbed cuffs. Warm enough for Aizawl evenings.',
        'street-hoodie' => 'A boxy hoodie with dropped shoulders in heavyweight fleece, cut for an easy, roomy fit.',
        'relaxed-hoodie' => 'A soft fleece hoodie with a relaxed body and a slightly cropped length. Lined hood and front pocket.',
        'crewneck-sweatshirt' => 'A classic crewneck in brushed-back fleece, with a ribbed collar, cuffs and hem.',
        'mock-neck-long-sleeve-tee' => 'A fitted long-sleeve tee with a low mock neck, in a smooth cotton blend that layers easily under a jacket.',
        'oversized-tee' => 'A roomy tee with dropped shoulders in heavyweight cotton. Take your usual size for the oversized look, or one down for a closer fit.',
        'everyday-tee' => 'A lightweight cotton tee with a regular fit. The one you reach for every day.',
        'ribbed-crop-top' => 'A stretchy rib-knit crop top with a close fit and a clean scoop neck.',
        'polo-crop-top' => 'A cropped knit polo with a two-button placket and a soft collar.',
        'knit-beanie' => 'A chunky rib-knit beanie with a fold-over cuff. One size that stretches to fit.',
        'canvas-tote-bag' => 'A sturdy cotton canvas tote with long shoulder straps, roomy enough for a laptop or the day’s shopping.',
        'insulated-bottle' => 'A double-walled steel bottle that keeps drinks cold or hot for hours. Leak-proof screw cap.',
        'spiral-notebook' => 'A spiral-bound notebook with ruled pages and a stiff card cover that stands up to a backpack.',
        'soy-candle' => 'A hand-poured soy wax candle with a cotton wick, in a glass jar you can reuse once it burns down.',
        'brass-keyring' => 'A solid brass keyring stamped with the Arusa mark. It darkens to a warm patina with use.',
        'ceramic-cup' => 'A stoneware cup with a matte glaze, sized for a coffee or a cup of tea.',
        'enamel-mug' => 'A lightweight enamel mug with a rolled rim, as at home on a camping trip as on your desk.',
    ];

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
            $tee = Product::factory()->for($tees)->create(['name' => $name, 'slug' => str($name)->slug(), 'description' => self::DESCRIPTIONS[(string) str($name)->slug()], 'gender' => Gender::Unisex, 'base_price' => 99900]);

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

        $mug = Product::factory()->for($goods)->create(['name' => 'Enamel Mug', 'slug' => 'enamel-mug', 'description' => self::DESCRIPTIONS['enamel-mug'], 'base_price' => 49900]);
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

            $product = Product::factory()->for($category)->create(['name' => $name, 'slug' => str($name)->slug(), 'description' => self::DESCRIPTIONS[(string) str($name)->slug()], 'gender' => $gender, 'base_price' => $price]);

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
