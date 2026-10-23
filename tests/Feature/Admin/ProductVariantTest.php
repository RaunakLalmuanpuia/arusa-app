<?php

use App\Models\Product;
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
