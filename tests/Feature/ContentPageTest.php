<?php

use App\Models\Page;
use Illuminate\Support\Facades\Storage;

test('text blocks render their markdown as html', function () {
    Page::factory()->create(['slug' => 'about', 'title' => 'About Arusa', 'blocks' => [
        ['type' => 'text', 'data' => ['markdown' => "# Hello\n\nMade in **Aizawl**."]],
    ]]);

    $this->get('/pages/about')
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Content/Show')
            ->where('title', 'About Arusa')
            ->where('blocks.0.type', 'text')
            ->where('blocks.0.data.html', fn (string $html) => str_contains($html, '<strong>Aizawl</strong>'))
            ->missing('blocks.0.data.markdown'));
});

test('raw html and unsafe links are stripped from text and faq answers', function () {
    $unsafe = "<script>alert(1)</script>\n\n[click](javascript:alert(1))";

    Page::factory()->create(['slug' => 'x', 'blocks' => [
        ['type' => 'text', 'data' => ['markdown' => $unsafe]],
        ['type' => 'faq', 'data' => ['items' => [['question' => 'Safe?', 'answer' => $unsafe]]]],
    ]]);

    $isSafe = fn (string $html) => ! str_contains($html, '<script') && ! str_contains($html, 'javascript:');

    $this->get('/pages/x')
        ->assertInertia(fn ($page) => $page
            ->where('blocks.0.data.html', $isSafe)
            ->where('blocks.1.data.items.0.html', $isSafe)
            ->missing('blocks.1.data.items.0.answer'));
});

test('markdown tables render as html tables', function () {
    Page::factory()->create(['slug' => 'size-guide', 'blocks' => [
        ['type' => 'text', 'data' => ['markdown' => "| Size | Chest |\n|---|---|\n| S | 36 |"]],
    ]]);

    $this->get('/pages/size-guide')
        ->assertInertia(fn ($page) => $page->where('blocks.0.data.html', fn (string $html) => str_contains($html, '<table>')));
});

test('block images become src and srcset urls', function () {
    Storage::fake('public');

    Page::factory()->create(['slug' => 'about', 'blocks' => [
        ['type' => 'hero', 'data' => ['heading' => 'About']],
        ['type' => 'image_text', 'data' => ['image' => 'pages/shop', 'image_side' => 'left', 'markdown' => 'Hi']],
    ]]);

    $disk = Storage::disk('public');

    $this->get('/pages/about')
        ->assertInertia(fn ($page) => $page
            ->where('blocks.0.data.image', null)
            ->where('blocks.1.data.image.src', $disk->url('pages/shop-800.webp'))
            ->where('blocks.1.data.image.srcset', $disk->url('pages/shop-800.webp').' 800w, '.$disk->url('pages/shop-1600.webp').' 1600w')
            ->where('blocks.1.data.html', "<p>Hi</p>\n"));
});

test('contact cards get links for their kind', function () {
    Page::factory()->create(['slug' => 'contact', 'blocks' => [
        ['type' => 'contact_cards', 'data' => ['items' => [
            ['kind' => 'phone', 'label' => 'Call', 'value' => '9876543210'],
            ['kind' => 'whatsapp', 'label' => 'WhatsApp', 'value' => '9876543210'],
            ['kind' => 'email', 'label' => 'Email', 'value' => 'hello@arusa.test'],
            ['kind' => 'address', 'label' => 'Studio', 'value' => 'Aizawl'],
        ]]],
    ]]);

    $this->get('/pages/contact')
        ->assertInertia(fn ($page) => $page
            ->where('blocks.0.data.items.0.href', 'tel:+919876543210')
            ->where('blocks.0.data.items.1.href', 'https://wa.me/919876543210')
            ->where('blocks.0.data.items.2.href', 'mailto:hello@arusa.test')
            ->where('blocks.0.data.items.3.href', null));
});

test('unknown content pages return 404', function () {
    $this->get('/pages/missing')->assertNotFound();
});
