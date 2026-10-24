<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\PagePreviewRequest;
use App\Models\Page;
use Illuminate\Http\JsonResponse;

class PagePreviewController extends Controller
{
    /**
     * Render unsaved blocks the way the storefront shows them, without saving anything.
     */
    public function __invoke(PagePreviewRequest $request): JsonResponse
    {
        return response()->json([
            'blocks' => (new Page(['blocks' => $request->validated('blocks')]))->renderedBlocks(),
        ]);
    }
}
