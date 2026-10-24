<?php

use App\Enums\Gender;
use App\Models\Category;
use App\Models\Product;
use Database\Seeders\CatalogSeeder;
use Database\Seeders\CategoryImageSeeder;
use Illuminate\Support\Facades\Storage;

test('the catalog seeder attaches a photo per tee colour and one for the mug', function () {
    Storage::fake('public');

    $this->seed(CatalogSeeder::class);

    $tee = Product::where('slug', 'wolf-badge-tee')->firstOrFail();
    expect($tee->images->pluck('colour')->all())->toBe(['Black', 'Cream']);

    $mug = Product::where('slug', 'enamel-mug')->firstOrFail();
    expect($mug->images)->toHaveCount(1)
        ->and($mug->images->first()->colour)->toBeNull();

    Storage::disk('public')->assertExists($tee->images->first()->path.'-1200.webp');

    $beanie = Product::where('slug', 'knit-beanie')->firstOrFail();
    expect($beanie->gender)->toBe(Gender::Unisex)
        ->and($beanie->images->pluck('colour')->all())->toBe(['White', 'Grey'])
        ->and($beanie->variants->pluck('sku')->all())->toBe(['KNIT-BEANIE-WHITE', 'KNIT-BEANIE-GREY'])
        ->and(Product::whereNotNull('gender')->has('images')->count())->toBe(13)
        ->and(Product::whereNull('gender')->has('images')->count())->toBe(7);

    $candle = Product::where('slug', 'soy-candle')->firstOrFail();
    expect($candle->variants->pluck('sku')->all())->toBe(['SOY-CANDLE'])
        ->and($candle->images)->toHaveCount(1);
});

test('the category image seeder gives subcategories a product photo', function () {
    Storage::fake('public');
    $this->seed(CatalogSeeder::class);

    $this->seed(CategoryImageSeeder::class);

    $headwearPath = Category::where('slug', 'headwear')->value('image_path');
    Storage::disk('public')->assertExists("{$headwearPath}-800.webp");
});

test('the category image seeder fills top-level tiles once and keeps existing images', function () {
    Storage::fake('public');
    $this->seed(CatalogSeeder::class);
    Category::where('slug', 'goods')->update(['image_path' => 'categories/custom']);

    $this->seed(CategoryImageSeeder::class);
    $apparelPath = Category::where('slug', 'apparel')->value('image_path');
    $this->seed(CategoryImageSeeder::class);

    expect(Category::where('slug', 'apparel')->value('image_path'))->toBe($apparelPath)
        ->and(Category::where('slug', 'goods')->value('image_path'))->toBe('categories/custom');
    Storage::disk('public')->assertExists("{$apparelPath}-1600.webp");
});
