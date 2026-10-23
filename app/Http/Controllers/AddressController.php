<?php

namespace App\Http\Controllers;

use App\Models\Address;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class AddressController extends Controller
{
    public function destroy(Request $request, Address $address): RedirectResponse
    {
        abort_unless($address->user_id === $request->user()->id, 404);

        $address->delete();

        return back()->with('status', 'Address removed.');
    }
}
