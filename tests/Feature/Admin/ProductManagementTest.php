<?php

use App\Models\Category;
use App\Models\Product;
use App\Models\ProductImage;
use App\Models\User;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    $this->admin = User::factory()->admin()->create();
});

function productPayload(array $overrides = []): array
{
    return [
        'name' => 'Wolf Badge Tee',
        'slug' => 'wolf-badge-tee',
        'category_id' => Category::factory()->create()->id,
        'description' => 'Heavyweight cotton.',
        'base_price' => 99900,
        'is_active' => true,
        ...$overrides,
    ];
}

test('admin can filter the product list by category and search', function () {
    $tees = Category::factory()->create();
    Product::factory()->for($tees)->create(['name' => 'Wolf Tee']);
    Product::factory()->for($tees)->create(['name' => 'Logo Tee']);
    Product::factory()->create(['name' => 'Wolf Mug']);

    $this->actingAs($this->admin)
        ->get("/admin/products?category_id={$tees->id}&search=wolf")
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Admin/Products/Index')
            ->has('products.data', 1)
            ->where('products.data.0.name', 'Wolf Tee'));
});

test('creating a product redirects to its editor', function () {
    $response = $this->actingAs($this->admin)->post('/admin/products', productPayload());

    $product = Product::firstWhere('slug', 'wolf-badge-tee');
    $response->assertRedirect("/admin/products/{$product->id}/edit");
    expect($product->base_price)->toBe(99900);
});

test('an empty description is stored as an empty string', function () {
    $this->actingAs($this->admin)->post('/admin/products', productPayload(['description' => '']));

    expect(Product::firstWhere('slug', 'wolf-badge-tee')->description)->toBe('');
});

test('base price must be whole paise of at least one rupee', function (mixed $price) {
    $this->actingAs($this->admin)
        ->post('/admin/products', productPayload(['base_price' => $price]))
        ->assertSessionHasErrors('base_price');
})->with([99.5, 50, -100, 'abc']);

test('admin can update a product keeping its own slug', function () {
    $product = Product::factory()->create(['slug' => 'wolf-badge-tee']);

    $this->actingAs($this->admin)
        ->put("/admin/products/{$product->id}", productPayload(['name' => 'Renamed']))
        ->assertRedirect("/admin/products/{$product->id}/edit");

    expect($product->fresh()->name)->toBe('Renamed');
});

test('deleting a product removes its image files', function () {
    Storage::fake('public');
    $image = ProductImage::factory()->create();
    Storage::disk('public')->put("{$image->path}-400.webp", 'x');

    $this->actingAs($this->admin)
        ->delete("/admin/products/{$image->product_id}")
        ->assertRedirect('/admin/products');

    Storage::disk('public')->assertMissing("{$image->path}-400.webp");
    expect(Product::count())->toBe(0);
});

test('non-admins cannot manage products', function () {
    $this->actingAs(User::factory()->create())->post('/admin/products', productPayload())->assertForbidden();
});
