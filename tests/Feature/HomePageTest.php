<?php

use App\Models\Product;
use App\Models\ProductVariant;

test('home page renders the storefront home with the newest products', function () {
    $product = Product::factory()->has(ProductVariant::factory(), 'variants')->create();
    Product::factory()->inactive()->has(ProductVariant::factory(), 'variants')->create();

    $this->get('/')
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Home')
            ->where('store.store_name', 'Arusa')
            ->has('products', 1)
            ->where('products.0.slug', $product->slug));
});
