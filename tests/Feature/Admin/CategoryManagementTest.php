<?php

use App\Models\Category;
use App\Models\Product;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    $this->admin = User::factory()->admin()->create();
});

test('admin can search the category list', function () {
    Category::factory()->create(['name' => 'Hoodies']);
    Category::factory()->create(['name' => 'Mugs']);

    $this->actingAs($this->admin)
        ->get('/admin/categories?search=hood')
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Admin/Categories/Index')
            ->has('categories.data', 1)
            ->where('categories.data.0.name', 'Hoodies')
            ->where('filters.search', 'hood'));
});

test('admin can create a subcategory', function () {
    $parent = Category::factory()->create();

    $this->actingAs($this->admin)
        ->post('/admin/categories', [
            'name' => 'Tees', 'slug' => 'tees', 'parent_id' => $parent->id, 'sort_order' => 2, 'is_active' => true,
        ])
        ->assertRedirect('/admin/categories');

    expect(Category::where('slug', 'tees')->first()->parent_id)->toBe($parent->id);
});

test('categories nest only one level deep', function () {
    $child = Category::factory()->for(Category::factory(), 'parent')->create();

    $this->actingAs($this->admin)
        ->post('/admin/categories', [
            'name' => 'Deep', 'slug' => 'deep', 'parent_id' => $child->id, 'sort_order' => 0, 'is_active' => true,
        ])
        ->assertSessionHasErrors('parent_id');
});

test('a category with subcategories cannot be given a parent', function () {
    $parent = Category::factory()->create();
    Category::factory()->create(['parent_id' => $parent->id]);
    $other = Category::factory()->create();

    $this->actingAs($this->admin)
        ->put("/admin/categories/{$parent->id}", [
            'name' => $parent->name, 'slug' => $parent->slug, 'parent_id' => $other->id, 'sort_order' => 0, 'is_active' => true,
        ])
        ->assertSessionHasErrors('parent_id');
});

test('a category with products cannot be deleted', function () {
    $category = Category::factory()->create();
    Product::factory()->for($category)->create();

    $this->actingAs($this->admin)
        ->delete("/admin/categories/{$category->id}")
        ->assertSessionHasErrors('category');

    expect($category->fresh())->not->toBeNull();
});

test('an empty category can be deleted', function () {
    $category = Category::factory()->create();

    $this->actingAs($this->admin)->delete("/admin/categories/{$category->id}")->assertRedirect('/admin/categories');

    expect(Category::count())->toBe(0);
});

test('admin can upload a category image and it shows on the home page', function () {
    Storage::fake('public');

    $this->actingAs($this->admin)
        ->post('/admin/categories', [
            'name' => 'Tees', 'slug' => 'tees', 'parent_id' => null, 'sort_order' => 0, 'is_active' => true,
            'image' => UploadedFile::fake()->image('tees.jpg', 2000, 1000),
        ])
        ->assertRedirect('/admin/categories');

    $path = Category::where('slug', 'tees')->value('image_path');

    expect(getimagesizefromstring(Storage::disk('public')->get("{$path}-1600.webp"))['mime'])->toBe('image/webp');

    $this->get('/')->assertInertia(fn ($page) => $page
        ->where('categories.0.image.src', Storage::disk('public')->url("{$path}-800.webp")));
});

test('replacing a category image deletes the old files', function () {
    Storage::fake('public');
    $category = Category::factory()->create();
    $update = fn () => $this->actingAs($this->admin)->put("/admin/categories/{$category->id}", [
        'name' => $category->name, 'slug' => $category->slug, 'parent_id' => null, 'sort_order' => 0, 'is_active' => true,
        'image' => UploadedFile::fake()->image('tees.jpg', 1000, 500),
    ])->assertRedirect('/admin/categories');

    $update();
    $oldPath = $category->fresh()->image_path;
    $update();

    expect(Storage::disk('public')->allFiles())->toHaveCount(2)
        ->and(Storage::disk('public')->exists("{$oldPath}-800.webp"))->toBeFalse();
});

test('deleting a category deletes its image files', function () {
    Storage::fake('public');
    Storage::disk('public')->put('categories/abc-800.webp', 'x');
    $category = Category::factory()->create(['image_path' => 'categories/abc']);

    $this->actingAs($this->admin)->delete("/admin/categories/{$category->id}");

    expect(Storage::disk('public')->allFiles())->toBe([]);
});

test('category image must be an image', function () {
    $this->actingAs($this->admin)
        ->post('/admin/categories', [
            'name' => 'Tees', 'slug' => 'tees', 'parent_id' => null, 'sort_order' => 0, 'is_active' => true,
            'image' => UploadedFile::fake()->create('menu.pdf', 10, 'application/pdf'),
        ])
        ->assertSessionHasErrors('image');
});

test('non-admins cannot manage categories', function () {
    $this->actingAs(User::factory()->create())->get('/admin/categories')->assertForbidden();
});
