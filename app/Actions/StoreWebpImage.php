<?php

namespace App\Actions;

use GdImage;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

class StoreWebpImage
{
    /**
     * Resize the upload to each width (never upscaling) and save each as "{$path}-{$width}.webp" on the public disk.
     *
     * @param  list<int>  $widths
     *
     * @throws ValidationException keyed by $errorKey when the file cannot be read or processed.
     */
    public function handle(UploadedFile $file, string $path, array $widths, string $errorKey): void
    {
        // A 6000x6000 decode is ~144 MB, plus the rotated and scaled copies.
        ini_set('memory_limit', '512M');

        $source = @imagecreatefromstring($file->getContent());

        if ($source === false) {
            throw ValidationException::withMessages([$errorKey => "Could not read {$file->getClientOriginalName()}."]);
        }

        $source = $this->uprightJpeg($source, $file);

        if ($source === false) {
            throw $this->processingFailed($file, $errorKey);
        }

        $written = [];

        foreach ($widths as $width) {
            $resized = imagescale($source, min($width, imagesx($source)));
            ob_start();
            $encoded = $resized !== false && imagewebp($resized, null, 80);
            $webp = ob_get_clean();

            if (! $encoded) {
                Storage::disk('public')->delete($written);

                throw $this->processingFailed($file, $errorKey);
            }

            Storage::disk('public')->put($written[] = "{$path}-{$width}.webp", $webp);
        }
    }

    private function processingFailed(UploadedFile $file, string $errorKey): ValidationException
    {
        return ValidationException::withMessages([$errorKey => "Could not process {$file->getClientOriginalName()}."]);
    }

    /**
     * Phone photos store rotation in EXIF instead of the pixels; GD ignores it.
     */
    private function uprightJpeg(GdImage $image, UploadedFile $file): GdImage|false
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
