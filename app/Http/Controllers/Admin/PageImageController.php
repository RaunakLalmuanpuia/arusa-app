<?php

namespace App\Http\Controllers\Admin;

use App\Actions\StoreWebpImage;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StorePageImageRequest;
use App\Models\Page;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Str;

class PageImageController extends Controller
{
    /**
     * Store an image for a page block and return the path the block saves.
     */
    public function store(StorePageImageRequest $request, StoreWebpImage $storeWebpImage): JsonResponse
    {
        // shortcut: images of removed blocks stay on disk; add a cleanup command if storage grows.
        $path = 'pages/'.Str::uuid();

        $storeWebpImage->handle($request->file('image'), $path, Page::IMAGE_WIDTHS, 'image');

        return response()->json(['path' => $path, 'url' => Page::imageUrl($path)]);
    }
}
