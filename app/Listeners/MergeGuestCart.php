<?php

namespace App\Listeners;

use App\Models\User;
use App\Services\Cart;
use Illuminate\Auth\Events\Login;

class MergeGuestCart
{
    public function __construct(private Cart $cart) {}

    /**
     * Keep what a shopper added before signing in.
     */
    public function handle(Login $event): void
    {
        if ($event->user instanceof User) {
            $this->cart->mergeSessionInto($event->user);
        }
    }
}
