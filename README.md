<p align="center">
  <a href="https://github.com/atikhasan2090/unipay-bd" target="_blank">
    <img src="https://raw.githubusercontent.com/laravel/art/master/logo-lockup/5%20SVG/2%20CMYK/1%20Full%20Color/laravel-logolockup-cmyk-red.svg" width="300" alt="Laravel Logo">
  </a>
</p>

<h1 align="center">UniPay BD — Unified Bangladesh Payment Gateway for Laravel</h1>

<p align="center">
  <strong>The ultimate unified Laravel payment gateway package for Bangladeshi Mobile Financial Services (MFS) & Payment Gateways.</strong>
</p>

<p align="center">
  <a href="https://packagist.org/packages/unipay/unipay-bd"><img src="https://img.shields.io/badge/version-v1.0.0-blue.svg?style=for-the-badge&logo=composer" alt="Package Version"></a>
  <a href="https://github.com/atikhasan2090/unipay-bd"><img src="https://img.shields.io/github/license/atikhasan2090/unipay-bd?style=for-the-badge&color=brightgreen" alt="License"></a>
  <a href="https://php.net"><img src="https://img.shields.io/badge/PHP-8.1%2B-777BB4?style=for-the-badge&logo=php&logoColor=white" alt="PHP Version"></a>
  <a href="https://laravel.com"><img src="https://img.shields.io/badge/Laravel-10.x%20%7C%2011.x-FF2D20?style=for-the-badge&logo=laravel&logoColor=white" alt="Laravel Version"></a>
  <a href="https://github.com/atikhasan2090/unipay-bd/actions"><img src="https://img.shields.io/badge/tests-passing-brightgreen.svg?style=for-the-badge&logo=github" alt="Tests"></a>
</p>

---

## 📌 Table of Contents

