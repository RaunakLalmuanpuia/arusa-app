<?php

namespace App\Http\Controllers\Admin;

use App\Actions\StoreProductImage;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreProductImagesRequest;
use App\Http\Requests\Admin\UpdateProductImagesRequest;
use App\Models\Product;
use App\Models\ProductImage;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;

class ProductImageController extends Controller
{
    public function store(StoreProductImagesRequest $request, Product $product, StoreProductImage $storeProductImage): RedirectResponse
    {
        foreach ($request->file('images') as $file) {
            $storeProductImage->handle($product, $file);
        }

        return back()->with('status', 'Images uploaded.');
    }

    /**
     * Save alt text and colour, and set the order to the order the images were sent in.
     */
    public function update(UpdateProductImagesRequest $request, Product $product): RedirectResponse
    {
        DB::transaction(function () use ($request, $product): void {
            foreach ($request->validated('images') as $position => $image) {
                $product->images()->whereKey($image['id'])->update([
                    'alt' => $image['alt'] ?? '',
                    'colour' => $image['colour'] ?? null,
                    'sort_order' => $position,
                ]);
            }
        });

        return back()->with('status', 'Images saved.');
    }

    public function destroy(Product $product, ProductImage $image): RedirectResponse
    {
        $image->delete();
        $image->deleteFiles();

        return back()->with('status', 'Image deleted.');
    }
}
