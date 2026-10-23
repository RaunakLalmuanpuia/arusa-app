<?php

use App\Models\Setting;
use App\Models\User;

beforeEach(function () {
    $this->admin = User::factory()->admin()->create();
});

test('settings page shows defaults when nothing is stored', function () {
    $this->actingAs($this->admin)
        ->get('/admin/settings')
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Admin/Settings/Edit')
            ->where('settings.store_name', 'Arusa')
            ->where('settings.cod_enabled', '1'));
});

test('admin can update settings and they are shared to pages', function () {
    $this->actingAs($this->admin)
        ->put('/admin/settings', [
            'store_name' => 'Arusa Aizawl',
            'contact_phone' => '9862000000',
            'whatsapp_number' => '9862000001',
            'contact_email' => 'hello@arusa.co.in',
            'cod_enabled' => false,
        ])
        ->assertRedirect()
        ->assertSessionHas('status', 'Settings saved.');

    expect(Setting::values())
        ->store_name->toBe('Arusa Aizawl')
        ->cod_enabled->toBe('0');

    $this->get('/')
        ->assertInertia(fn ($page) => $page
            ->where('store.store_name', 'Arusa Aizawl')
            ->where('store.whatsapp_number', '9862000001'));
});

test('settings reject invalid phone numbers and email', function () {
    $this->actingAs($this->admin)
        ->put('/admin/settings', [
            'store_name' => 'Arusa',
            'contact_phone' => '12345',
            'whatsapp_number' => '+919862000000',
            'contact_email' => 'not-an-email',
            'cod_enabled' => true,
        ])
        ->assertSessionHasErrors(['contact_phone', 'whatsapp_number', 'contact_email']);
});

test('non-admins cannot update settings', function () {
    $this->actingAs(User::factory()->create())
        ->put('/admin/settings', ['store_name' => 'Hacked', 'cod_enabled' => true])
        ->assertForbidden();

    expect(Setting::values()['store_name'])->toBe('Arusa');
});
