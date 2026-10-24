<?php

use App\Models\Product;
use App\Models\ProductImage;
use App\Models\ProductVariant;
use App\Models\User;

beforeEach(function () {
    $this->admin = User::factory()->admin()->create();
    $this->product = Product::factory()->create();
});

function variantRow(array $overrides = []): array
{
    return ['size' => 'M', 'colour' => 'Black', 'sku' => 'TEE-BLACK-M', 'price_override' => null, 'stock' => 5, 'is_active' => true, ...$overrides];
}

test('saving the grid updates existing variants and creates new ones', function () {
    $existing = ProductVariant::factory()->for($this->product)->create(['size' => 'S', 'colour' => 'Black', 'sku' => 'TEE-BLACK-S']);

    $this->actingAs($this->admin)
        ->put("/admin/products/{$this->product->id}/variants", ['variants' => [
            variantRow(['id' => $existing->id, 'size' => 'S', 'sku' => 'TEE-BLACK-S', 'stock' => 9]),
            variantRow(['price_override' => 109900]),
        ]])
        ->assertRedirect()
        ->assertSessionHasNoErrors();

    expect($existing->fresh()->stock)->toBe(9)
        ->and($this->product->variants()->count())->toBe(2)
        ->and($this->product->variants()->firstWhere('sku', 'TEE-BLACK-M')->price_override)->toBe(109900);
});

test('size and colour combinations must be unique', function () {
    $this->actingAs($this->admin)
        ->put("/admin/products/{$this->product->id}/variants", ['variants' => [
            variantRow(),
            variantRow(['sku' => 'OTHER']),
        ]])
        ->assertSessionHasErrors('variants');
});

test('the same variant id cannot appear twice', function () {
    $existing = ProductVariant::factory()->for($this->product)->create(['sku' => 'TEE-BLACK-M']);

    $this->actingAs($this->admin)
        ->put("/admin/products/{$this->product->id}/variants", ['variants' => [
            variantRow(['id' => $existing->id]),
            variantRow(['id' => $existing->id, 'size' => 'L', 'sku' => 'TEE-BLACK-L']),
        ]])
        ->assertSessionHasErrors(['variants.0.id', 'variants.1.id']);
});

test('a sku used by another product is rejected', function () {
    ProductVariant::factory()->create(['sku' => 'TEE-BLACK-M']);

    $this->actingAs($this->admin)
        ->put("/admin/products/{$this->product->id}/variants", ['variants' => [variantRow()]])
        ->assertSessionHasErrors('variants.0.sku');
});

test('a variant of another product cannot be edited through this product', function () {
    $foreign = ProductVariant::factory()->create();

    $this->actingAs($this->admin)
        ->put("/admin/products/{$this->product->id}/variants", ['variants' => [variantRow(['id' => $foreign->id])]])
        ->assertSessionHasErrors('variants.0.id');
});

test('stock cannot be negative', function () {
    $this->actingAs($this->admin)
        ->put("/admin/products/{$this->product->id}/variants", ['variants' => [variantRow(['stock' => -1])]])
        ->assertSessionHasErrors('variants.0.stock');
});

test('the editor receives the variants', function () {
    ProductVariant::factory()->for($this->product)->create();

    $this->actingAs($this->admin)
        ->get("/admin/products/{$this->product->id}/edit")
        ->assertInertia(fn ($page) => $page->has('variants', 1));
});

test('non-admins cannot save variants', function () {
    $this->actingAs(User::factory()->create())
        ->put("/admin/products/{$this->product->id}/variants", ['variants' => [variantRow()]])
        ->assertForbidden();
});

test('saving is refused when stock changed since the page loaded', function () {
    $variant = ProductVariant::factory()->for($this->product)->create(['size' => 'M', 'colour' => 'Black', 'sku' => 'TEE-BLACK-M', 'stock' => 4]);

    $this->actingAs($this->admin)
        ->put("/admin/products/{$this->product->id}/variants", ['variants' => [
            variantRow(['id' => $variant->id, 'stock' => 10, 'stock_was' => 5]),
        ]])
        ->assertSessionHasErrors('variants.0.stock');

    expect($variant->fresh()->stock)->toBe(4);
});

test('saving works when stock is unchanged since the page loaded', function () {
    $variant = ProductVariant::factory()->for($this->product)->create(['size' => 'M', 'colour' => 'Black', 'sku' => 'TEE-BLACK-M', 'stock' => 4]);

    $this->actingAs($this->admin)
        ->put("/admin/products/{$this->product->id}/variants", ['variants' => [
            variantRow(['id' => $variant->id, 'stock' => 10, 'stock_was' => 4]),
        ]])
        ->assertSessionHasNoErrors();

    expect($variant->fresh()->stock)->toBe(10);
});

test('renaming a colour on every variant renames it on the images', function () {
    $small = ProductVariant::factory()->for($this->product)->create(['size' => 'S', 'colour' => 'Navy', 'sku' => 'TEE-NAVY-S']);
    $medium = ProductVariant::factory()->for($this->product)->create(['size' => 'M', 'colour' => 'Navy', 'sku' => 'TEE-NAVY-M']);
    $image = ProductImage::factory()->for($this->product)->create(['colour' => 'Navy']);

    $this->actingAs($this->admin)
        ->put("/admin/products/{$this->product->id}/variants", ['variants' => [
            variantRow(['id' => $small->id, 'size' => 'S', 'colour' => 'Blue', 'sku' => 'TEE-NAVY-S']),
            variantRow(['id' => $medium->id, 'size' => 'M', 'colour' => 'Blue', 'sku' => 'TEE-NAVY-M']),
        ]])
        ->assertSessionHasNoErrors();

    expect($image->fresh()->colour)->toBe('Blue');
});

test('images keep a colour that some variant still has', function () {
    $small = ProductVariant::factory()->for($this->product)->create(['size' => 'S', 'colour' => 'Navy', 'sku' => 'TEE-NAVY-S']);
    $medium = ProductVariant::factory()->for($this->product)->create(['size' => 'M', 'colour' => 'Navy', 'sku' => 'TEE-NAVY-M']);
    $image = ProductImage::factory()->for($this->product)->create(['colour' => 'Navy']);

    $this->actingAs($this->admin)
        ->put("/admin/products/{$this->product->id}/variants", ['variants' => [
            variantRow(['id' => $small->id, 'size' => 'S', 'colour' => 'Blue', 'sku' => 'TEE-NAVY-S']),
            variantRow(['id' => $medium->id, 'size' => 'M', 'colour' => 'Navy', 'sku' => 'TEE-NAVY-M']),
        ]])
        ->assertSessionHasNoErrors();

    expect($image->fresh()->colour)->toBe('Navy');
});
