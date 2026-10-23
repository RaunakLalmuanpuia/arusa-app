<?php

namespace App\Http\Middleware;

use App\Models\Category;
use App\Models\Setting;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    /**
     * The root template that is loaded on the first page visit.
     *
     * @var string
     */
    protected $rootView = 'app';

    /**
     * Determine the current asset version.
     */
    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    /**
     * Define the props that are shared by default.
     *
     * @return array<string, mixed>
     */
    public function share(Request $request): array
    {
        return [
            ...parent::share($request),
            'auth' => [
                'user' => $request->user(),
            ],
            'store' => fn (): array => Arr::only(Setting::values(), [
                'store_name', 'contact_phone', 'whatsapp_number', 'contact_email',
            ]),
            'categories' => fn (): array => Category::active()
                ->whereNull('parent_id')
                ->orderBy('sort_order')
                ->orderBy('name')
                ->get(['name', 'slug'])
                ->toArray(),
            'flash' => [
                'status' => fn (): ?string => $request->session()->get('status'),
            ],
        ];
    }
}
