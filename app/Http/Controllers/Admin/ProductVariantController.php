<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\UpdateProductVariantsRequest;
use App\Models\Product;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ProductVariantController extends Controller
{
    /**
     * Save the whole variant grid. Variants are never deleted; the admin deactivates them instead.
     * Rows are locked, and a row whose stock moved since the page loaded (an order came in) is refused.
     * A colour renamed on every variant that had it is renamed on its images too, so the gallery still matches.
     */
    public function update(UpdateProductVariantsRequest $request, Product $product): RedirectResponse
    {
        DB::transaction(function () use ($request, $product): void {
            $live = $product->variants()->lockForUpdate()->get(['id', 'stock', 'colour'])->keyBy('id');

            foreach ($request->validated('variants') as $index => $variant) {
                if (isset($variant['id'], $variant['stock_was']) && $live[$variant['id']]->stock !== (int) $variant['stock_was']) {
                    throw ValidationException::withMessages([
                        "variants.{$index}.stock" => 'Stock changed since you opened this page (an order came in). Reload and try again.',
                    ]);
                }
            }

            foreach ($request->validated('variants') as $variant) {
                $attributes = Arr::except($variant, ['id', 'stock_was']);

                if (isset($variant['id'])) {
                    $product->variants()->whereKey($variant['id'])->update($attributes);
                } else {
                    $product->variants()->create($attributes);
                }
            }

            foreach ($request->validated('variants') as $variant) {
                $oldColour = isset($variant['id']) ? $live[$variant['id']]->colour : null;

                if ($oldColour !== null && $oldColour !== ($variant['colour'] ?? null) && ! $product->variants()->where('colour', $oldColour)->exists()) {
                    $product->images()->where('colour', $oldColour)->update(['colour' => $variant['colour'] ?? null]);
                }
            }
        });

        return back()->with('status', 'Variants saved.');
    }
}
