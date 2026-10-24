<?php

use App\Http\Controllers\Admin\CategoryController;
use App\Http\Controllers\Admin\DashboardController;
use App\Http\Controllers\Admin\OrderController;
use App\Http\Controllers\Admin\PageController;
use App\Http\Controllers\Admin\PageImageController;
use App\Http\Controllers\Admin\PagePreviewController;
use App\Http\Controllers\Admin\ProductController;
use App\Http\Controllers\Admin\ProductImageController;
use App\Http\Controllers\Admin\ProductVariantController;
use App\Http\Controllers\Admin\SettingController;
use App\Http\Controllers\Admin\ShippingZoneController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'can:access-admin'])
    ->prefix('admin')
    ->name('admin.')
    ->group(function () {
        Route::get('/', DashboardController::class)->name('dashboard');
        Route::resource('orders', OrderController::class)->only(['index', 'show']);
        Route::put('orders/{order}/status', [OrderController::class, 'updateStatus'])->name('orders.status.update');
        Route::post('orders/{order}/cancel', [OrderController::class, 'cancel'])->name('orders.cancel');
        Route::post('orders/{order}/payments/{payment}/refund', [OrderController::class, 'refund'])
            ->scopeBindings()
            ->name('orders.payments.refund');
        Route::get('orders/{order}/slip', [OrderController::class, 'slip'])->name('orders.slip');
        Route::post('pages/preview', PagePreviewController::class)->name('pages.preview');
        Route::resource('pages', PageController::class)->except('show');
        Route::post('page-images', [PageImageController::class, 'store'])->name('page-images.store');
        Route::resource('categories', CategoryController::class)->except('show');
        Route::resource('products', ProductController::class)->except('show');
        Route::put('products/{product}/variants', [ProductVariantController::class, 'update'])->name('products.variants.update');
        Route::post('products/{product}/images', [ProductImageController::class, 'store'])->name('products.images.store');
        Route::put('products/{product}/images', [ProductImageController::class, 'update'])->name('products.images.update');
        Route::delete('products/{product}/images/{image}', [ProductImageController::class, 'destroy'])
            ->scopeBindings()
            ->name('products.images.destroy');
        Route::resource('shipping-zones', ShippingZoneController::class)->except('show');
        Route::get('settings', [SettingController::class, 'edit'])->name('settings.edit');
        Route::put('settings', [SettingController::class, 'update'])->name('settings.update');
    });
