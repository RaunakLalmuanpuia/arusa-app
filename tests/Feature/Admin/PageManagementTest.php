<?php

use App\Models\Page;
use App\Models\User;

beforeEach(function () {
    $this->admin = User::factory()->admin()->create();
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
        ->post('/admin/pages', ['title' => 'FAQ', 'slug' => 'faq', 'body' => 'Questions'])
        ->assertRedirect('/admin/pages');

    expect(Page::where('slug', 'faq')->exists())->toBeTrue();
});

test('admin can update a page keeping its own slug', function () {
    $page = Page::factory()->create(['slug' => 'about']);

    $this->actingAs($this->admin)
        ->put("/admin/pages/{$page->id}", ['title' => 'About us', 'slug' => 'about', 'body' => 'New body'])
        ->assertRedirect('/admin/pages');

    expect($page->fresh()->title)->toBe('About us');
});

test('slugs must be unique and url safe', function () {
    Page::factory()->create(['slug' => 'about']);

    $this->actingAs($this->admin)
        ->post('/admin/pages', ['title' => 'Dup', 'slug' => 'about', 'body' => 'x'])
        ->assertSessionHasErrors('slug');

    $this->actingAs($this->admin)
        ->post('/admin/pages', ['title' => 'Bad', 'slug' => 'has spaces/slash', 'body' => 'x'])
        ->assertSessionHasErrors('slug');
});

test('admin can delete a page', function () {
    $page = Page::factory()->create();

    $this->actingAs($this->admin)->delete("/admin/pages/{$page->id}")->assertRedirect('/admin/pages');

    expect(Page::count())->toBe(0);
});

test('non-admins cannot manage pages', function () {
    $this->actingAs(User::factory()->create())
        ->post('/admin/pages', ['title' => 'X', 'slug' => 'x', 'body' => 'x'])
        ->assertForbidden();
});
