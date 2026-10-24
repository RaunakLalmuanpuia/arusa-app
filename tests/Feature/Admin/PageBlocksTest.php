<?php

use App\Models\Page;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    Storage::fake('public');
    $this->image = 'pages/9b2f3c4e-1a2b-4c3d-8e9f-0a1b2c3d4e5f';
    Storage::disk('public')->put("{$this->image}-800.webp", 'webp');
    $this->admin = User::factory()->admin()->create();
});

/**
 * One valid block of each type, keyed by type.
 *
 * @return array<string, array{type: string, data: array<string, mixed>}>
 */
function validBlocks(string $image): array
{
    return [
        'hero' => ['type' => 'hero', 'data' => ['eyebrow' => 'Our story', 'heading' => 'About Arusa', 'intro' => 'Made in Aizawl.', 'image' => $image]],
        'text' => ['type' => 'text', 'data' => ['markdown' => 'Hello **there**.']],
        'image_text' => ['type' => 'image_text', 'data' => ['image' => $image, 'image_alt' => 'Shop front', 'image_side' => 'right', 'heading' => 'Where we started', 'markdown' => 'A small shop.']],
        'features' => ['type' => 'features', 'data' => ['heading' => 'Why us', 'items' => [
            ['icon' => 'cotton', 'title' => 'Cotton', 'text' => 'Soft.'],
            ['icon' => 'shield', 'title' => 'Durable', 'text' => 'Lasts.'],
        ]]],
        'table' => ['type' => 'table', 'data' => ['heading' => 'Sizes', 'columns' => ['Size', 'Chest'], 'rows' => [['S', '36'], ['M', '38']], 'note' => 'In inches.']],
        'faq' => ['type' => 'faq', 'data' => ['heading' => 'Questions', 'items' => [['question' => 'Do you ship?', 'answer' => 'Across **Mizoram**.']]]],
        'contact_cards' => ['type' => 'contact_cards', 'data' => ['heading' => 'Reach us', 'items' => [
            ['kind' => 'phone', 'label' => 'Call', 'value' => '9876543210'],
            ['kind' => 'email', 'label' => 'Email', 'value' => 'hello@arusa.test'],
            ['kind' => 'hours', 'label' => 'Hours', 'value' => 'Mon–Sat, 10am–6pm'],
        ]]],
        'call_to_action' => ['type' => 'call_to_action', 'data' => ['heading' => 'Shop the drop', 'text' => 'New tees are in.', 'button_label' => 'Shop now', 'button_url' => '/']],
    ];
}

/**
 * @param  list<array<string, mixed>>  $blocks
 * @return array<string, mixed>
 */
function pagePayload(array $blocks): array
{
    return ['title' => 'About', 'slug' => 'about', 'blocks' => $blocks];
}

test('a page with every block type saves and keeps only known fields', function () {
    $blocks = array_values(validBlocks($this->image));
    $sent = $blocks;
    $sent[0]['uid'] = 7;
    $sent[1]['data']['colour'] = 'red';

    $this->actingAs($this->admin)
        ->post('/admin/pages', pagePayload($sent))
        ->assertSessionHasNoErrors()
        ->assertRedirect('/admin/pages');

    $stored = Page::firstWhere('slug', 'about')->blocks;

    expect(array_is_list($stored))->toBeTrue()
        ->and(collect($stored)->pluck('type')->all())->toBe(collect($blocks)->pluck('type')->all())
        ->and($stored)->toEqual($blocks);
});

test('blocks and their nested lists are stored as JSON arrays, in order', function () {
    $valid = validBlocks($this->image);
    $blocks = [$valid['table'], $valid['features'], $valid['contact_cards'], $valid['text']];

    $this->actingAs($this->admin)
        ->post('/admin/pages', pagePayload($blocks))
        ->assertSessionHasNoErrors();

    $stored = Page::firstWhere('slug', 'about')->blocks;

    expect(array_is_list($stored))->toBeTrue()
        ->and(collect($stored)->pluck('type')->all())->toBe(['table', 'features', 'contact_cards', 'text'])
        ->and(DB::table('pages')->value('blocks'))->toStartWith('[');

    foreach ($stored as $block) {
        foreach (['items', 'columns', 'rows'] as $key) {
            if (isset($block['data'][$key])) {
                expect(array_is_list($block['data'][$key]))->toBeTrue();
            }
        }
        foreach ($block['data']['rows'] ?? [] as $row) {
            expect(array_is_list($row))->toBeTrue();
        }
    }
});

