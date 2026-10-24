<?php

use App\Models\Page;
use App\Models\Setting;
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

test('contact cards omit phone and email until settings exist', function () {
    $this->seed(PageSeeder::class);

    $cards = collect(Page::firstWhere('slug', 'contact')->blocks)->firstWhere('type', 'contact_cards')['data']['items'];

    expect(collect($cards)->pluck('kind')->all())->toBe(['address', 'hours']);
});

test('contact cards use the contact settings', function () {
    Setting::create(['key' => 'contact_phone', 'value' => '9862000000']);
    Setting::create(['key' => 'whatsapp_number', 'value' => '9862000001']);
    Setting::create(['key' => 'contact_email', 'value' => 'hello@arusa.co.in']);

    $this->seed(PageSeeder::class);

    $cards = collect(Page::firstWhere('slug', 'contact')->blocks)->firstWhere('type', 'contact_cards')['data']['items'];

    expect(collect($cards)->pluck('value', 'kind')->all())->toMatchArray([
        'whatsapp' => '9862000001',
        'phone' => '9862000000',
        'email' => 'hello@arusa.co.in',
    ]);
});
