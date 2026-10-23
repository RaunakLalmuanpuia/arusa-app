<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\UpdateSettingsRequest;
use App\Models\Setting;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class SettingController extends Controller
{
    public function edit(): Response
    {
        return Inertia::render('Admin/Settings/Edit', [
            'settings' => Setting::values(),
        ]);
    }

    public function update(UpdateSettingsRequest $request): RedirectResponse
    {
        $values = [
            ...$request->safe()->except('cod_enabled'),
            'cod_enabled' => $request->boolean('cod_enabled') ? '1' : '0',
        ];

        foreach ($values as $key => $value) {
            Setting::updateOrCreate(['key' => $key], ['value' => $value]);
        }

        return back()->with('status', 'Settings saved.');
    }
}
