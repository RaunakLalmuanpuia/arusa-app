<?php

namespace App\Http\Controllers;

use App\Models\Product;
use App\Models\ProductImage;
use App\Models\ProductVariant;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Inertia\Inertia;
use Inertia\Response;

class ProductController extends Controller
{
    public function show(Product $product): Response
    {
        abort_unless(Product::visible()->whereKey($product->id)->exists(), 404);

        $product->load([
            'category:id,name,slug',
            'variants' => fn (HasMany $variants) => $variants->where('is_active', true),
            'images',
        ]);

        return Inertia::render('Catalog/Product', [
            'product' => [
                'name' => $product->name,
                'slug' => $product->slug,
                'description' => $product->description,
                'category' => $product->category->only('name', 'slug'),
            ],
            'variants' => $product->variants->map(fn (ProductVariant $variant): array => [
                'id' => $variant->id,
                'size' => $variant->size,
                'colour' => $variant->colour,
                'price' => $variant->effectivePrice(),
                'in_stock' => $variant->stock > 0,
            ]),
            'images' => $product->images->map(fn (ProductImage $image): array => [
                ...$image->toImageData(),
                'colour' => $image->colour,
            ]),
        ]);
    }
}
