<?php

namespace App\Actions;

use App\Models\Product;
use App\Models\ProductImage;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Str;

class StoreProductImage
{
    public function __construct(private StoreWebpImage $storeWebpImage) {}

    /**
     * Resize the upload to every ProductImage width, save each as WebP, and attach it to the product.
     */
    public function handle(Product $product, UploadedFile $file): ProductImage
    {
        $path = "products/{$product->id}/".Str::uuid();

        $this->storeWebpImage->handle($file, $path, ProductImage::WIDTHS, 'images');

        return $product->images()->create([
            'path' => $path,
            'alt' => $product->name,
            'sort_order' => (int) $product->images()->max('sort_order') + 1,
        ]);
    }
}
