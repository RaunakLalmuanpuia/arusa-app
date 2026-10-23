<?php

use App\Models\User;

test('guests are redirected to login from admin', function () {
    $this->get('/admin')->assertRedirect('/login');
});

test('non-admin users are forbidden from admin', function () {
    $this->actingAs(User::factory()->create())
        ->get('/admin')
        ->assertForbidden();
});

test('admins can view the admin dashboard', function () {
    $this->actingAs(User::factory()->admin()->create())
        ->get('/admin')
        ->assertOk()
        ->assertInertia(fn ($page) => $page->component('Admin/Dashboard'));
});

test('is_admin cannot be mass assigned', function () {
    $user = User::factory()->create();

    $user->fill(['is_admin' => true])->save();

    expect($user->fresh()->is_admin)->toBeFalse();
});

test('admin:grant makes an existing user an admin', function () {
    $user = User::factory()->create(['email' => 'owner@arusa.co.in']);

    $this->artisan('admin:grant', ['email' => 'owner@arusa.co.in'])
        ->expectsOutput('owner@arusa.co.in is now an admin.')
        ->assertSuccessful();

    expect($user->fresh()->is_admin)->toBeTrue();
});

test('admin:grant fails for an unknown email', function () {
    $this->artisan('admin:grant', ['email' => 'nobody@example.com'])
        ->expectsOutput('No user with email nobody@example.com.')
        ->assertFailed();
});
