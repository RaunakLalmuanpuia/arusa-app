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
        $gender = Gender::tryFrom($filters['gender'] ?? '');
        $isFiltered = collect($filters)->except('gender')->filter(fn ($value) => $value !== null)->isNotEmpty();

        // shortcut: no pagination (catalog < ~100 products); add ->paginate() when a category passes ~50.
        $products = Product::visible()
            ->whereIn('category_id', $categoryIds)
            ->when($gender, fn (Builder $query, Gender $gender) => $query->forGender($gender))
            ->when($isFiltered, fn (Builder $query) => $query->whereHas('variants', fn (Builder $variant) => $variant
                ->where('is_active', true)
                ->where('stock', '>', 0)
                ->when($filters['size'] ?? null, fn (Builder $q, string $size) => $q->where('size', $size))
                ->when($filters['colour'] ?? null, fn (Builder $q, string $colour) => $q->where('colour', $colour))
                ->when(isset($filters['min']), fn (Builder $q) => $q->whereRaw('coalesce(price_override, products.base_price) >= ?', [(int) $filters['min'] * 100]))
                ->when(isset($filters['max']), fn (Builder $q) => $q->whereRaw('coalesce(price_override, products.base_price) <= ?', [(int) $filters['max'] * 100]))))
            ->withCardData($filters['colour'] ?? null)
            ->latest()
            ->get();

        return Inertia::render('Catalog/Category', [
            'category' => $category->only('name', 'slug'),
            'subcategories' => $subcategories->map->only('name', 'slug'),
            'products' => $products->map(fn (Product $product): array => $product->cardData()),
            'sizes' => $this->variantValues($categoryIds, $gender, 'size', $filters['size'] ?? null),
            'colours' => $this->variantValues($categoryIds, $gender, 'colour', $filters['colour'] ?? null),
            'genders' => Product::visible()->whereIn('category_id', $categoryIds)->whereNotNull('gender')->exists() ? Gender::shopperOptions() : [],
            'filters' => $filters,
        ]);
    }

    /**
     * Distinct in-stock sizes or colours in these categories for the picked gender, in the order the admin created them.
     * The picked value is always kept so its chip can still be tapped off after a gender switch.
     *
     * @param  Collection<int, int>  $categoryIds
     * @return Collection<int, string>
     */
    private function variantValues(Collection $categoryIds, ?Gender $gender, string $column, ?string $picked): Collection
    {
        $values = ProductVariant::query()
            ->where('is_active', true)
            ->where('stock', '>', 0)
            ->whereNotNull($column)
            ->whereHas('product', fn (Builder $product) => $product->visible()->whereIn('category_id', $categoryIds)
                ->when($gender, fn (Builder $product, Gender $gender) => $product->forGender($gender)))
            ->groupBy($column)
            ->orderByRaw('min(id)')
            ->pluck($column);

        return $picked === null || $values->contains($picked) ? $values : $values->push($picked);
    }
}
