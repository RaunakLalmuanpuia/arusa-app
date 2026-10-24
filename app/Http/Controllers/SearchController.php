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

        $products = $query === ''
            ? collect()
            : Product::visible()
                ->whereFullText(['name', 'description'], $query, ['mode' => 'websearch'])
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
