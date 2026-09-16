# UniPay BD (`unipay-bd`)

[![Latest Version on Packagist](https://img.shields.io/packagist/v/unipay/unipay-bd.svg?style=flat-square)](https://packagist.org/packages/unipay/unipay-bd)
[![Total Downloads](https://img.shields.io/packagist/dt/unipay/unipay-bd.svg?style=flat-square)](https://packagist.org/packages/unipay/unipay-bd)
[![License](https://img.shields.io/packagist/l/unipay/unipay-bd.svg?style=flat-square)](LICENSE)

UniPay BD is a unified, driver-based Laravel payment package for Bangladeshi mobile financial services (MFS) and payment gateways, featuring first-class support for **bKash Tokenized Checkout** and **Nagad PGW (RSA Signed)**.

---

## Features

- 🚀 **Unified Driver Architecture**: Seamlessly switch between payment gateways using Laravel's Manager pattern (`Payment::driver('bkash')` / `Payment::driver('nagad')`).
- 🔐 **First-Class bKash PGW Support**: Token grant caching, create payment, execute payment, status query, and instant refund.
- 🛡️ **Complete Nagad RSA PGW Support**: Built-in OpenSSL RSA key encryption/decryption, SHA256 signature generation, initialization, completion, and GET verification.
- 📦 **Normalized Response DTOs**: Consistent data structures (`PaymentResponse`) across all gateways—never write gateway-specific response parser logic again!
- ⚡ **Auto Webhook & Callback Engine**: Uniform route handler (`/unipay/callback/{gateway}`) that executes payment verification and fires native Laravel events (`PaymentSucceeded`, `PaymentFailed`, `PaymentRefunded`).
- 📊 **Transaction Logging**: Automatic logging of payment requests, status changes, and raw gateway JSON payloads to your database.
- 🛠️ **Artisan Installer**: One-command installation (`php artisan unipay:install`).

---

## Installation

Install the package via Composer:

```bash
composer require unipay/unipay-bd
```

Run the installer command to publish the configuration and database migrations:

```bash
php artisan unipay:install
php artisan migrate
```

---

## Environment Configuration

Add your gateway credentials to your `.env` file:

```env
# Default Driver
UNIPAY_DEFAULT_DRIVER=bkash
UNIPAY_LOGGING_ENABLED=true

# bKash PGW Credentials
BKASH_SANDBOX=true
BKASH_APP_KEY=your_bkash_app_key
BKASH_APP_SECRET=your_bkash_app_secret
BKASH_USERNAME=your_bkash_username
BKASH_PASSWORD=your_bkash_password

# Nagad PGW Credentials
NAGAD_SANDBOX=true
NAGAD_MERCHANT_ID=68625001
NAGAD_MERCHANT_NUMBER=01700000000
NAGAD_PUBLIC_KEY="-----BEGIN PUBLIC KEY-----\n...\n-----END PUBLIC KEY-----"
NAGAD_PRIVATE_KEY="-----BEGIN PRIVATE KEY-----\n...\n-----END PRIVATE KEY-----"
```

---

## Basic Usage

### 1. Initiating a Payment

```php
use Unipay\BD\Facades\Payment;
use Unipay\BD\DTOs\PaymentRequest;

class CheckoutController extends Controller
{
    public function checkout()
    {
        $paymentRequest = new PaymentRequest(
            amount: 1500.00,
            invoiceId: 'INV-10001',
            customerMobile: '01700000000',
            callbackUrl: route('checkout.callback')
        );

        // Uses default driver or specify driver explicitly: Payment::driver('nagad')
        $response = Payment::createPayment($paymentRequest);

        if ($response->isSuccessful() || $response->isPending()) {
            return redirect()->away($response->redirectUrl);
        }

        return back()->with('error', $response->message);
    }
}
```

### 2. Handling Payment Callbacks & Events

UniPay automatically handles callback redirects and webhooks at `/unipay/callback/{gateway}`. 

Listen for UniPay events in your application:

```php
// App\Providers\EventServiceProvider.php
use Unipay\BD\Events\PaymentSucceeded;
use Unipay\BD\Events\PaymentFailed;

protected $listen = [
    PaymentSucceeded::class => [
        \App\Listeners\MarkOrderAsPaid::class,
    ],
    PaymentFailed::class => [
        \App\Listeners\HandleFailedPayment::class,
    ],
];
```

Inside your listener:

```php
namespace App\Listeners;

use Unipay\BD\Events\PaymentSucceeded;

class MarkOrderAsPaid
{
    public function handle(PaymentSucceeded $event)
    {
        $response = $event->response; // PaymentResponse DTO
        
        $invoiceId = $response->invoiceId;
        $trxId = $response->transactionId;
        $amount = $response->amount;

        // Update your order in database
    }
}
```

### 3. Refunding a Transaction

```php
use Unipay\BD\Facades\Payment;
use Unipay\BD\DTOs\RefundRequest;

$refundReq = new RefundRequest(
    paymentId: 'PAY12345678',
    transactionId: 'TRX98765432',
    amount: 500.00,
    reason: 'Defective product return'
);

$response = Payment::driver('bkash')->refund($refundReq);

if ($response->isSuccessful()) {
    // Refund complete
}
```

---

## Testing

Run the Pest test suite:

```bash
vendor/bin/phpunit
```

---

## License

The MIT License (MIT). Please see [License File](LICENSE) for more information.
