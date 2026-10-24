<?php

use App\Models\Product;
use Database\Seeders\CatalogSeeder;
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
});
