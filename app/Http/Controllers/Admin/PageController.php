<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\PageRequest;
use App\Models\Page;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class PageController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('Admin/Pages/Index', [
            'pages' => Page::orderBy('title')->get(['id', 'slug', 'title', 'updated_at']),
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('Admin/Pages/Create');
    }

    public function store(PageRequest $request): RedirectResponse
    {
        Page::create($request->validated());

        return to_route('admin.pages.index')->with('status', 'Page created.');
    }

    public function edit(Page $page): Response
    {
        return Inertia::render('Admin/Pages/Edit', [
            'page' => $page->only('id', 'slug', 'title', 'body'),
        ]);
    }

    public function update(PageRequest $request, Page $page): RedirectResponse
    {
        $page->update($request->validated());

        return to_route('admin.pages.index')->with('status', 'Page saved.');
    }

    public function destroy(Page $page): RedirectResponse
    {
        $page->delete();

        return to_route('admin.pages.index')->with('status', 'Page deleted.');
    }
}
