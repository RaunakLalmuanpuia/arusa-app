<?php

namespace Database\Seeders;

use App\Actions\StoreWebpImage;
use App\Models\Category;
use Illuminate\Database\Seeder;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Str;

class CategoryImageSeeder extends Seeder
{
    /**
     * Bundled photo for each category, keyed by category slug: a tile photo for the top level, a product photo for subcategories.
     *
     * @var array<string, string>
     */
    private const PHOTOS = [
        'apparel' => 'category-apparel',
        'goods' => 'category-goods',
        't-shirts' => 'wolf-badge-tee-black',
        'hoodies' => 'essential-pullover-hoodie-black',
        'sweatshirts' => 'crewneck-sweatshirt-grey',
        'headwear' => 'knit-beanie-white',
    ];

    /**
     * Give existing categories an image; safe to re-run, categories that already have one are left alone.
     */
    public function run(StoreWebpImage $storeWebpImage): void
    {
        $categories = Category::whereIn('slug', array_keys(self::PHOTOS))->whereNull('image_path')->get();

        foreach ($categories as $category) {
            $file = database_path('seeders/images/'.self::PHOTOS[$category->slug].'.jpg');

            if (! is_file($file)) {
                continue;
            }

            $path = 'categories/'.Str::uuid();
            $storeWebpImage->handle(new UploadedFile($file, basename($file), test: true), $path, Category::IMAGE_WIDTHS, 'image');
            $category->update(['image_path' => $path]);
        }
    }
}
