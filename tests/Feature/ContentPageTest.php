<?php

use App\Models\Page;

test('a content page renders its markdown as html', function () {
    Page::factory()->create(['slug' => 'about', 'title' => 'About Arusa', 'body' => "# Hello\n\nMade in **Aizawl**."]);

    $this->get('/pages/about')
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Content/Show')
            ->where('title', 'About Arusa')
            ->where('html', fn (string $html) => str_contains($html, '<strong>Aizawl</strong>')));
});

test('raw html and unsafe links in page bodies are stripped', function () {
    Page::factory()->create(['slug' => 'x', 'body' => "<script>alert(1)</script>\n\n[click](javascript:alert(1))"]);

    $this->get('/pages/x')
        ->assertInertia(fn ($page) => $page
            ->where('html', fn (string $html) => ! str_contains($html, '<script') && ! str_contains($html, 'javascript:')));
});

test('unknown content pages return 404', function () {
    $this->get('/pages/missing')->assertNotFound();
});

test('markdown tables render as html tables', function () {
    Page::factory()->create(['slug' => 'size-guide', 'body' => "| Size | Chest |\n|---|---|\n| S | 36 |"]);

    $this->get('/pages/size-guide')
        ->assertInertia(fn ($page) => $page
            ->where('html', fn (string $html) => str_contains($html, '<table>')));
});
