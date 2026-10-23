<?php

use App\Models\Category;
use App\Models\Product;
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
    ]);
});

test('stock can never go negative', function () {
    $variant = ProductVariant::factory()->create(['stock' => 0]);

    $variant->decrement('stock');
})->throws(QueryException::class);
