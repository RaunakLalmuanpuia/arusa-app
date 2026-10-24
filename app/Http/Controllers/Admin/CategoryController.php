<?php

namespace App\Http\Controllers\Admin;

use App\Actions\StoreWebpImage;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\CategoryRequest;
use App\Models\Category;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

class CategoryController extends Controller
{
    public function index(Request $request): Response
    {
        $search = $request->string('search')->trim()->value();

        return Inertia::render('Admin/Categories/Index', [
            'categories' => Category::query()
                ->with('parent:id,name')
                ->withCount('products')
                ->when($search, fn ($query) => $query->whereLike('name', "%{$search}%"))
                ->orderBy('sort_order')
                ->orderBy('name')
                ->paginate(25)
                ->withQueryString()
                ->through(fn (Category $category): array => [
                    'id' => $category->id,
                    'name' => $category->name,
                    'slug' => $category->slug,
                    'parent' => $category->parent?->name,
                    'sort_order' => $category->sort_order,
                    'is_active' => $category->is_active,
                    'products_count' => $category->products_count,
                ]),
            'filters' => ['search' => $search],
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('Admin/Categories/Create', [
            'parents' => $this->parentOptions(),
        ]);
    }

    public function store(CategoryRequest $request, StoreWebpImage $storeWebpImage): RedirectResponse
    {
        $category = Category::create($request->safe()->except('image'));
        $this->storeImage($request, $category, $storeWebpImage);

        return to_route('admin.categories.index')->with('status', 'Category created.');
    }

    public function edit(Category $category): Response
    {
        return Inertia::render('Admin/Categories/Edit', [
            'category' => $category->only('id', 'name', 'slug', 'parent_id', 'sort_order', 'is_active'),
            'imageUrl' => $category->imageData()['src'] ?? null,
            'parents' => $this->parentOptions($category),
        ]);
    }

    public function update(CategoryRequest $request, Category $category, StoreWebpImage $storeWebpImage): RedirectResponse
    {
        $category->update($request->safe()->except('image'));
        $this->storeImage($request, $category, $storeWebpImage);

        return to_route('admin.categories.index')->with('status', 'Category saved.');
    }

    public function destroy(Category $category): RedirectResponse
    {
        if ($category->products()->exists() || $category->children()->exists()) {
            return back()->withErrors(['category' => "Move the products and subcategories out of \"{$category->name}\" first."]);
        }

        $category->delete();
        Category::deleteImageFiles($category->image_path);

        return to_route('admin.categories.index')->with('status', 'Category deleted.');
    }

    /**
     * Save an uploaded image as WebP and remove the one it replaces.
     */
    private function storeImage(CategoryRequest $request, Category $category, StoreWebpImage $storeWebpImage): void
    {
        if (! $request->hasFile('image')) {
            return;
        }

        $previousPath = $category->image_path;
        $path = 'categories/'.Str::uuid();

        $storeWebpImage->handle($request->file('image'), $path, Category::IMAGE_WIDTHS, 'image');
        $category->update(['image_path' => $path]);
        Category::deleteImageFiles($previousPath);
    }

    /**
     * Top-level categories that may be chosen as a parent.
     *
     * @return Collection<int, Category>
     */
    private function parentOptions(?Category $except = null): Collection
    {
        return Category::whereNull('parent_id')
            ->when($except, fn ($query) => $query->whereKeyNot($except->id))
            ->orderBy('name')
            ->get(['id', 'name']);
    }
}
