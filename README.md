# UniPay BD (`unipay/unipay-bd`)

[![Latest Version on Packagist](https://img.shields.io/packagist/v/unipay/unipay-bd.svg?style=flat-square)](https://packagist.org/packages/unipay/unipay-bd)
[![Total Downloads](https://img.shields.io/packagist/dt/unipay/unipay-bd.svg?style=flat-square)](https://packagist.org/packages/unipay/unipay-bd)
[![License](https://img.shields.io/packagist/l/unipay/unipay-bd.svg?style=flat-square)](LICENSE)

UniPay BD is a unified, driver-based Laravel payment package for Bangladeshi mobile financial services (MFS) and payment gateways, with full driver support for **bKash**, **Nagad**, **Rocket**, **Upay**, **CellFin**, **SSLCommerz**, and **Shurjopay**.

---

## Supported Gateways & Drivers

| Gateway | Driver Name | MFS / Provider | Type | Protocol / Tech |
| :--- | :--- | :--- | :--- | :--- |
| **bKash** | `bkash` | bKash PGW | Mobile Banking | Tokenized REST API v1.2 |
| **Nagad** | `nagad` | Nagad | Mobile Banking | RSA OpenSSL Encryption & Signature |
| **Rocket** | `rocket` | Dutch-Bangla Bank (DBBL) | Mobile Banking | Merchant API v1 |
| **Upay** | `upay` | UCB Fintech | Mobile Banking | Bearer Token REST API |
| **CellFin** | `cellfin` | Islami Bank (IBBL) | Mobile Banking | Reference ID Checkout API |
| **SSLCommerz**| `sslcommerz`| SSL Wireless | Card & Gateway Aggregator| GWProcess v4 API |
| **Shurjopay** | `shurjopay` | ShurjoMukhi | Card & Gateway Aggregator| Shurjopay API v2 |

---

## Features

- 🚀 **Unified Driver Architecture**: Seamlessly switch between any supported Bangladeshi payment gateway (`Payment::driver('bkash')`, `Payment::driver('rocket')`, `Payment::driver('upay')`, etc.).
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

Run the installer command to publish configuration and database migrations:

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

# bKash PGW
BKASH_SANDBOX=true
BKASH_APP_KEY=your_bkash_app_key
BKASH_APP_SECRET=your_bkash_app_secret
BKASH_USERNAME=your_bkash_username
BKASH_PASSWORD=your_bkash_password

# Nagad PGW
NAGAD_SANDBOX=true
NAGAD_MERCHANT_ID=68625001
NAGAD_MERCHANT_NUMBER=01700000000
NAGAD_PUBLIC_KEY="-----BEGIN PUBLIC KEY-----\n...\n-----END PUBLIC KEY-----"
NAGAD_PRIVATE_KEY="-----BEGIN PRIVATE KEY-----\n...\n-----END PRIVATE KEY-----"

# Rocket DBBL
ROCKET_SANDBOX=true
ROCKET_MERCHANT_ID=your_rocket_merchant_id
ROCKET_TERMINAL_ID=your_rocket_terminal_id
ROCKET_PASSWORD=your_rocket_password

# Upay (UCB)
UPAY_SANDBOX=true
UPAY_MERCHANT_ID=your_upay_merchant_id
UPAY_MERCHANT_KEY=your_upay_merchant_key
UPAY_MERCHANT_CODE=your_upay_merchant_code
UPAY_PASSWORD=your_upay_password

# CellFin (IBBL)
CELLFIN_SANDBOX=true
CELLFIN_MERCHANT_ID=your_cellfin_merchant_id
CELLFIN_STORE_ID=your_cellfin_store_id
CELLFIN_SECRET_KEY=your_cellfin_secret_key

# SSLCommerz
SSLCOMMERZ_SANDBOX=true
SSLCOMMERZ_STORE_ID=your_store_id
SSLCOMMERZ_STORE_PASSWORD=your_store_password

# Shurjopay
SHURJOPAY_SANDBOX=true
SHURJOPAY_USERNAME=sp_sandbox
SHURJOPAY_PASSWORD=pyRcsawValidated
SHURJOPAY_PREFIX=NOK
```

---

## Usage Examples

### 1. Initiating a Payment

```php
use Unipay\BD\Facades\Payment;
use Unipay\BD\DTOs\PaymentRequest;

class CheckoutController extends Controller
{
    public function checkout(Request $request)
    {
        $gateway = $request->input('gateway', 'bkash'); // 'bkash', 'nagad', 'rocket', 'upay', 'cellfin', 'sslcommerz', 'shurjopay'

        $paymentRequest = new PaymentRequest(
            amount: 1500.00,
            invoiceId: 'INV-10001',
            customerMobile: '01700000000',
            callbackUrl: route('checkout.callback')
        );

        $response = Payment::driver($gateway)->createPayment($paymentRequest);

        if ($response->isSuccessful() || $response->isPending()) {
            return redirect()->away($response->redirectUrl);
        }

        return back()->with('error', $response->message);
    }
}
```

### 2. Event Listeners

```php
use Unipay\BD\Events\PaymentSucceeded;

class MarkOrderAsPaid
{
    public function handle(PaymentSucceeded $event)
    {
        $response = $event->response; // PaymentResponse DTO
        
        $invoiceId = $response->invoiceId;
        $trxId = $response->transactionId;
        $amount = $response->amount;
        $gateway = $response->gatewayName; // 'bkash', 'rocket', etc.

        // Mark order as paid in database
    }
}
```

---

## Testing

Run the PHPUnit test suite:

```bash
vendor/bin/phpunit
```

---

## License

The MIT License (MIT). Please see [License File](LICENSE) for more information.
