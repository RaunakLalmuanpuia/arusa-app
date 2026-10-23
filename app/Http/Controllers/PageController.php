<?php

namespace App\Http\Controllers;

use App\Models\Page;
use Inertia\Inertia;
use Inertia\Response;

class PageController extends Controller
{
    public function show(Page $page): Response
    {
        return Inertia::render('Content/Show', [
            'title' => $page->title,
            'html' => $page->renderedBody(),
        ]);
    }
}