- [Overview](#-overview)
- [Supported Payment Gateways](#-supported-payment-gateways)
- [Key Features](#-key-features)
- [Requirements](#-requirements)
- [Installation](#-installation)
- [Configuration](#-configuration)
- [Quick Start & Usage](#-quick-start--usage)
  - [1. Initiate Payment](#1-initiate-payment)
  - [2. Handle Payment Callbacks & Webhooks](#2-handle-payment-callbacks--webhooks)
  - [3. Listen to Payment Events](#3-listen-to-payment-events)
  - [4. Process Refunds](#4-process-refunds)
  - [5. Query Payment Status](#5-query-payment-status)
- [Extending with Custom Drivers](#-extending-with-custom-drivers)
- [Exception & Error Handling](#-exception--error-handling)
- [Testing](#-testing)
- [Security](#-security)
- [License](#-license)

---

## 🚀 Overview

**UniPay BD** (`unipay/unipay-bd`) provides a seamless, developer-friendly driver-based payment integration for **Laravel applications** operating in Bangladesh. 

Inspired by Laravel's native driver pattern (`FilesystemManager`, `CacheManager`), UniPay allows developers to integrate **bKash**, **Nagad**, **Rocket**, **Upay**, **CellFin**, **SSLCommerz**, and **Shurjopay** using a single, normalized API syntax.

Instead of writing fragmented, gateway-specific API logic for each payment provider, **UniPay BD** normalizes all request payloads and response structures (`PaymentResponse`) across all Bangladeshi payment gateways.

---

## 💳 Supported Payment Gateways

UniPay BD natively supports **7 Bangladeshi payment providers**:

| Gateway | Driver | MFS / Provider | Security & Protocol | Supported Operations |
| :--- | :--- | :--- | :--- | :--- |
| **bKash** | `bkash` | bKash PGW | Tokenized REST API v1.2 with Bearer token caching | Create, Execute, Query, Refund |
| **Nagad** | `nagad` | Nagad | RSA OpenSSL Public Key Encryption & Private Key Signing | Create, Complete, Verify, Refund |
| **Rocket** | `rocket` | Dutch-Bangla Bank (DBBL) | Merchant API v1 with Terminal ID | Create, Verify, Refund |
| **Upay** | `upay` | UCB Fintech | Bearer Token REST API | Create, Verify, Refund |
| **CellFin** | `cellfin` | Islami Bank (IBBL) | Reference ID Checkout API | Create, Verify, Refund |
| **SSLCommerz** | `sslcommerz` | SSL Wireless | GWProcess v4 API & Validation API | Create, Validate, Refund |
| **Shurjopay** | `shurjopay` | ShurjoMukhi | Shurjopay API v2 Tokenized | Create, Verify, Refund |

---

## ✨ Key Features

- 🎯 **Unified Manager Architecture**: Easily switch payment drivers on the fly using `Payment::driver('bkash')`, `Payment::driver('nagad')`, etc.
- 📦 **Normalized DTO Responses**: Unified `PaymentResponse` object guarantees identical property access (`status`, `paymentId`, `transactionId`, `amount`, `redirectUrl`, `rawResponse`).
- 🔄 **Automated Callback & Webhook System**: Built-in callback engine at `/unipay/callback/{gateway}` handles payment redirects and webhooks automatically.
- 🔔 **Native Laravel Events**: Fires `PaymentSucceeded`, `PaymentFailed`, and `PaymentRefunded` events for decoupled application logic.
- 🔐 **Enterprise RSA & Token Security**: In-memory token caching for bKash and PKCS1 RSA encryption for Nagad merchant onboarding.
- 📊 **Transaction Logging**: Automatic logging of payment requests, status updates, and raw gateway JSON payloads to your database.
- 🛠️ **Artisan Installation CLI**: Publish configs and database migrations with `php artisan unipay:install`.

---

## ⚙️ Requirements

- **PHP**: `^8.1 || ^8.2 || ^8.3`
- **Laravel**: `^10.0 || ^11.0`
- **PHP Extensions**: `ext-json`, `ext-openssl`, `ext-curl`

---

## 📦 Installation

Install the package via Composer:

```bash
composer require unipay/unipay-bd
```

Run the Artisan installer to publish the configuration file and database migrations:

```bash
php artisan unipay:install
php artisan migrate
```

---

## 🔧 Configuration

Add your payment gateway credentials to your application's `.env` file:

```env
# Default Driver & Logging
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

# DBBL Rocket Credentials
ROCKET_SANDBOX=true
ROCKET_MERCHANT_ID=your_rocket_merchant_id
ROCKET_TERMINAL_ID=your_rocket_terminal_id
ROCKET_PASSWORD=your_rocket_password

# Upay Credentials
UPAY_SANDBOX=true
UPAY_MERCHANT_ID=your_upay_merchant_id
UPAY_MERCHANT_KEY=your_upay_merchant_key
UPAY_MERCHANT_CODE=your_upay_merchant_code
UPAY_PASSWORD=your_upay_password

# IBBL CellFin Credentials
CELLFIN_SANDBOX=true
CELLFIN_MERCHANT_ID=your_cellfin_merchant_id
CELLFIN_STORE_ID=your_cellfin_store_id
CELLFIN_SECRET_KEY=your_cellfin_secret_key

# SSLCommerz Credentials
SSLCOMMERZ_SANDBOX=true
SSLCOMMERZ_STORE_ID=your_sslcommerz_store_id
SSLCOMMERZ_STORE_PASSWORD=your_sslcommerz_store_password

# Shurjopay Credentials
SHURJOPAY_SANDBOX=true
SHURJOPAY_USERNAME=sp_sandbox
SHURJOPAY_PASSWORD=pyRcsawValidated
SHURJOPAY_PREFIX=NOK
```

---

## 💻 Quick Start & Usage

### 1. Initiate Payment

To start a checkout process, build a `PaymentRequest` and call `Payment::createPayment()`:

```php
namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Unipay\BD\Facades\Payment;
use Unipay\BD\DTOs\PaymentRequest;

class CheckoutController extends Controller
{
    public function processCheckout(Request $request)
    {
        // Choose gateway driver dynamically ('bkash', 'nagad', 'rocket', 'upay', 'cellfin', 'sslcommerz', 'shurjopay')
        $gateway = $request->input('gateway', 'bkash');

        $paymentRequest = new PaymentRequest(
            amount: 1250.00,
            invoiceId: 'INV-' . time(),
            customerMobile: '01700000000',
            callbackUrl: route('checkout.callback')
        );

        $response = Payment::driver($gateway)->createPayment($paymentRequest);

        if ($response->isSuccessful() || $response->isPending()) {
            // Redirect customer to MFS / Gateway checkout page
            return redirect()->away($response->redirectUrl);
        }

        return back()->with('error', $response->message);
    }
}
```

---

### 2. Handle Payment Callbacks & Webhooks

UniPay automatically registers a unified callback route at `/unipay/callback/{gateway}`.

When a customer completes payment, the MFS provider redirects back to this endpoint. UniPay automatically executes status verification and dispatches Laravel events.

---

### 3. Listen to Payment Events

Decouple your order processing logic by listening to UniPay events in `App\Providers\EventServiceProvider.php`:

```php
use Unipay\BD\Events\PaymentSucceeded;
use Unipay\BD\Events\PaymentFailed;
use Unipay\BD\Events\PaymentRefunded;

protected $listen = [
    PaymentSucceeded::class => [
        \App\Listeners\MarkOrderAsPaid::class,
    ],
    PaymentFailed::class => [
        \App\Listeners\HandleFailedPayment::class,
    ],
];
```

Inside your event listener class:

```php
namespace App\Listeners;

use Unipay\BD\Events\PaymentSucceeded;
use App\Models\Order;

class MarkOrderAsPaid
{
    public function handle(PaymentSucceeded $event)
    {
        $response = $event->response; // PaymentResponse DTO
        
        $order = Order::where('invoice_id', $response->invoiceId)->first();

        if ($order) {
            $order->update([
                'status' => 'paid',
                'transaction_id' => $response->transactionId,
                'gateway' => $response->gatewayName,
            ]);
        }
    }
}
```

---

### 4. Process Refunds

To issue a full or partial refund to a customer:

```php
use Unipay\BD\Facades\Payment;
use Unipay\BD\DTOs\RefundRequest;

$refundRequest = new RefundRequest(
    paymentId: 'PAY_12345678',
    transactionId: 'TRX_98765432',
    amount: 500.00,
    reason: 'Customer return request'
);

$response = Payment::driver('bkash')->refund($refundRequest);

if ($response->isSuccessful()) {
    // Refund complete
    $refundTrxId = $response->refundTransactionId;
}
```

---

### 5. Query Payment Status

Query any transaction status explicitly at any time:

```php
use Unipay\BD\Facades\Payment;

$response = Payment::driver('nagad')->queryPayment('NAGAD_REF_1001');

if ($response->isSuccessful()) {
    $trxId = $response->transactionId;
    $amount = $response->amount;
}
```

---

## 🛠️ Extending with Custom Drivers

You can easily register custom gateway drivers using `Payment::extend()`:

```php
use Unipay\BD\Facades\Payment;

Payment::extend('custom_mfs', function ($app) {
    return new CustomMfsGateway(config('unipay.gateways.custom_mfs'));
});
```

---

## ⚠️ Exception & Error Handling

UniPay provides a clean exception hierarchy:

- `Unipay\BD\Exceptions\UnipayException`: Base exception class.
- `Unipay\BD\Exceptions\InvalidGatewayException`: Thrown when requesting an unsupported gateway.
- `Unipay\BD\Exceptions\BkashApiException`: Thrown when bKash API returns invalid credentials or auth errors.
- `Unipay\BD\Exceptions\NagadEncryptionException`: Thrown when OpenSSL RSA keys or signatures fail.
- `Unipay\BD\Exceptions\PaymentVerificationFailedException`: Thrown on failed payment checksums.

```php
use Unipay\BD\Exceptions\UnipayException;

try {
    $response = Payment::driver('bkash')->createPayment($paymentRequest);
} catch (UnipayException $e) {
    logger()->error('UniPay Error: ' . $e->getMessage());
}
```

---

## 🧪 Testing

UniPay BD includes a full **Pest & PHPUnit** test suite with 100% mocked HTTP responses and OpenSSL key pair generation.

Run the test suite:

```bash
vendor/bin/phpunit
```

Expected output:
```text
OK (24 tests, 73 assertions)
```

---

## 🔒 Security

If you discover any security-related issues, please email `atikhasan2090@gmail.com` instead of using the public issue tracker.

---

## 📄 License

UniPay BD is open-sourced software licensed under the **[MIT License](LICENSE)**.

---

<p align="center">
  Crafted with ❤️ for the Bangladeshi Developer Community.
</p>
