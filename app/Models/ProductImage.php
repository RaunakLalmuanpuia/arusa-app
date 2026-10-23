<?php

namespace App\Models;

use Database\Factories\ProductImageFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

#[Fillable(['colour', 'path', 'alt', 'sort_order'])]
class ProductImage extends Model
{
    /** @use HasFactory<ProductImageFactory> */
    use HasFactory;

    // shortcut: srcset assumes uploads are ≥ 1200 px wide; store real widths if small uploads become common.
    /**
     * Widths each upload is resized to. Files are stored as "{path}-{width}.webp".
     *
     * @var list<int>
     */
    public const WIDTHS = [400, 800, 1200];

    /**
     * @return BelongsTo<Product, $this>
     */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function url(int $width = 800): string
    {
        return Storage::disk('public')->url("{$this->path}-{$width}.webp");
    }

    public function srcset(): string
    {
        return collect(self::WIDTHS)
            ->map(fn (int $width): string => "{$this->url($width)} {$width}w")
            ->implode(', ');
    }

    /**
     * @return array{src: string, srcset: string, alt: string}
     */
    public function toImageData(): array
    {
        return ['src' => $this->url(), 'srcset' => $this->srcset(), 'alt' => $this->alt];
    }

    public function deleteFiles(): void
    {
        Storage::disk('public')->delete(
            array_map(fn (int $width): string => "{$this->path}-{$width}.webp", self::WIDTHS),
        );
    }
}
