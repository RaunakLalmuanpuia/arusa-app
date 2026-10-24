<?php

use App\Models\Page;
use App\Models\User;

beforeEach(function () {
    $this->admin = User::factory()->admin()->create();
    $this->blocks = [['type' => 'text', 'data' => ['markdown' => "## Sizes\n\n<script>alert(1)</script>"]]];
});

test('admin previews unsaved blocks rendered like the storefront', function () {
    $this->actingAs($this->admin)
        ->postJson(route('admin.pages.preview'), ['blocks' => $this->blocks])
        ->assertOk()
        ->assertJsonPath('blocks.0.type', 'text')
        ->assertJsonPath('blocks.0.data.html', fn (string $html) => str_contains($html, '<h2>Sizes</h2>') && ! str_contains($html, '<script>'));

    expect(Page::count())->toBe(0);
});

test('an invalid block is rejected with the same error key as saving', function () {
    $this->actingAs($this->admin)
        ->postJson(route('admin.pages.preview'), ['blocks' => [['type' => 'text', 'data' => ['markdown' => '']]]])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('blocks.0.data.markdown');
});

test('only admins can preview pages', function () {
    $this->post(route('admin.pages.preview'), ['blocks' => $this->blocks])->assertRedirect(route('login'));

    $this->actingAs(User::factory()->create())
        ->postJson(route('admin.pages.preview'), ['blocks' => $this->blocks])
        ->assertForbidden();
});
