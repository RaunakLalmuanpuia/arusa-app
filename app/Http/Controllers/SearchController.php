<?php

namespace App\Http\Controllers;

use App\Enums\Gender;
use App\Models\Product;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class SearchController extends Controller
{
    public function __invoke(Request $request): Response
    {
        $query = $request->string('q')->trim()->limit(100, '')->value();
        $gender = $request->enum('gender', Gender::class);
        preg_match_all('/[\p{L}\p{N}]+/u', $query, $words);
        // Every word must match the start of a stemmed word, so "hood" finds "Hoodie" and "hoodies" still does.
        $prefixQuery = implode(' & ', array_map(fn (string $word): string => $word.':*', $words[0]));

        $products = $prefixQuery === ''
            ? collect()
            : Product::visible()
                ->whereRaw("(to_tsvector('english', name) || to_tsvector('english', description)) @@ to_tsquery('english', ?)", [$prefixQuery])
                ->when($gender, fn ($products, Gender $gender) => $products->forGender($gender))
                ->withCardData()
                ->latest()
                ->limit(48)
                ->get();

        return Inertia::render('Catalog/Search', [
            'query' => $query,
            'gender' => $gender?->value,
            'genders' => Gender::shopperOptions(),
            'products' => $products->map(fn (Product $product): array => $product->cardData()),
        ]);
    }
}
