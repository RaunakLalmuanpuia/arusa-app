<?php

use App\Models\Page;
use App\Models\User;
use Database\Seeders\PageSeeder;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    Storage::fake('public');
});

test('the seeder fills placeholder pages and leaves edited pages alone', function () {
    Page::factory()->create(['slug' => 'about', 'blocks' => [['type' => 'text', 'data' => ['markdown' => "Old.\n\nEdit this page in the admin."]]]]);
    Page::factory()->create(['slug' => 'contact', 'title' => 'Talk to us', 'blocks' => [['type' => 'text', 'data' => ['markdown' => 'Our own words.']]]]);

    $this->seed(PageSeeder::class);

    $about = Page::firstWhere('slug', 'about');

    expect(collect($about->blocks)->pluck('type')->all())->toBe(['hero', 'image_text', 'features', 'call_to_action'])
        ->and(Storage::disk('public')->exists("{$about->blocks[1]['data']['image']}-800.webp"))->toBeTrue()
        ->and(Page::firstWhere('slug', 'contact')->title)->toBe('Talk to us')
        ->and(Page::whereIn('slug', ['size-guide', 'shipping-returns'])->count())->toBe(2);
});

test('seeded pages pass the admin validation', function () {
    $this->seed(PageSeeder::class);
    $admin = User::factory()->admin()->create();

    Page::all()->each(fn (Page $page) => $this->actingAs($admin)
        ->put("/admin/pages/{$page->id}", $page->only('title', 'slug', 'blocks'))
        ->assertSessionHasNoErrors());
});
