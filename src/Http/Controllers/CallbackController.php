<?php

namespace Unipay\BD\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Unipay\BD\Events\PaymentFailed;
use Unipay\BD\Events\PaymentSucceeded;
use Unipay\BD\Facades\Payment;
use Unipay\BD\Models\Transaction;

class CallbackController extends Controller
{
    public function handle(Request $request, string $gateway)
    {
        $paymentId = $request->input('paymentID') ?? $request->input('paymentRefId');
        $status = $request->input('status');

        if ($gateway === 'bkash') {
            if ($status === 'cancel' || $status === 'failure') {
                $response = Payment::driver('bkash')->queryPayment($paymentId ?? '');
                $transaction = Transaction::where('payment_id', $paymentId)->first();
                event(new PaymentFailed($response, $transaction));

                return response()->json([
                    'status' => 'failed',
                    'message' => 'Payment was cancelled or failed.',
                    'response' => $response->toArray(),
                ]);
            }

            // Execute bKash Payment
            $response = Payment::driver('bkash')->executePayment($paymentId ?? '');
        } elseif ($gateway === 'nagad') {
            $paymentRefId = $request->input('payment_ref_id') ?? $paymentId;
            $response = Payment::driver('nagad')->queryPayment($paymentRefId ?? '');
        } else {
            $response = Payment::driver($gateway)->queryPayment($paymentId ?? '');
        }

        $transaction = null;
        if (config('unipay.logging.enabled', true)) {
            try {
                $transaction = Transaction::where('payment_id', $response->paymentId)
                    ->orWhere('invoice_id', $response->invoiceId)
                    ->first();
            } catch (\Throwable $e) {
                // Ignore DB error if DB driver is not configured
            }
        }

        if ($response->isSuccessful()) {
            event(new PaymentSucceeded($response, $transaction));

            return response()->json([
                'status' => 'success',
                'message' => 'Payment completed successfully.',
                'data' => $response->toArray(),
            ]);
        }

        event(new PaymentFailed($response, $transaction));

        return response()->json([
            'status' => 'failed',
            'message' => 'Payment verification failed.',
            'data' => $response->toArray(),
        ], 400);
    }
}
