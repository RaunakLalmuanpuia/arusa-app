<?php

use App\Models\Page;
use App\Models\User;

beforeEach(function () {
    $this->admin = User::factory()->admin()->create();
    $this->blocks = [['type' => 'text', 'data' => ['markdown' => 'Questions']]];
});

test('admin sees the page list', function () {
    Page::factory()->create(['title' => 'Size Guide']);

    $this->actingAs($this->admin)
        ->get('/admin/pages')
        ->assertOk()
        ->assertInertia(fn ($page) => $page->component('Admin/Pages/Index')->has('pages', 1));
});

test('admin can create a page', function () {
    $this->actingAs($this->admin)
        ->post('/admin/pages', ['title' => 'FAQ', 'slug' => 'faq', 'blocks' => $this->blocks])
        ->assertRedirect('/admin/pages');

    expect(Page::firstWhere('slug', 'faq')->blocks)->toEqual($this->blocks);
});

test('admin edits a page with its stored blocks', function () {
    $page = Page::factory()->create(['blocks' => $this->blocks]);

    $this->actingAs($this->admin)
        ->get("/admin/pages/{$page->id}/edit")
        ->assertInertia(fn ($inertia) => $inertia
            ->component('Admin/Pages/Edit')
            ->where('page.blocks', $this->blocks)
            ->has('imageBaseUrl'));
});

test('admin can update a page keeping its own slug', function () {
    $page = Page::factory()->create(['slug' => 'about']);

    $this->actingAs($this->admin)
        ->put("/admin/pages/{$page->id}", ['title' => 'About us', 'slug' => 'about', 'blocks' => $this->blocks])
        ->assertRedirect('/admin/pages');

    expect($page->fresh()->title)->toBe('About us');
});

test('slugs must be unique and url safe', function () {
    Page::factory()->create(['slug' => 'about']);

    $this->actingAs($this->admin)
        ->post('/admin/pages', ['title' => 'Dup', 'slug' => 'about', 'blocks' => $this->blocks])
        ->assertSessionHasErrors('slug');

    $this->actingAs($this->admin)
        ->post('/admin/pages', ['title' => 'Bad', 'slug' => 'has spaces/slash', 'blocks' => $this->blocks])
        ->assertSessionHasErrors('slug');
});

test('a page needs at least one block', function () {
    $this->actingAs($this->admin)
        ->post('/admin/pages', ['title' => 'Empty', 'slug' => 'empty', 'blocks' => []])
        ->assertSessionHasErrors('blocks');
});

test('admin can delete a page', function () {
    $page = Page::factory()->create();

    $this->actingAs($this->admin)->delete("/admin/pages/{$page->id}")->assertRedirect('/admin/pages');

    expect(Page::count())->toBe(0);
});

test('non-admins cannot manage pages', function () {
    $this->actingAs(User::factory()->create())
        ->post('/admin/pages', ['title' => 'X', 'slug' => 'x', 'blocks' => $this->blocks])
        ->assertForbidden();
});
