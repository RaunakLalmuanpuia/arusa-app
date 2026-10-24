<?php

namespace App\Http\Middleware;

use App\Enums\Gender;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cookie;
use Symfony\Component\HttpFoundation\Response;

class RememberGender
{
    /**
     * Remember the shopper's Men / Women pick across visits: an explicit `gender` query
     * (empty for "All") updates the cookie, and its absence falls back to the cookie.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        if (! $request->query->has('gender')) {
            $request->merge(['gender' => Gender::tryFrom((string) $request->cookie('gender'))?->value]);
        } elseif ($gender = Gender::tryFrom((string) $request->query('gender'))) {
            Cookie::queue('gender', $gender->value, 60 * 24 * 365);
        } elseif (blank($request->query('gender'))) {
            Cookie::expire('gender');
        }

        return $next($request);
    }
}
