<?php

namespace App\Models;

use App\Enums\Gender;
use Database\Factories\ProductFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['category_id', 'gender', 'name', 'slug', 'description', 'base_price', 'is_active'])]
class Product extends Model
{
    /** @use HasFactory<ProductFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'base_price' => 'integer',
            'gender' => Gender::class,
            'is_active' => 'boolean',
        ];
    }

    /**
     * @return BelongsTo<Category, $this>
     */
    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    /**
     * Variants in creation order, which is the order the admin typed sizes and colours.
     *
     * @return HasMany<ProductVariant, $this>
     */
    public function variants(): HasMany
    {
        return $this->hasMany(ProductVariant::class)->orderBy('id')->chaperone();
    }

    /**
     * @return HasMany<ProductImage, $this>
     */
    public function images(): HasMany
    {
        return $this->hasMany(ProductImage::class)->orderBy('sort_order')->orderBy('id');
    }

    /**
     * Products a shopper may see: active, in an active category, with at least one active variant.
     *
     * @param  Builder<Product>  $query
     */
    #[Scope]
    protected function visible(Builder $query): void
    {
        $query->where('is_active', true)
            ->whereHas('category', fn (Builder $category) => $category->where('is_active', true)
                ->where(fn (Builder $q) => $q->whereNull('parent_id')->orWhereHas('parent', fn (Builder $p) => $p->where('is_active', true))))
            ->whereHas('variants', fn (Builder $variant) => $variant->where('is_active', true));
    }

    /**
     * Products for a shopper browsing this gender: its own, unisex, and ungendered ones (e.g. a mug).
     *
     * @param  Builder<Product>  $query
     */
    #[Scope]
    protected function forGender(Builder $query, Gender $gender): void
    {
        $query->where(fn (Builder $q) => $q->whereIn('gender', $gender->matching())->orWhereNull('gender'));
    }

    /**
     * Eager-load what cardData() needs: active variants and the first two images,
     * with the given colour's images first so a colour-filtered card shows that colour.
     *
     * @param  Builder<Product>  $query
     */
    #[Scope]
    protected function withCardData(Builder $query, ?string $colour = null): void
    {
        $query->with([
            'variants' => fn (HasMany $variants) => $variants->where('is_active', true),
            'images' => fn (HasMany $images) => $images
                ->when($colour, fn (Builder $images, string $colour) => $images->reorder()->orderByRaw('case when colour = ? then 0 else 1 end', [$colour])->orderBy('sort_order')->orderBy('id'))
                ->limit(2),
        ]);
    }

    /**
     * Listing card props. Requires the withCardData() scope.
     *
     * @return array{name: string, slug: string, price_from: int, has_price_range: bool, in_stock: bool, image: array{src: string, srcset: string, alt: string}|null, hover_image: array{src: string, srcset: string, alt: string}|null}
     */
    public function cardData(): array
    {
        $prices = $this->variants->map(fn (ProductVariant $variant): int => $variant->effectivePrice());

        return [
            'name' => $this->name,
            'slug' => $this->slug,
            'price_from' => (int) $prices->min(),
            'has_price_range' => $prices->unique()->count() > 1,
            'in_stock' => $this->variants->contains(fn (ProductVariant $variant): bool => $variant->stock > 0),
            'image' => $this->images->first()?->toImageData(),
            'hover_image' => $this->images->get(1)?->toImageData(),
        ];
    }
}
