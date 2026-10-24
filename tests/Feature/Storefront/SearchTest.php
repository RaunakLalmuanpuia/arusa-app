<?php

use App\Enums\Gender;
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

test('search matches the start of a word', function () {
    $hoodie = searchable(['name' => 'Wolf Hoodie', 'description' => 'Fleece lined.']);
    searchable(['name' => 'Logo Tee', 'description' => 'Printed with puff ink on heavy cotton.']);

    $this->get('/search?q=hood')
        ->assertInertia(fn ($page) => $page->has('products', 1)->where('products.0.slug', $hoodie->slug));

    $this->get('/search?q=wol flee')
        ->assertInertia(fn ($page) => $page->has('products', 1)->where('products.0.slug', $hoodie->slug));
});

test('search treats punctuation as plain text', function () {
    $tee = searchable(['name' => 'Logo T-Shirt']);

    $this->get("/search?q=t-shirt's ! & | :* (")
        ->assertOk()
        ->assertInertia(fn ($page) => $page->has('products', 1)->where('products.0.slug', $tee->slug));

    $this->get('/search?q=!!!')->assertOk()->assertInertia(fn ($page) => $page->has('products', 0));
});

test('search skips hidden products', function () {
    Product::factory()->inactive()->has(ProductVariant::factory(), 'variants')->create(['name' => 'Secret Hoodie']);

    $this->get('/search?q=hoodie')->assertInertia(fn ($page) => $page->has('products', 0));
});

test('an empty query shows no results', function () {
    searchable(['name' => 'Wolf Hoodie']);

    $this->get('/search')->assertOk()->assertInertia(fn ($page) => $page->where('query', '')->has('products', 0));
});

test('search narrows by gender, keeping unisex and ungendered products', function () {
    searchable(['name' => 'Mens Hoodie', 'gender' => Gender::Men]);
    $womens = searchable(['name' => 'Womens Hoodie', 'gender' => Gender::Women]);
    $unisex = searchable(['name' => 'Unisex Hoodie', 'gender' => Gender::Unisex]);
    $ungendered = searchable(['name' => 'Hoodie Patch', 'gender' => null]);

    $this->get('/search?q=hoodie&gender=women')
        ->assertInertia(fn ($page) => $page
            ->where('gender', 'women')
            ->has('genders', 2)
            ->has('products', 3)
            ->where('products', fn ($products) => collect($products)->pluck('slug')->sort()->values()->all() === collect([$womens->slug, $unisex->slug, $ungendered->slug])->sort()->values()->all()));
});

test('search falls back to the remembered gender', function () {
    searchable(['name' => 'Mens Hoodie', 'gender' => Gender::Men]);
    searchable(['name' => 'Womens Hoodie', 'gender' => Gender::Women]);

    $this->withCookie('gender', 'women')->get('/search?q=hoodie')
        ->assertInertia(fn ($page) => $page->where('gender', 'women')->has('products', 1));
});
