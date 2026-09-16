# UniPay BD Workflow & Integration Guide

This document details the complete end-to-end operational workflows, sequence diagrams, event lifecycles, and developer integration examples for **UniPay BD**.

---

## 1. High-Level Payment Workflow

UniPay acts as a unified abstraction layer between your Laravel application and Bangladeshi payment gateways (bKash, Nagad).

```
+------------------+         +------------------+         +------------------+         +------------------+
|   Merchant App   |         |    UniPay BD     |         | Payment Gateway  |         | Customer Browser |
+--------+---------+         +--------+---------+         +--------+---------+         +--------+---------+
         |                            |                            |                            |
         | 1. Payment::create(...)    |                            |                            |
         +--------------------------->|                            |                            |
         |                            | 2. API Init / Token        |                            |
         |                            +--------------------------->|                            |
         |                            |                            |                            |
         |                            | 3. Raw Response            |                            |
         |                            |<---------------------------+                            |
         |                            |                            |                            |
         | 4. PaymentResponse DTO     |                            |                            |
         |<---------------------------+                            |                            |
         |                            |                            |                            |
         | 5. Redirect to Gateway URL |                            |                            |
         +------------------------------------------------------------------------------------->|
         |                            |                            |                            |
         |                            |                            | 6. PIN / OTP Checkout      |
         |                            |                            |<---------------------------+
         |                            |                            |                            |
         |                            | 7. Callback Redirect / IPN |                            |
         |                            |<---------------------------+                            |
         |                            |                            |                            |
         | 8. Fire PaymentSucceeded   |                            |                            |
         |<---------------------------+                            |                            |
         |                            |                            |                            |
```

---

## 2. Gateway-Specific Workflows

### A. bKash Tokenized Checkout Workflow

```
Merchant App                   UniPay (BkashGateway)              bKash PGW API
     |                                   |                              |
     |--- 1. Payment::driver('bkash')--->|                              |
     |    ->createPayment($request)      |                              |
     |                                   |--- 2. Check Token Cache ---->|
     |                                   |    (Grant token if expired)  |
     |                                   |<-- 3. Return Bearer Token ---|
     |                                   |                              |
     |                                   |--- 4. POST /create --------->|
     |                                   |    (amount, invoiceId, etc)  |
     |                                   |<-- 5. {paymentID, bkashURL}--|
     |                                   |                              |
     |<-- 6. PaymentResponse ------------|                              |
     |    (redirectUrl: bkashURL)        |                              |
     |                                                                  |
     |=========== Customer Redirected to bkashURL ======================|
     |                                                                  |
     |-- 7. bKash Redirects to /unipay/callback/bkash?paymentID=... --->|
     |                                   |                              |
     |                                   |--- 8. POST /execute -------->|
     |                                   |    (paymentID)               |
     |                                   |<-- 9. {trxID, statusCode} ---|
     |                                   |                              |
     |<-- 10. Fire PaymentSucceeded -----|                              |
```

**Key Steps in bKash Workflow**:
1. **Token Management**: Checks Laravel Cache for `bkash_token`. If missing or expired, requests `/tokenized/checkout/token/grant`.
2. **Create Payment**: Requests payment session, receives `paymentID` and `bkashURL`.
3. **Execution**: Upon customer completion, bKash redirects to the UniPay callback route. UniPay executes POST `/tokenized/checkout/execute` to finalize funds transfer and retrieve `trxID`.

---

### B. Nagad RSA Encrypted Workflow

```
Merchant App                   UniPay (NagadGateway)              Nagad PGW API
     |                                   |                              |
     |--- 1. Payment::driver('nagad')--->|                              |
     |    ->createPayment($request)      |                              |
     |                                   |--- 2. Sign & Encrypt --------|
     |                                   |    RSA SHA256 Signature      |
     |                                   |                              |
     |                                   |--- 3. POST /initialize ------>|
     |                                   |<-- 4. {paymentRefId, challenge}|
     |                                   |                              |
     |                                   |--- 5. Decrypt Challenge & --->|
     |                                   |    Encrypt Complete Payload  |
     |                                   |                              |
     |                                   |--- 6. POST /complete -------->|
     |                                   |<-- 7. {callBackUrl} ---------|
     |                                   |                              |
     |<-- 8. PaymentResponse ------------|                              |
     |    (redirectUrl: callBackUrl)     |                              |
     |                                                                  |
     |=========== Customer Redirected to Nagad Portal ==================|
     |                                                                  |
     |-- 9. Nagad Callback Redirect to /unipay/callback/nagad --------->|
     |                                   |                              |
     |                                   |--- 10. GET /verify/payment ->|
     |                                   |<-- 11. {status: COMPLETED}---|
     |                                   |                              |
     |<-- 12. Fire PaymentSucceeded -----|                              |
```

