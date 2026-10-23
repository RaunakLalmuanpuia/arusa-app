<?php

use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'can:access-admin'])
    ->prefix('admin')
    ->name('admin.')
    ->group(function () {
        Route::inertia('/', 'Admin/Dashboard')->name('dashboard');
    });
