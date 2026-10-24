<?php

use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    Storage::fake('public');
});

test('admin uploads a page image as webp at each width', function () {
    $response = $this->actingAs(User::factory()->admin()->create())
        ->postJson('/admin/page-images', ['image' => UploadedFile::fake()->image('shop.jpg', 2000, 1000)])
        ->assertOk();

    $path = $response->json('path');

    expect($path)->toMatch('#^pages/[0-9a-f-]{36}$#')
        ->and($response->json('url'))->toBe(Storage::disk('public')->url("{$path}-800.webp"))
        ->and(getimagesizefromstring(Storage::disk('public')->get("{$path}-1600.webp"))[0])->toBe(1600)
        ->and(getimagesizefromstring(Storage::disk('public')->get("{$path}-800.webp"))['mime'])->toBe('image/webp');
});

test('non-image uploads are rejected', function () {
    $this->actingAs(User::factory()->admin()->create())
        ->postJson('/admin/page-images', ['image' => UploadedFile::fake()->create('menu.pdf', 10, 'application/pdf')])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('image');

    expect(Storage::disk('public')->allFiles())->toBe([]);
});

test('non-admins cannot upload page images', function () {
    $this->actingAs(User::factory()->create())
        ->postJson('/admin/page-images', ['image' => UploadedFile::fake()->image('shop.jpg')])
        ->assertForbidden();
});
