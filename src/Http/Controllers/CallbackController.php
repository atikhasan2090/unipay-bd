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
        $paymentId = $request->input('paymentID')
            ?? $request->input('paymentRefId')
            ?? $request->input('payment_id')
            ?? $request->input('val_id')
            ?? $request->input('order_id')
            ?? $request->input('cellfin_ref_id')
            ?? $request->input('trx_id');

        $status = strtolower($request->input('status') ?? $request->input('tran_status') ?? '');

        if ($gateway === 'bkash') {
            if ($status === 'cancel' || $status === 'failure') {
                $response = Payment::driver('bkash')->queryPayment($paymentId ?? '');
                $transaction = $this->findTransaction($response->paymentId, $response->invoiceId);
                event(new PaymentFailed($response, $transaction));

                return response()->json([
                    'status' => 'failed',
                    'message' => 'Payment was cancelled or failed.',
                    'response' => $response->toArray(),
                ]);
            }

            $response = Payment::driver('bkash')->executePayment($paymentId ?? '');
        } elseif ($gateway === 'sslcommerz') {
            $valId = $request->input('val_id') ?? $paymentId;
            $response = Payment::driver('sslcommerz')->queryPayment($valId ?? '');
        } else {
            $response = Payment::driver($gateway)->queryPayment($paymentId ?? '');
        }

        $transaction = $this->findTransaction($response->paymentId, $response->invoiceId);

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

    private function findTransaction(?string $paymentId, ?string $invoiceId): ?Transaction
    {
        if (!config('unipay.logging.enabled', true)) {
            return null;
        }

        try {
            return Transaction::where('payment_id', $paymentId)
                ->orWhere('invoice_id', $invoiceId)
                ->first();
        } catch (\Throwable $e) {
            return null;
        }
    }
}
