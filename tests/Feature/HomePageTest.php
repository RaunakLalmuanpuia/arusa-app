<?php

test('home page renders the storefront home', function () {
    $this->get('/')
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Home')
            ->where('store.store_name', 'Arusa'));
});
