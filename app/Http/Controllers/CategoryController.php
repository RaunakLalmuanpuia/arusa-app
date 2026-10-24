<?php

namespace App\Http\Controllers;

use App\Enums\Gender;
use App\Models\Category;
use App\Models\Product;
use App\Models\ProductVariant;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class CategoryController extends Controller
{
    public function show(Request $request, Category $category): Response
    {
        abort_unless($category->is_active && ($category->parent?->is_active ?? true), 404);

        $filters = $request->validate([
            'gender' => ['nullable', Rule::enum(Gender::class)],
            'size' => ['nullable', 'string', 'max:30'],
            'colour' => ['nullable', 'string', 'max:30'],
            'min' => ['nullable', 'integer', 'min:0'],
            'max' => ['nullable', 'integer', 'min:0'],
        ]);

        $subcategories = $category->children()->active()->orderBy('sort_order')->orderBy('name')->get(['id', 'name', 'slug']);
        $categoryIds = $subcategories->pluck('id')->push($category->id);
        $isFiltered = collect($filters)->except('gender')->filter(fn ($value) => $value !== null)->isNotEmpty();

        // shortcut: no pagination (catalog < ~100 products); add ->paginate() when a category passes ~50.
        $products = Product::visible()
            ->whereIn('category_id', $categoryIds)
            ->when($filters['gender'] ?? null, fn (Builder $query, string $gender) => $query->whereIn('gender', Gender::from($gender)->matching()))
            ->when($isFiltered, fn (Builder $query) => $query->whereHas('variants', fn (Builder $variant) => $variant
                ->where('is_active', true)
                ->where('stock', '>', 0)
                ->when($filters['size'] ?? null, fn (Builder $q, string $size) => $q->where('size', $size))
                ->when($filters['colour'] ?? null, fn (Builder $q, string $colour) => $q->where('colour', $colour))
                ->when($filters['min'] ?? null, fn (Builder $q, $min) => $q->whereRaw('coalesce(price_override, products.base_price) >= ?', [(int) $min * 100]))
                ->when($filters['max'] ?? null, fn (Builder $q, $max) => $q->whereRaw('coalesce(price_override, products.base_price) <= ?', [(int) $max * 100]))))
            ->withCardData($filters['colour'] ?? null)
            ->latest()
            ->get();

        return Inertia::render('Catalog/Category', [
            'category' => $category->only('name', 'slug'),
            'subcategories' => $subcategories->map->only('name', 'slug'),
            'products' => $products->map(fn (Product $product): array => $product->cardData()),
            'sizes' => $this->variantValues($categoryIds, 'size'),
            'colours' => $this->variantValues($categoryIds, 'colour'),
            'genders' => Product::visible()->whereIn('category_id', $categoryIds)->whereNotNull('gender')->exists() ? Gender::shopperOptions() : [],
            'filters' => $filters,
        ]);
    }

    /**
     * Distinct sizes or colours on sale in these categories, in the order the admin created them.
     *
     * @param  Collection<int, int>  $categoryIds
     * @return Collection<int, string>
     */
    private function variantValues(Collection $categoryIds, string $column): Collection
    {
        return ProductVariant::query()
            ->where('is_active', true)
            ->whereNotNull($column)
            ->whereHas('product', fn (Builder $product) => $product->visible()->whereIn('category_id', $categoryIds))
            ->groupBy($column)
            ->orderByRaw('min(id)')
            ->pluck($column);
    }
}
