# Comprehensive Implementation Plan - UniPay BD (`unipay-bd`)

UniPay BD is a unified, driver-based Laravel payment package for Bangladeshi mobile financial services (MFS) and payment gateways, starting with **bKash** and **Nagad**, with extensible architecture for Rocket, Upay, and SSLCommerz.

---

## 1. Architectural Blueprint & Design System

UniPay adopts Laravel's standard **Driver Pattern** (analogous to `Illuminate\Filesystem\FilesystemManager` or `Illuminate\Cache\CacheManager`). 

```
                                  +-----------------------+
                                  |    Payment Facade     |
                                  +-----------+-----------+
                                              |
                                  +-----------v-----------+
                                  |    PaymentManager     |
                                  +-----------+-----------+
                                              |
                   +--------------------------+--------------------------+
                   |                                                     |
       +-----------v-----------+                             +-----------v-----------+
       |     BkashGateway      |                             |     NagadGateway      |
       |  (Token REST Engine)  |                             | (RSA Sign/Encrypt Eng)|
       +-----------+-----------+                             +-----------+-----------+
                   |                                                     |
                   +--------------------------+--------------------------+
                                              |
                                  +-----------v-----------+
                                  |   PaymentResponse DTO |
                                  +-----------------------+
```

### Core Architecture Components:
1. **`PaymentManager`**: Resolves gateway drivers dynamically based on config or runtime selection (`Payment::driver('bkash')->create($request)`).
2. **`GatewayInterface`**: Unified contract enforcing `createPayment`, `executePayment`, `queryPayment`, and `refund` signatures across all gateway drivers.
3. **`PaymentRequest` DTO**: Immutable object encapsulating payment amount, order reference, currency, customer details, and callback metadata.
4. **`PaymentResponse` DTO**: Standardized response wrapper guaranteeing uniform property access (`status`, `transaction_id`, `payment_id`, `redirect_url`, `amount`, `message`, `raw_response`) regardless of the underlying payment gateway.
5. **Callback & Webhook Engine**: Automatically registered routes handling IPN/webhooks and user redirects, firing native Laravel events.
6. **Persistence Layer**: Optional transaction logger storing raw gateway JSON responses and normalized payment records.

---

## 2. Directory Structure & File Specifications

```
packages/unipay-bd/
├── composer.json
├── README.md
├── LICENSE
├── config/
│   └── unipay.php
├── database/
│   └── migrations/
│       └── 2026_01_01_000000_create_unipay_transactions_table.php
├── routes/
│   └── web.php
├── src/
│   ├── UnipayServiceProvider.php
│   ├── PaymentManager.php
│   ├── Contracts/
│   │   └── GatewayInterface.php
│   ├── DTOs/
│   │   ├── PaymentRequest.php
│   │   ├── PaymentResponse.php
│   │   ├── RefundRequest.php
│   │   └── RefundResponse.php
│   ├── Enums/
│   │   ├── PaymentStatus.php
│   │   └── GatewayDriver.php
│   ├── Gateways/
│   │   ├── AbstractGateway.php
│   │   ├── BkashGateway.php
│   │   └── NagadGateway.php
│   ├── Facades/
│   │   └── Payment.php
│   ├── Http/
│   │   └── Controllers/
│   │       └── CallbackController.php
│   ├── Events/
│   │   ├── PaymentSucceeded.php
│   │   ├── PaymentFailed.php
│   │   └── PaymentRefunded.php
│   ├── Models/
│   │   └── Transaction.php
│   ├── Console/
│   │   └── Commands/
│   │       └── InstallCommand.php
│   └── Exceptions/
│       ├── UnipayException.php
│       ├── InvalidGatewayException.php
│       ├── BkashApiException.php
│       ├── NagadEncryptionException.php
│       └── PaymentVerificationFailedException.php
└── tests/
    ├── TestCase.php
    ├── Feature/
    │   ├── BkashGatewayTest.php
    │   ├── NagadGatewayTest.php
    │   └── CallbackControllerTest.php
    └── Unit/
        ├── PaymentManagerTest.php
        └── DtoTest.php
```

---

## 3. Package Configuration Schema (`config/unipay.php`)

