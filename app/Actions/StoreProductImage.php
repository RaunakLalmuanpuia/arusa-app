<?php

namespace App\Actions;

use App\Models\Product;
use App\Models\ProductImage;
use GdImage;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class StoreProductImage
{
    /**
     * Resize the upload to every ProductImage width (never upscaling), save each as WebP, and attach it to the product.
     */
    public function handle(Product $product, UploadedFile $file): ProductImage
    {
        $source = @imagecreatefromstring($file->getContent());

        if ($source === false) {
            throw ValidationException::withMessages(['images' => "Could not read {$file->getClientOriginalName()}."]);
        }

        $source = $this->uprightJpeg($source, $file);
        $path = "products/{$product->id}/".Str::uuid();

        foreach (ProductImage::WIDTHS as $width) {
            $resized = imagescale($source, min($width, imagesx($source)));
            ob_start();
            imagewebp($resized, null, 80);
            Storage::disk('public')->put("{$path}-{$width}.webp", ob_get_clean());
        }

        return $product->images()->create([
            'path' => $path,
            'alt' => $product->name,
            'sort_order' => (int) $product->images()->max('sort_order') + 1,
        ]);
    }

    /**
     * Phone photos store rotation in EXIF instead of the pixels; GD ignores it.
     */
    private function uprightJpeg(GdImage $image, UploadedFile $file): GdImage
    {
        if ($file->getMimeType() !== 'image/jpeg') {
            return $image;
        }

        return match (@exif_read_data($file->getRealPath())['Orientation'] ?? 1) {
            3 => imagerotate($image, 180, 0),
            6 => imagerotate($image, -90, 0),
            8 => imagerotate($image, 90, 0),
            default => $image,
        };
    }
}
