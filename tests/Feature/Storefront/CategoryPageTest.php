<?php

use App\Models\Category;
use App\Models\Product;
use App\Models\ProductVariant;

beforeEach(function () {
    $this->apparel = Category::factory()->create(['slug' => 'apparel']);
    $this->tees = Category::factory()->create(['parent_id' => $this->apparel->id]);
});

function productWith(Category $category, array ...$variants): Product
{
    $product = Product::factory()->for($category)->create(['base_price' => 100000]);

    foreach ($variants as $variant) {
        ProductVariant::factory()->for($product)->create($variant);
    }

    return $product;
}

test('a parent category lists its own and its subcategories visible products', function () {
    productWith($this->apparel, ['size' => 'M']);
    productWith($this->tees, ['size' => 'L']);
    Product::factory()->inactive()->for($this->tees)->has(ProductVariant::factory(), 'variants')->create();

    $this->get('/categories/apparel')
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Catalog/Category')
            ->has('products', 2)
            ->has('subcategories', 1)
            ->where('sizes', ['M', 'L']));
});

test('size and colour filters match one in-stock variant', function () {
    $match = productWith($this->apparel, ['size' => 'M', 'colour' => 'Black']);
    productWith($this->apparel, ['size' => 'M', 'colour' => 'Cream'], ['size' => 'L', 'colour' => 'Black']);
    productWith($this->apparel, ['size' => 'M', 'colour' => 'Black', 'stock' => 0]);

    $this->get('/categories/apparel?size=M&colour=Black')
        ->assertInertia(fn ($page) => $page->has('products', 1)->where('products.0.slug', $match->slug));
});

test('the price filter uses the effective variant price in rupees', function () {
    $cheap = productWith($this->apparel, ['price_override' => 50000]);
    productWith($this->apparel, []);

    $this->get('/categories/apparel?max=600')
        ->assertInertia(fn ($page) => $page->has('products', 1)->where('products.0.slug', $cheap->slug));
});

test('inactive categories are not found', function () {
    $hidden = Category::factory()->inactive()->create();

    $this->get("/categories/{$hidden->slug}")->assertNotFound();
});

test('active top-level categories are shared for the navigation', function () {
    Category::factory()->inactive()->create();

    $this->get('/')->assertInertia(fn ($page) => $page
        ->has('categories', 1)
        ->where('categories.0.slug', 'apparel'));
});
