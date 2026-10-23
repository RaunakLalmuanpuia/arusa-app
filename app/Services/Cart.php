<?php

namespace App\Services;

use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\User;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;

class Cart
{
    /**
     * Most of one variant a shopper can hold.
     */
    public const MAX_PER_LINE = 10;

    private const SESSION_KEY = 'cart';

    public function __construct(private Request $request) {}

    /**
     * Variant id => quantity: the saved cart when signed in, otherwise the session cart.
     *
     * @return array<int, int>
     */
    public function quantities(): array
    {
        $user = $this->request->user();

        if ($user) {
            return $user->cartItems()->pluck('quantity', 'product_variant_id')->map(fn ($quantity): int => (int) $quantity)->all();
        }

        return $this->request->session()->get(self::SESSION_KEY, []);
    }

    public function add(ProductVariant $variant, int $quantity): void
    {
        $this->set($variant, ($this->quantities()[$variant->id] ?? 0) + $quantity);
    }

    /**
     * Store a quantity, capped at stock and MAX_PER_LINE. Zero or less removes the line.
     */
    public function set(ProductVariant $variant, int $quantity): void
    {
        $quantity = min($quantity, $variant->stock, self::MAX_PER_LINE);

        if ($quantity < 1) {
            $this->remove($variant->id);

            return;
        }

        $user = $this->request->user();

        if ($user) {
            $user->cartItems()->updateOrCreate(['product_variant_id' => $variant->id], ['quantity' => $quantity]);
        } else {
            $this->request->session()->put(self::SESSION_KEY.'.'.$variant->id, $quantity);
        }
    }

    public function remove(int $variantId): void
    {
        $this->request->user()?->cartItems()->where('product_variant_id', $variantId)->delete();
        $this->request->session()->forget(self::SESSION_KEY.'.'.$variantId);
    }

    public function clear(): void
    {
        $this->request->user()?->cartItems()->delete();
        $this->request->session()->forget(self::SESSION_KEY);
    }

    /**
     * Move the guest session cart into the user's saved cart, adding quantities up to the caps.
     */
    public function mergeSessionInto(User $user): void
    {
        $guest = $this->request->session()->pull(self::SESSION_KEY, []);

        if ($guest === []) {
            return;
        }

        $saved = $user->cartItems()->pluck('quantity', 'product_variant_id');

        foreach (ProductVariant::whereKey(array_keys($guest))->get() as $variant) {
            $quantity = min(($saved[$variant->id] ?? 0) + $guest[$variant->id], $variant->stock, self::MAX_PER_LINE);

            if ($quantity > 0) {
                $user->cartItems()->updateOrCreate(['product_variant_id' => $variant->id], ['quantity' => $quantity]);
            }
        }
    }

    /**
     * Cart lines priced from current variant data. Lines that can't be bought now are kept but flagged.
     *
     * @return Collection<int, array{variant_id: int, name: string, slug: string, size: ?string, colour: ?string, image: array{src: string, srcset: string, alt: string}|null, unit_price: int, quantity: int, max_quantity: int, line_total: int, available: bool}>
     */
    public function lines(): Collection
    {
        $quantities = $this->quantities();

        if ($quantities === []) {
            return collect();
        }

        $variants = ProductVariant::query()
            ->with(['product', 'product.images' => fn (HasMany $images) => $images->limit(1)])
            ->whereKey(array_keys($quantities))
            ->orderBy('id')
            ->get();

        $visibleProductIds = Product::visible()->whereKey($variants->pluck('product_id'))->pluck('id');

        return $variants->map(function (ProductVariant $variant) use ($quantities, $visibleProductIds): array {
            $available = $variant->is_active && $variant->stock > 0 && $visibleProductIds->contains($variant->product_id);
            $quantity = $available ? min($quantities[$variant->id], $variant->stock, self::MAX_PER_LINE) : $quantities[$variant->id];
            $unitPrice = $variant->effectivePrice();

            return [
                'variant_id' => $variant->id,
                'name' => $variant->product->name,
                'slug' => $variant->product->slug,
                'size' => $variant->size,
                'colour' => $variant->colour,
                'image' => $variant->product->images->first()?->toImageData(),
                'unit_price' => $unitPrice,
                'quantity' => $quantity,
                'max_quantity' => max(0, min($variant->stock, self::MAX_PER_LINE)),
                'line_total' => $unitPrice * $quantity,
                'available' => $available,
            ];
        })->values();
    }

    /**
     * @return array{lines: list<array<string, mixed>>, count: int, subtotal: int, has_unavailable: bool}
     */
    public function summary(): array
    {
        $lines = $this->lines();
        $available = $lines->where('available', true);

        return [
            'lines' => $lines->all(),
            'count' => (int) $available->sum('quantity'),
            'subtotal' => (int) $available->sum('line_total'),
            'has_unavailable' => $lines->contains('available', false),
        ];
    }
}
