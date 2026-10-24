<?php

use App\Models\Category;
use App\Models\Product;
use App\Models\ProductImage;
use App\Models\ProductVariant;
use Illuminate\Database\QueryException;

test('variant price falls back to the product base price', function () {
    $product = Product::factory()->create(['base_price' => 99900]);
    $plain = ProductVariant::factory()->for($product)->create();
    $override = ProductVariant::factory()->for($product)->create(['price_override' => 129900]);

    expect($plain->effectivePrice())->toBe(99900)
        ->and($override->effectivePrice())->toBe(129900);
});

test('visible products need an active product, active category and an active variant', function () {
    $visible = Product::factory()->has(ProductVariant::factory(), 'variants')->create();
    Product::factory()->inactive()->has(ProductVariant::factory(), 'variants')->create();
    Product::factory()->for(Category::factory()->inactive())->has(ProductVariant::factory(), 'variants')->create();
    Product::factory()->has(ProductVariant::factory()->inactive(), 'variants')->create();
    Product::factory()->create();

    expect(Product::visible()->pluck('id')->all())->toBe([$visible->id]);
});

test('a product under an inactive parent category is not visible', function () {
    $child = Category::factory()->for(Category::factory()->inactive(), 'parent')->create();
    Product::factory()->for($child)->has(ProductVariant::factory(), 'variants')->create();

    expect(Product::visible()->count())->toBe(0);
});

test('card data reports the price range, stock and first image', function () {
    $product = Product::factory()->create(['base_price' => 50000]);
    ProductVariant::factory()->for($product)->outOfStock()->create();
    ProductVariant::factory()->for($product)->create(['price_override' => 60000]);
    ProductVariant::factory()->for($product)->inactive()->create(['price_override' => 100]);

    $card = Product::withCardData()->find($product->id)->cardData();

    expect($card)->toMatchArray([
        'slug' => $product->slug,
        'price_from' => 50000,
        'has_price_range' => true,
        'in_stock' => true,
        'image' => null,
        'hover_image' => null,
    ]);
});

test('card data includes the second image of each product for the hover swap', function () {
    [$first, $second] = Product::factory()->count(2)->has(ProductVariant::factory(), 'variants')->create();
    foreach ([$first, $second] as $product) {
        foreach ([1, 2, 3] as $order) {
            ProductImage::factory()->for($product)->create(['alt' => "{$product->slug} {$order}", 'sort_order' => $order]);
        }
    }
    $lonely = Product::factory()->has(ProductVariant::factory(), 'variants')->create();
    ProductImage::factory()->for($lonely)->create();

    $cards = Product::withCardData()->orderBy('id')->get()->map->cardData();

    expect($cards[0]['image']['alt'])->toBe("{$first->slug} 1")
        ->and($cards[0]['hover_image']['alt'])->toBe("{$first->slug} 2")
        ->and($cards[1]['hover_image']['alt'])->toBe("{$second->slug} 2")
        ->and($cards[2]['hover_image'])->toBeNull();
});

test('stock can never go negative', function () {
    $variant = ProductVariant::factory()->create(['stock' => 0]);

    $variant->decrement('stock');
})->throws(QueryException::class);
