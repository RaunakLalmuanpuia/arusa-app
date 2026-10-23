<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\UpdateProductVariantsRequest;
use App\Models\Product;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;

class ProductVariantController extends Controller
{
    /**
     * Save the whole variant grid. Variants are never deleted; the admin deactivates them instead.
     */
    public function update(UpdateProductVariantsRequest $request, Product $product): RedirectResponse
    {
        DB::transaction(function () use ($request, $product): void {
            foreach ($request->validated('variants') as $variant) {
                $attributes = Arr::except($variant, 'id');

                if (isset($variant['id'])) {
                    $product->variants()->whereKey($variant['id'])->update($attributes);
                } else {
                    $product->variants()->create($attributes);
                }
            }
        });

        return back()->with('status', 'Variants saved.');
    }
}
