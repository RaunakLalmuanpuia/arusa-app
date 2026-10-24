<?php

use App\Enums\Gender;
use App\Models\Category;
use App\Models\Product;
use App\Models\ProductImage;
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

test('a subcategory of an inactive parent returns 404', function () {
    $this->apparel->update(['is_active' => false]);

    $this->get("/categories/{$this->tees->slug}")->assertNotFound();
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

test('the gender filter includes unisex products and ignores stock', function () {
    $mens = productWith($this->apparel, ['stock' => 0]);
    $mens->update(['gender' => Gender::Men]);
    $unisex = productWith($this->apparel, []);
    $unisex->update(['gender' => Gender::Unisex]);
    productWith($this->apparel, [])->update(['gender' => Gender::Women]);
    productWith($this->apparel, []);

    $this->get('/categories/apparel?gender=men')
        ->assertInertia(fn ($page) => $page
            ->has('products', 2)
            ->has('genders', 2)
            ->where('products', fn ($products) => collect($products)->pluck('slug')->sort()->values()->all() === collect([$mens->slug, $unisex->slug])->sort()->values()->all()));
});

test('the gender filter is hidden when no product has a gender', function () {
    productWith($this->apparel, []);

    $this->get('/categories/apparel')->assertInertia(fn ($page) => $page->where('genders', []));
});

test('a picked gender is remembered for later visits until all is picked', function () {
    productWith($this->apparel, [])->update(['gender' => Gender::Men]);
    productWith($this->apparel, [])->update(['gender' => Gender::Women]);

    $this->get('/categories/apparel?gender=men')->assertCookie('gender', 'men');

    $this->withCookie('gender', 'men')->get('/categories/apparel')
        ->assertInertia(fn ($page) => $page->where('filters.gender', 'men')->has('products', 1));

    $this->withCookie('gender', 'men')->get('/categories/apparel?gender=')
        ->assertCookieExpired('gender')
        ->assertInertia(fn ($page) => $page->where('filters.gender', null)->has('products', 2));
});

test('a colour filter shows that colour photo on the card', function () {
    $beanie = productWith($this->apparel, ['colour' => 'White'], ['colour' => 'Grey']);
    ProductImage::factory()->for($beanie)->create(['colour' => 'White', 'alt' => 'white photo', 'sort_order' => 1]);
    ProductImage::factory()->for($beanie)->create(['colour' => 'Grey', 'alt' => 'grey photo', 'sort_order' => 2]);

    $this->get('/categories/apparel?colour=Grey')
        ->assertInertia(fn ($page) => $page->where('products.0.image.alt', 'grey photo'));

    $this->get('/categories/apparel')
        ->assertInertia(fn ($page) => $page->where('products.0.image.alt', 'white photo'));
});
