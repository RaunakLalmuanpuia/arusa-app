<?php

use App\Models\Product;
use App\Models\ProductImage;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    Storage::fake('public');
    $this->admin = User::factory()->admin()->create();
    $this->product = Product::factory()->create();
});

function storedWidth(string $file): int
{
    return getimagesizefromstring(Storage::disk('public')->get($file))[0];
}

test('uploads are resized to each width as webp', function () {
    ProductImage::factory()->for($this->product)->create(['sort_order' => 4]);

    $this->actingAs($this->admin)
        ->post("/admin/products/{$this->product->id}/images", ['images' => [UploadedFile::fake()->image('tee.jpg', 1600, 1200)]])
        ->assertRedirect()
        ->assertSessionHasNoErrors();

    $image = $this->product->images()->reorder('id', 'desc')->first();

    expect($image->sort_order)->toBe(5)
        ->and(storedWidth("{$image->path}-400.webp"))->toBe(400)
        ->and(storedWidth("{$image->path}-1200.webp"))->toBe(1200)
        ->and(getimagesizefromstring(Storage::disk('public')->get("{$image->path}-800.webp"))['mime'])->toBe('image/webp');
});

test('small uploads are not upscaled', function () {
    $this->actingAs($this->admin)
        ->post("/admin/products/{$this->product->id}/images", ['images' => [UploadedFile::fake()->image('small.png', 300, 300)]]);

    expect(storedWidth("{$this->product->images()->first()->path}-1200.webp"))->toBe(300);
});

test('non-image uploads are rejected', function () {
    $this->actingAs($this->admin)
        ->post("/admin/products/{$this->product->id}/images", ['images' => [UploadedFile::fake()->create('menu.pdf', 10, 'application/pdf')]])
        ->assertSessionHasErrors('images.0');

    expect($this->product->images()->count())->toBe(0);
});

test('admin can reorder images and set alt text and colour', function () {
    [$first, $second] = ProductImage::factory()->for($this->product)->count(2)->sequence(['sort_order' => 0], ['sort_order' => 1])->create();

    $this->actingAs($this->admin)
        ->put("/admin/products/{$this->product->id}/images", ['images' => [
            ['id' => $second->id, 'alt' => 'Back print', 'colour' => 'Black'],
            ['id' => $first->id, 'alt' => null, 'colour' => null],
        ]])
        ->assertSessionHasNoErrors();

    expect($this->product->images()->pluck('id')->all())->toBe([$second->id, $first->id])
        ->and($second->fresh()->only('alt', 'colour'))->toBe(['alt' => 'Back print', 'colour' => 'Black'])
        ->and($first->fresh()->alt)->toBe('');
});

test('deleting an image removes its files', function () {
    $image = ProductImage::factory()->for($this->product)->create();
    Storage::disk('public')->put("{$image->path}-800.webp", 'x');

    $this->actingAs($this->admin)->delete("/admin/products/{$this->product->id}/images/{$image->id}")->assertRedirect();

    Storage::disk('public')->assertMissing("{$image->path}-800.webp");
    expect(ProductImage::count())->toBe(0);
});

test('an image of another product cannot be deleted through this product', function () {
    $foreign = ProductImage::factory()->create();

    $this->actingAs($this->admin)->delete("/admin/products/{$this->product->id}/images/{$foreign->id}")->assertNotFound();
});

test('non-admins cannot upload images', function () {
    $this->actingAs(User::factory()->create())
        ->post("/admin/products/{$this->product->id}/images", ['images' => [UploadedFile::fake()->image('tee.jpg')]])
        ->assertForbidden();
});
