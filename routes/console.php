<?php

use App\Actions\CancelOrder;
use App\Enums\FulfilmentStatus;
use App\Enums\PaymentMethod;
use App\Models\Order;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
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
    $orders = Order::where('fulfilment_status', FulfilmentStatus::Pending)
        ->where(fn (Builder $query) => $query
            ->where(fn (Builder $query) => $query->where('payment_method', PaymentMethod::Razorpay)->where('placed_at', '<', now()->subMinutes(30)))
            ->orWhere(fn (Builder $query) => $query->where('payment_method', PaymentMethod::Cod)->where('placed_at', '<', now()->subDay())))
        ->get();

    foreach ($orders as $order) {
        try {
            $cancelOrder->handle($order, onlyIfPending: true);
        } catch (Throwable $exception) {
            report($exception);
        }
    }

    $this->info("Checked {$orders->count()} pending orders.");

    return 0;
})->purpose('Cancel online orders unpaid after 30 minutes and COD orders unconfirmed after 24 hours, and put their stock back');

Schedule::command('orders:cancel-abandoned')->everyFiveMinutes()->withoutOverlapping();