```php
<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Default Payment Gateway Driver
    |--------------------------------------------------------------------------
    */
    'default' => env('UNIPAY_DEFAULT_DRIVER', 'bkash'),

    /*
    |--------------------------------------------------------------------------
    | Transaction Logging
    |--------------------------------------------------------------------------
    */
    'logging' => [
        'enabled' => env('UNIPAY_LOGGING_ENABLED', true),
        'table_name' => 'unipay_transactions',
    ],

    /*
    |--------------------------------------------------------------------------
    | Callback & Webhook Route Configurations
    |--------------------------------------------------------------------------
    */
    'routes' => [
        'prefix' => 'unipay',
        'middleware' => ['web'],
    ],

    /*
    |--------------------------------------------------------------------------
    | Gateway Credentials & Drivers Settings
    |--------------------------------------------------------------------------
    */
    'gateways' => [

        'bkash' => [
            'sandbox' => env('BKASH_SANDBOX', true),
            'app_key' => env('BKASH_APP_KEY', ''),
            'app_secret' => env('BKASH_APP_SECRET', ''),
            'username' => env('BKASH_USERNAME', ''),
            'password' => env('BKASH_PASSWORD', ''),
            'currency' => 'BDT',
            'intent' => 'sale',
            'endpoints' => [
                'sandbox' => 'https://tokenized.sandbox.bka.sh/v1.2.0-beta',
                'live' => 'https://tokenized.pay.bka.sh/v1.2.0-beta',
            ],
        ],

        'nagad' => [
            'sandbox' => env('NAGAD_SANDBOX', true),
            'merchant_id' => env('NAGAD_MERCHANT_ID', ''),
            'merchant_number' => env('NAGAD_MERCHANT_NUMBER', ''),
            'public_key' => env('NAGAD_PUBLIC_KEY', ''), // Nagad PGW Public Key
            'private_key' => env('NAGAD_PRIVATE_KEY', ''), // Merchant Private Key
            'currency' => 'BDT',
            'endpoints' => [
                'sandbox' => 'http://sandbox.mynagad.com:9778/api/dfs',
                'live' => 'https://api.mynagad.com/api/dfs',
            ],
        ],

    ],
];
```

---

## 4. Phase-by-Phase Implementation Specifications

### Phase 1: Package Foundation & Infrastructure
- **Files**: `composer.json`, `src/UnipayServiceProvider.php`, `config/unipay.php`.
- **Dependencies**:
  - `illuminate/support`: `^10.0 || ^11.0`
  - `illuminate/contracts`: `^10.0 || ^11.0`
  - `guzzlehttp/guzzle`: `^7.5`
  - `ext-openssl`: `*`
  - `orchestra/testbench`: `^8.0 || ^9.0` (Dev)
  - `pestphp/pest`: `^2.0 || ^3.0` (Dev)
- **Service Provider Responsibilities**:
  - Merge default configuration from `config/unipay.php`.
  - Register `PaymentManager` as a singleton in the service container.
  - Bind facade accessor `Payment` -> `PaymentManager`.
  - Register migration files and artisan command `unipay:install`.
  - Load package callback routes.

---

### Phase 2: Core Abstractions, Enums & DTOs

#### Contract: `GatewayInterface.php`
```php
namespace Unipay\BD\Contracts;

use Unipay\BD\DTOs\PaymentRequest;
use Unipay\BD\DTOs\PaymentResponse;
use Unipay\BD\DTOs\RefundRequest;
use Unipay\BD\DTOs\RefundResponse;

interface GatewayInterface
{
    public function createPayment(PaymentRequest $request): PaymentResponse;
    public function executePayment(string $paymentId, array $additionalParams = []): PaymentResponse;
    public function queryPayment(string $paymentId): PaymentResponse;
    public function refund(RefundRequest $request): RefundResponse;
}
```

#### DTO: `PaymentRequest.php`
- Enforces strict typing for payment inputs:
  - `amount`: float (e.g. `100.00`)
  - `invoiceId`: string (Unique order reference)
  - `callbackUrl`: string (Return URL after payment attempt)
  - `customerMobile`: ?string (Optional customer phone number)
  - `additionalData`: array (Custom metadata)

#### DTO: `PaymentResponse.php`
- Standardized attributes:
  - `status`: `PaymentStatus` Enum (`SUCCESS`, `PENDING`, `FAILED`, `CANCELLED`)
  - `paymentId`: ?string (Gateway session ID / Payment ID)
  - `transactionId`: ?string (TrxID issued by MFS on success)
  - `amount`: float
  - `currency`: string
  - `redirectUrl`: ?string (URL to redirect the customer to complete payment)
  - `message`: string
  - `rawResponse`: array (Full un-altered raw JSON payload from gateway)

---

### Phase 3: Gateway Driver Implementation Details

#### Driver 1: bKash Tokenized Checkout Driver (`BkashGateway.php`)
1. **Authentication Flow**:
   - Calls POST `/tokenized/checkout/token/grant` using `app_key` and `app_secret` header.
   - Caches `id_token` in Laravel Cache with expiration (~3600 seconds) to avoid redundant auth network calls.
2. **Create Payment**:
   - Sends POST `/tokenized/checkout/create` with `amount`, `currency`, `intent`, `merchantInvoiceNumber`, `callbackURL`.
   - Extracts `paymentID` and `bkashURL` (redirect URL).
