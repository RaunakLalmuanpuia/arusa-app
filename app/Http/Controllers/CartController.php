<?php

namespace App\Http\Controllers;

use App\Models\ProductVariant;
use App\Services\Cart;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class CartController extends Controller
{
    /**
     * The page reads the shared `cart` prop.
     */
    public function index(): Response
    {
        return Inertia::render('Cart/Index');
    }

    public function store(Request $request, Cart $cart): RedirectResponse
    {
        $validated = $request->validate([
            'variant_id' => ['required', 'integer'],
            'quantity' => ['required', 'integer', 'min:1', 'max:'.Cart::MAX_PER_LINE],
        ]);

        $variant = ProductVariant::query()
            ->where('is_active', true)
            ->where('stock', '>', 0)
            ->whereHas('product', fn (Builder $product) => $product->visible())
            ->find($validated['variant_id']);

        if (! $variant) {
            throw ValidationException::withMessages(['variant_id' => 'This item is no longer available.']);
        }

        $cart->add($variant, $validated['quantity']);

        return back();
    }

    public function update(Request $request, Cart $cart, ProductVariant $variant): RedirectResponse
    {
        abort_unless(array_key_exists($variant->id, $cart->quantities()), 404);

        $validated = $request->validate([
            'quantity' => ['required', 'integer', 'min:0', 'max:'.Cart::MAX_PER_LINE],
        ]);

        $cart->set($variant, $validated['quantity']);

        return back();
    }

    public function destroy(Cart $cart, ProductVariant $variant): RedirectResponse
    {
        $cart->remove($variant->id);

        return back();
    }
}
