<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\ShippingZoneRequest;
use App\Models\ShippingZone;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class ShippingZoneController extends Controller
{
    public function index(Request $request): Response
    {
        $search = $request->string('search')->trim()->value();

        return Inertia::render('Admin/ShippingZones/Index', [
            'zones' => ShippingZone::query()
                ->withCount('pincodes')
                ->when($search, fn ($query) => $query
                    ->whereLike('name', "%{$search}%")
                    ->orWhereHas('pincodes', fn ($pincodes) => $pincodes->where('pincode', $search)))
                ->orderBy('name')
                ->paginate(25)
                ->withQueryString()
                ->through(fn (ShippingZone $zone): array => [
                    'id' => $zone->id,
                    'name' => $zone->name,
                    'rate' => $zone->rate,
                    'free_above' => $zone->free_above,
                    'cod_allowed' => $zone->cod_allowed,
                    'pincodes_count' => $zone->pincodes_count,
                    'is_active' => $zone->is_active,
                ]),
            'filters' => ['search' => $search],
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('Admin/ShippingZones/Create');
    }

    public function store(ShippingZoneRequest $request): RedirectResponse
    {
        DB::transaction(function () use ($request): void {
            $zone = ShippingZone::create($request->safe()->except('pincodes'));
            $this->savePincodes($zone, $request->validated('pincodes'));
        });

        return to_route('admin.shipping-zones.index')->with('status', 'Zone created.');
    }

    public function edit(ShippingZone $shippingZone): Response
    {
        return Inertia::render('Admin/ShippingZones/Edit', [
            'zone' => [
                ...$shippingZone->only('id', 'name', 'rate', 'free_above', 'cod_allowed', 'cod_max', 'is_active'),
                'pincodes' => $shippingZone->pincodes->pluck('pincode')->implode("\n"),
            ],
        ]);
    }

    public function update(ShippingZoneRequest $request, ShippingZone $shippingZone): RedirectResponse
    {
        DB::transaction(function () use ($request, $shippingZone): void {
            $shippingZone->update($request->safe()->except('pincodes'));
            $shippingZone->pincodes()->delete();
            $this->savePincodes($shippingZone, $request->validated('pincodes'));
        });

        return to_route('admin.shipping-zones.index')->with('status', 'Zone saved.');
    }

    public function destroy(ShippingZone $shippingZone): RedirectResponse
    {
        if ($shippingZone->orders()->exists()) {
            return back()->withErrors(['zone' => "\"{$shippingZone->name}\" has orders. Untick Active to stop using it instead."]);
        }

        $shippingZone->delete();

        return to_route('admin.shipping-zones.index')->with('status', 'Zone deleted.');
    }

    /**
     * @param  list<string>  $pincodes
     */
    private function savePincodes(ShippingZone $zone, array $pincodes): void
    {
        $zone->pincodes()->createMany(array_map(fn (string $pincode): array => ['pincode' => $pincode], $pincodes));
    }
}
