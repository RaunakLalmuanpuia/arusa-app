<?php

namespace App\Http\Controllers;

use App\Models\Product;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class SearchController extends Controller
{
    public function __invoke(Request $request): Response
    {
        $query = $request->string('q')->trim()->limit(100, '')->value();

        $products = $query === ''
            ? collect()
            : Product::visible()
                ->whereFullText(['name', 'description'], $query, ['mode' => 'websearch'])
                ->withCardData()
                ->latest()
                ->limit(48)
                ->get();

        return Inertia::render('Catalog/Search', [
            'query' => $query,
            'products' => $products->map(fn (Product $product): array => $product->cardData()),
        ]);
    }
}