test('nested lists with client-chosen keys are rejected', function () {
    $block = validBlocks($this->image)['features'];
    $block['data']['items'] = ['x' => $block['data']['items'][0], 'y' => $block['data']['items'][1]];

    $this->actingAs($this->admin)
        ->post('/admin/pages', pagePayload([$block]))
        ->assertSessionHasErrors('blocks.0.data.items');
});

test('unknown block types are rejected', function () {
    $this->actingAs($this->admin)
        ->post('/admin/pages', pagePayload([['type' => 'carousel', 'data' => ['slides' => 3]]]))
        ->assertSessionHasErrors('blocks.0.type');
});

test('each block type requires its main field', function (string $type, string $field) {
    $block = validBlocks($this->image)[$type];
    unset($block['data'][$field]);

    $this->actingAs($this->admin)
        ->post('/admin/pages', pagePayload([$block]))
        ->assertSessionHasErrors("blocks.0.data.{$field}");
})->with([
    'hero heading' => ['hero', 'heading'],
    'text markdown' => ['text', 'markdown'],
    'image + text image' => ['image_text', 'image'],
    'features items' => ['features', 'items'],
    'table rows' => ['table', 'rows'],
    'faq items' => ['faq', 'items'],
    'contact items' => ['contact_cards', 'items'],
    'call to action link' => ['call_to_action', 'button_url'],
]);

test('call to action links must be a site path or https', function (string $url) {
    $block = validBlocks($this->image)['call_to_action'];
    $block['data']['button_url'] = $url;

    $this->actingAs($this->admin)
        ->post('/admin/pages', pagePayload([$block]))
        ->assertSessionHasErrors('blocks.0.data.button_url');
})->with(['javascript:alert(1)', 'http://example.com', '//evil.example', 'catalog', '/\\evil.example', "/\t/evil.example"]);

test('site paths and https links are accepted', function (string $url) {
    $block = validBlocks($this->image)['call_to_action'];
    $block['data']['button_url'] = $url;

    $this->actingAs($this->admin)
        ->post('/admin/pages', pagePayload([$block]))
        ->assertSessionHasNoErrors();
})->with(['/pages/size-guide', 'https://wa.me/919876543210']);

test('table rows must have one cell per column', function () {
    $block = validBlocks($this->image)['table'];
    $block['data']['rows'] = [['S', '36', 'extra']];

    $this->actingAs($this->admin)
        ->post('/admin/pages', pagePayload([$block]))
        ->assertSessionHasErrors('blocks.0.data.rows.0');
});

test('features allow at most four items', function () {
    $block = validBlocks($this->image)['features'];
    $block['data']['items'] = array_fill(0, 5, ['icon' => 'bag', 'title' => 'Bag', 'text' => 'Roomy.']);

    $this->actingAs($this->admin)
        ->post('/admin/pages', pagePayload([$block]))
        ->assertSessionHasErrors('blocks.0.data.items');
});

test('images must be uploaded page images', function (string $path) {
    $block = validBlocks($this->image)['image_text'];
    $block['data']['image'] = $path;

    $this->actingAs($this->admin)
        ->post('/admin/pages', pagePayload([$block]))
        ->assertSessionHasErrors('blocks.0.data.image');
})->with([
    'outside pages/' => ['products/1/9b2f3c4e-1a2b-4c3d-8e9f-0a1b2c3d4e5f'],
    'path traversal' => ['pages/../products/1/9b2f3c4e-1a2b-4c3d-8e9f-0a1b2c3d4e5f'],
    'never uploaded' => ['pages/00000000-0000-4000-8000-000000000000'],
]);

test('contact values must match their kind', function (string $kind, string $value) {
    $block = validBlocks($this->image)['contact_cards'];
    $block['data']['items'] = [['kind' => $kind, 'label' => 'Reach us', 'value' => $value]];

    $this->actingAs($this->admin)
        ->post('/admin/pages', pagePayload([$block]))
        ->assertSessionHasErrors('blocks.0.data.items.0.value');
})->with([
    'short whatsapp number' => ['whatsapp', '12345'],
    'landline-style phone' => ['phone', '0389123456'],
    'bad email' => ['email', 'not-an-email'],
]);

test('errors name the block they belong to', function () {
    $block = validBlocks($this->image)['hero'];
    unset($block['data']['heading']);

    $this->actingAs($this->admin)
        ->post('/admin/pages', pagePayload([validBlocks($this->image)['text'], $block]))
        ->assertSessionHasErrors(['blocks.1.data.heading' => 'The block 2 heading field is required.']);
});
