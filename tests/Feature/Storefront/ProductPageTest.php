<?php

use App\Models\Category;
use App\Models\Product;
use App\Models\ProductImage;
use App\Models\ProductVariant;

test('a visible product shows its active variants with effective prices', function () {
    $product = Product::factory()->create(['base_price' => 99900]);
    ProductVariant::factory()->for($product)->create(['size' => 'M', 'colour' => 'Black']);
    ProductVariant::factory()->for($product)->outOfStock()->create(['size' => 'XL', 'colour' => 'Black', 'price_override' => 109900]);
    ProductVariant::factory()->for($product)->inactive()->create(['size' => 'S', 'colour' => 'Black']);
    ProductImage::factory()->for($product)->create(['colour' => 'Black', 'sort_order' => 1]);
    ProductImage::factory()->for($product)->create(['sort_order' => 0]);

    $this->get("/products/{$product->slug}")
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Catalog/Product')
            ->where('product.name', $product->name)
            ->has('variants', 2)
            ->where('variants.0.price', 99900)
            ->where('variants.0.in_stock', true)
            ->where('variants.1.price', 109900)
            ->where('variants.1.in_stock', false)
            ->missing('variants.0.stock')
            ->where('images.0.colour', null)
            ->where('images.1.colour', 'Black'));
});

test('hidden products are not found', function (Closure $makeProduct) {
    $product = $makeProduct();

    $this->get("/products/{$product->slug}")->assertNotFound();
})->with([
    'inactive product' => fn () => Product::factory()->inactive()->has(ProductVariant::factory(), 'variants')->create(),
    'inactive category' => fn () => Product::factory()->for(Category::factory()->inactive())->has(ProductVariant::factory(), 'variants')->create(),
    'no active variants' => fn () => Product::factory()->create(),
]);
