<?php

use App\Actions\CancelOrder;
use App\Enums\PaymentStatus;
use App\Models\Order;
use App\Models\User;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Artisan::command('admin:grant {email}', function (string $email): int {
    $user = User::where('email', $email)->first();

    if (! $user) {
        $this->error("No user with email {$email}.");

        return 1;
    }

    $user->forceFill(['is_admin' => true])->save();
    $this->info("{$email} is now an admin.");

    return 0;
})->purpose('Grant admin access to an existing user');

Artisan::command('orders:cancel-abandoned', function (CancelOrder $cancelOrder): int {
    $orders = Order::where('payment_status', PaymentStatus::AwaitingPayment)
        ->where('placed_at', '<', now()->subMinutes(30))
        ->get();

    foreach ($orders as $order) {
        $cancelOrder->handle($order, onlyIfAwaitingPayment: true);
    }

    $this->info("Checked {$orders->count()} unpaid orders.");

    return 0;
})->purpose('Cancel online orders left unpaid for 30 minutes and put their stock back');

Schedule::command('orders:cancel-abandoned')->everyFiveMinutes()->withoutOverlapping();
