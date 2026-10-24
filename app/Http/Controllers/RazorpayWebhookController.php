<?php

namespace App\Http\Controllers;

use App\Actions\MarkOrderPaid;
use App\Enums\PaymentStatus;
use App\Models\Payment;
use App\Services\Razorpay;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Validation\ValidationException;
use UnexpectedValueException;

class RazorpayWebhookController extends Controller
{
    /**
     * Razorpay posts every subscribed event here. This is the source of truth for payments:
     * signature-checked, idempotent, and answered 2xx once verified so Razorpay stops retrying.
     * The exception is a late or duplicate payment whose refund fails: that is answered 503,
     * so Razorpay redelivers the event and the refund is tried again.
     */
    public function __invoke(Request $request, Razorpay $razorpay, MarkOrderPaid $markOrderPaid): Response
    {
        abort_unless(
            $razorpay->isValidWebhookSignature($request->getContent(), (string) $request->header('X-Razorpay-Signature')),
            400,
            'Invalid signature.',
        );

        $entity = $request->input('payload.payment.entity');

        if (! is_array($entity) || ! isset($entity['id'], $entity['order_id'], $entity['amount'])) {
            return response()->noContent();
        }

        $payment = Payment::with('order')->where('gateway_order_id', $entity['order_id'])->oldest('id')->first();

        if (! $payment) {
            return response()->noContent();
        }

        try {
            match ($request->input('event')) {
                'payment.captured' => $markOrderPaid->handle($payment->order, $entity['order_id'], $entity['id'], (int) $entity['amount'], $entity),
                'payment.failed' => $this->recordFailedAttempt($payment, $entity),
                default => null,
            };
        } catch (UnexpectedValueException $exception) {
            report($exception);
        } catch (ValidationException) {
            // RefundPayment already reported the Razorpay error; a 5xx (not the default 302) makes Razorpay redeliver.
            return response()->noContent(503);
        }

        return response()->noContent();
    }

    /**
     * One row per failed attempt. The order keeps waiting: the shopper may try again until the sweep.
     *
     * @param  array{id: string, order_id: string, amount: int|string}  $entity
     */
    private function recordFailedAttempt(Payment $payment, array $entity): void
    {
        Payment::firstOrCreate(['gateway_payment_id' => $entity['id']], [
            'order_id' => $payment->order_id,
            'gateway' => 'razorpay',
            'gateway_order_id' => $entity['order_id'],
            'amount' => (int) $entity['amount'],
            'status' => PaymentStatus::Failed,
            'payload' => ['payment' => $entity],
        ]);
    }
}
