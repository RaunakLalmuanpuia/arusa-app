<?php

namespace App\Http\Controllers;

use App\Models\Product;
use Inertia\Inertia;
use Inertia\Response;

class HomeController extends Controller
{
    public function __invoke(): Response
    {
        return Inertia::render('Home', [
            'products' => Product::visible()->withCardData()->latest()->limit(8)->get()
                ->map(fn (Product $product): array => $product->cardData()),
        ]);
    }
}
