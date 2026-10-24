<?php

namespace App\Models;

use Database\Factories\CategoryFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Storage;

#[Fillable(['parent_id', 'name', 'slug', 'image_path', 'sort_order', 'is_active'])]
class Category extends Model
{
    /** @use HasFactory<CategoryFactory> */
    use HasFactory;

    public const IMAGE_WIDTHS = [800, 1600];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }

    /**
     * @return BelongsTo<Category, $this>
     */
    public function parent(): BelongsTo
    {
        return $this->belongsTo(Category::class, 'parent_id');
    }

    /**
     * @return HasMany<Category, $this>
     */
    public function children(): HasMany
    {
        return $this->hasMany(Category::class, 'parent_id');
    }

    /**
     * @return HasMany<Product, $this>
     */
    public function products(): HasMany
    {
        return $this->hasMany(Product::class);
    }

    /**
     * @param  Builder<Category>  $query
     */
    #[Scope]
    protected function active(Builder $query): void
    {
        $query->where('is_active', true);
    }

    /**
     * @return array{src: string, srcset: string, alt: string}|null
     */
    public function imageData(): ?array
    {
        if ($this->image_path === null) {
            return null;
        }

        $disk = Storage::disk('public');

        return [
            'src' => $disk->url("{$this->image_path}-800.webp"),
            'srcset' => collect(self::IMAGE_WIDTHS)
                ->map(fn (int $width): string => $disk->url("{$this->image_path}-{$width}.webp")." {$width}w")
                ->implode(', '),
            'alt' => $this->name,
        ];
    }

    public static function deleteImageFiles(?string $path): void
    {
        if ($path !== null) {
            Storage::disk('public')->delete(array_map(fn (int $width): string => "{$path}-{$width}.webp", self::IMAGE_WIDTHS));
        }
    }
}