3. **Execute Payment**:
   - Triggered when customer completes PIN verification on bKash PGW modal/redirect.
   - Calls POST `/tokenized/checkout/execute` using `paymentID`.
   - Extracts `trxID`, `paymentExecuteTime`, and `statusCode`.
4. **Query & Refund**:
   - POST `/tokenized/checkout/payment/status` with `paymentID`.
   - POST `/tokenized/checkout/payment/refund` with `paymentID`, `amount`, `trxID`, `sku`.

#### Driver 2: Nagad RSA Signed Driver (`NagadGateway.php`)
1. **RSA Security Architecture**:
   - Uses `OpenSSL` for PKCS1 padding encryption and SHA256withRSA signature generation.
   - Merchant Private Key signs sensitive data (`merchantId`, `datetime`, `orderId`, `challenge`).
   - Nagad Public Key encrypts sensitive JSON payloads.
2. **Step 1: Initialize Payment**:
   - Generates random `challenge` string & timestamp `YYYYMMDDHHMMSS`.
   - Sends POST `/check-out/initialize/{merchantId}/{orderId}` with encrypted `sensitiveData` and `signature`.
   - Receives `paymentReferenceId` and `challenge` from Nagad.
3. **Step 2: Complete Payment / Checkout**:
   - Decrypts Nagad's challenge using Private Key.
   - Construct completion sensitive payload (orderId, amount, challenge, callbackUrl).
   - Encrypts payload with Nagad Public Key and signs with Merchant Private Key.
   - Sends POST `/check-out/complete/{paymentReferenceId}`.
   - Extracts `callBackUrl` containing transaction token / redirect URL.
4. **Step 3: Verification & Status Query**:
   - GET `/verify/payment/{paymentRefId}` to confirm final payment status (`COMPLETED`, `FAILED`).

---

### Phase 4: Callbacks, Events & Persistence

#### Route & Webhook Controller (`CallbackController.php`)
- Route: `GET|POST /unipay/callback/{gateway}`
- Reads incoming request query parameters / POST payload (`paymentID`, `status`, `paymentRefId`, `trxID`).
- Executes gateway verification check via `Payment::driver($gateway)->executePayment(...)` or `queryPayment(...)`.
- Fires events:
  - `PaymentSucceeded($paymentResponse, $transaction)`
  - `PaymentFailed($paymentResponse, $transaction)`
- Redirects user back to application's designated success/failure URL or returns JSON response for API integrations.

#### Transaction Model & Schema (`unipay_transactions`)
- Fields:
  - `id`: bigint (primary key)
  - `uuid`: uuid (unique identifier)
  - `gateway`: string (`bkash`, `nagad`)
  - `payment_id`: string (nullable, indexed)
  - `transaction_id`: string (nullable, indexed)
  - `invoice_id`: string (indexed)
  - `amount`: decimal(12, 2)
  - `currency`: string (3 chars, default BDT)
  - `status`: string (`pending`, `completed`, `failed`, `cancelled`, `refunded`)
  - `raw_response`: json (nullable)
  - `created_at`, `updated_at`

---

### Phase 5: Developer Experience & CLI Tooling

#### Artisan Installer (`php artisan unipay:install`)
- Publishes `config/unipay.php`.
- Publishes database migrations.
- Prompts user to run `php artisan migrate`.

#### Custom Exceptions
- `InvalidGatewayException`: Thrown if requested gateway driver does not exist.
- `BkashApiException`: Detailed bKash error messages with response status codes.
- `NagadEncryptionException`: Detailed OpenSSL key or encryption/signing errors.
- `PaymentVerificationFailedException`: Thrown when callback signature/status check fails.

---

### Phase 6: Automated Testing & Quality Assurance
- **Pest / PHPUnit Test Suite**:
  - `BkashGatewayTest`: Mocks bKash Guzzle HTTP client responses for grant token, create payment, execute, and refund.
  - `NagadGatewayTest`: Generates dummy RSA keypair in test setup, tests RSA encryption/decryption, challenge resolution, and verification.
  - `PaymentManagerTest`: Verifies custom driver extension capabilities and default driver fallback.
  - `CallbackControllerTest`: Tests callback route handling and event dispatching.

---

## 5. Verification Plan

### Automated Test Execution
Run Pest test suite with coverage report:
```bash
vendor/bin/pest --coverage
```

### Verification Checklist
- [ ] `composer test` executes cleanly with 0 failures.
- [ ] RSA encryption/decryption and signing passes without OpenSSL memory leaks.
- [ ] bKash token caching properly prevents unnecessary token grant calls.
- [ ] `PaymentResponse` returns identical array structure regardless of driver used.
- [ ] `php artisan unipay:install` successfully publishes assets in host Laravel app.
