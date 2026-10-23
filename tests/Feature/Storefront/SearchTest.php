<?php

use App\Models\Product;
use App\Models\ProductVariant;

function searchable(array $attributes): Product
{
    return Product::factory()->has(ProductVariant::factory(), 'variants')->create($attributes);
}

test('search matches stemmed words in the name and description', function () {
    $hoodie = searchable(['name' => 'Wolf Hoodie', 'description' => 'Fleece lined.']);
    $tee = searchable(['name' => 'Logo Tee', 'description' => 'Printed with puff ink on heavy cotton.']);
    searchable(['name' => 'Enamel Mug', 'description' => 'Holds coffee.']);

    $this->get('/search?q=hoodies')
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Catalog/Search')
            ->has('products', 1)
            ->where('products.0.slug', $hoodie->slug));

    $this->get('/search?q=printed cotton')
        ->assertInertia(fn ($page) => $page->has('products', 1)->where('products.0.slug', $tee->slug));
});

test('search skips hidden products', function () {
    Product::factory()->inactive()->has(ProductVariant::factory(), 'variants')->create(['name' => 'Secret Hoodie']);

    $this->get('/search?q=hoodie')->assertInertia(fn ($page) => $page->has('products', 0));
});

test('an empty query shows no results', function () {
    searchable(['name' => 'Wolf Hoodie']);

    $this->get('/search')->assertOk()->assertInertia(fn ($page) => $page->where('query', '')->has('products', 0));
});
