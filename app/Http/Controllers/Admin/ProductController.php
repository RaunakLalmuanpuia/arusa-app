<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\ProductRequest;
use App\Models\Category;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\ProductImage;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Inertia\Inertia;
use Inertia\Response;

class ProductController extends Controller
{
    public function index(Request $request): Response
    {
        $filters = [
            'search' => $request->string('search')->trim()->value(),
            'category_id' => $request->integer('category_id') ?: null,
        ];

        return Inertia::render('Admin/Products/Index', [
            'products' => Product::query()
                ->with('category:id,name')
                ->withCount('variants')
                ->withSum('variants', 'stock')
                ->when($filters['search'], fn ($query, string $search) => $query->whereLike('name', "%{$search}%"))
                ->when($filters['category_id'], fn ($query, int $categoryId) => $query->where('category_id', $categoryId))
                ->latest()
                ->paginate(25)
                ->withQueryString()
                ->through(fn (Product $product): array => [
                    'id' => $product->id,
                    'name' => $product->name,
                    'category' => $product->category->name,
                    'base_price' => $product->base_price,
                    'variants_count' => $product->variants_count,
                    'stock' => (int) $product->variants_sum_stock,
                    'is_active' => $product->is_active,
                ]),
            'filters' => $filters,
            'categories' => $this->categoryOptions(),
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('Admin/Products/Create', [
            'categories' => $this->categoryOptions(),
        ]);
    }

    public function store(ProductRequest $request): RedirectResponse
    {
        $product = Product::create($request->validated());

        return to_route('admin.products.edit', $product)->with('status', 'Product created. Add variants and images below.');
    }

    public function edit(Product $product): Response
    {
        return Inertia::render('Admin/Products/Edit', [
            'product' => $product->only('id', 'name', 'slug', 'category_id', 'description', 'base_price', 'is_active'),
            'variants' => $product->variants->map->only('id', 'size', 'colour', 'sku', 'price_override', 'stock', 'is_active'),
            'categories' => $this->categoryOptions(),
            'images' => $product->images->map(fn (ProductImage $image): array => [
                'id' => $image->id,
                'url' => $image->url(400),
                'alt' => $image->alt,
                'colour' => $image->colour,
                'delete_url' => route('admin.products.images.destroy', [$product, $image]),
            ]),
            'colours' => $product->variants->pluck('colour')->filter()->unique()->values(),
        ]);
    }

    public function update(ProductRequest $request, Product $product): RedirectResponse
    {
        $product->update($request->validated());

        return to_route('admin.products.edit', $product)->with('status', 'Product saved.');
    }

    public function destroy(Product $product): RedirectResponse
    {
        if (OrderItem::whereIn('product_variant_id', $product->variants()->select('id'))->exists()) {
            return back()->withErrors(['product' => "\"{$product->name}\" has orders. Untick \"Visible on the store\" to hide it instead."]);
        }

        $images = $product->images;
        $product->delete();
        $images->each(fn (ProductImage $image) => $image->deleteFiles());

        return to_route('admin.products.index')->with('status', 'Product deleted.');
    }

    /**
     * @return Collection<int, array{id: int, label: string}>
     */
    private function categoryOptions(): Collection
    {
        return Category::with('parent:id,name')
            ->orderBy('name')
            ->get()
            ->map(fn (Category $category): array => [
                'id' => $category->id,
                'label' => $category->parent ? "{$category->parent->name} › {$category->name}" : $category->name,
            ])
            ->sortBy('label')
            ->values();
    }
}