**Key Steps in Nagad Workflow**:
1. **Initialize Payment**: Signs merchant payload using Private Key (`openssl_sign`) and sends initialize request to Nagad.
2. **Challenge Resolution**: Decrypts Nagad's challenge using Private Key (`openssl_private_decrypt`) and encrypts customer/order payload using Nagad Public Key (`openssl_public_encrypt`).
3. **Completion & Verification**: Submits completion request, gets checkout URL, and verifies status via GET `/verify/payment/{paymentRefId}` upon callback.

---

## 3. Webhook & Callback Engine Workflow

UniPay provides a unified callback controller registered under `/unipay/callback/{gateway}`.

```
                              +--------------------------+
                              | Incoming Callback Route  |
                              | /unipay/callback/{driver}|
                              +------------+-------------+
                                           |
                                           v
                              +--------------------------+
                              | Resolve Gateway Driver   |
                              +------------+-------------+
                                           |
                                           v
                              +--------------------------+
                              | Verify & Execute Payment |
                              +------------+-------------+
                                           |
                   +-----------------------+-----------------------+
                   | (Status == SUCCESS)                           | (Status == FAILED)
                   v                                               v
     +--------------------------+                    +--------------------------+
     | Fire `PaymentSucceeded`  |                    |   Fire `PaymentFailed`   |
     +-------------+------------+                    +-------------+------------+
                   |                                               |
                   v                                               v
     +--------------------------+                    +--------------------------+
     | Update Transaction Model |                    | Update Transaction Model |
     | (status = 'completed')   |                    | (status = 'failed')      |
     +-------------+------------+                    +-------------+------------+
                   |                                               |
                   v                                               v
     +--------------------------+                    +--------------------------+
     | Redirect App Success URL |                    | Redirect App Failure URL |
     +--------------------------+                    +--------------------------+
```

---

## 4. Refund Workflow

```
Merchant App                     UniPay BD                     Gateway API
     |                               |                              |
     |--- 1. RefundRequest --------->|                              |
     |    (trxID, amount, reason)    |                              |
     |                               |--- 2. Call Gateway Refund --->|
     |                               |    (bKash/Nagad Endpoint)    |
     |                               |<-- 3. Gateway Response ------|
     |                               |                              |
     |<-- 4. RefundResponse ---------|                              |
     |                               |                              |
     |--- 5. Fire PaymentRefunded -->|                              |
     |    (Updates DB status)        |                              |
```

---

## 5. Code Integration Examples

### Example 1: Initiating a Payment in a Controller

```php
namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Unipay\BD\Facades\Payment;
use Unipay\BD\DTOs\PaymentRequest;

class CheckoutController extends Controller
{
    public function processCheckout(Request $request)
    {
        $paymentRequest = new PaymentRequest(
            amount: 1500.00,
            invoiceId: 'INV-' . time(),
            callbackUrl: route('checkout.callback'),
            customerMobile: '01700000000',
            additionalData: ['user_id' => auth()->id()]
        );

        // Driver can be 'bkash', 'nagad', or omitted to use default
        $response = Payment::driver($request->input('gateway', 'bkash'))
            ->createPayment($paymentRequest);

        if ($response->isSuccessful()) {
            return redirect()->away($response->redirectUrl);
        }

        return back()->with('error', $response->message);
    }
}
```

### Example 2: Listening for UniPay Events

In `App\Providers\EventServiceProvider.php` or a dedicated listener:

```php
namespace App\Listeners;

use Unipay\BD\Events\PaymentSucceeded;
use Unipay\BD\Events\PaymentFailed;
use App\Models\Order;

class HandlePaymentEvents
{
    public function handlePaymentSucceeded(PaymentSucceeded $event)
    {
        $response = $event->response; // PaymentResponse DTO
        $transaction = $event->transaction; // Transaction Model instance

        $order = Order::where('invoice_id', $response->invoiceId)->first();
        if ($order) {
            $order->update([
                'status' => 'paid',
                'transaction_id' => $response->transactionId,
            ]);
        }
    }

    public function handlePaymentFailed(PaymentFailed $event)
    {
        $response = $event->response;
        
        $order = Order::where('invoice_id', $response->invoiceId)->first();
        if ($order) {
            $order->update(['status' => 'failed']);
        }
    }
}
```

### Example 3: Refunding a Transaction

```php
use Unipay\BD\Facades\Payment;
use Unipay\BD\DTOs\RefundRequest;

$refundRequest = new RefundRequest(
    paymentId: 'PAY12345678',
    transactionId: 'TRX98765432',
    amount: 500.00,
    reason: 'Customer requested return'
);

$refundResponse = Payment::driver('bkash')->refund($refundRequest);

if ($refundResponse->isSuccessful()) {
    // Refund complete
}
```
