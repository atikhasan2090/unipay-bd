<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Default Payment Gateway Driver
    |--------------------------------------------------------------------------
    |
    | Supported drivers: "bkash", "nagad", "rocket", "upay", "cellfin", "sslcommerz", "shurjopay"
    |
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
    | Routes & Callback Settings
    |--------------------------------------------------------------------------
    */

    'routes' => [
        'prefix' => 'unipay',
        'middleware' => ['web'],
    ],

    /*
    |--------------------------------------------------------------------------
    | Payment Gateway Drivers Configurations
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
            'public_key' => env('NAGAD_PUBLIC_KEY', ''),
            'private_key' => env('NAGAD_PRIVATE_KEY', ''),
            'currency' => 'BDT',
            'endpoints' => [
                'sandbox' => 'http://sandbox.mynagad.com:9778/api/dfs',
                'live' => 'https://api.mynagad.com/api/dfs',
            ],
        ],

        'rocket' => [
            'sandbox' => env('ROCKET_SANDBOX', true),
            'merchant_id' => env('ROCKET_MERCHANT_ID', ''),
            'terminal_id' => env('ROCKET_TERMINAL_ID', ''),
            'password' => env('ROCKET_PASSWORD', ''),
            'currency' => 'BDT',
            'endpoints' => [
                'sandbox' => 'https://sandbox.dutchbanglabank.com/rocket/api/v1',
                'live' => 'https://rocket.dutchbanglabank.com/api/v1',
            ],
        ],

        'upay' => [
            'sandbox' => env('UPAY_SANDBOX', true),
            'merchant_id' => env('UPAY_MERCHANT_ID', ''),
            'merchant_key' => env('UPAY_MERCHANT_KEY', ''),
            'merchant_code' => env('UPAY_MERCHANT_CODE', ''),
            'password' => env('UPAY_PASSWORD', ''),
            'currency' => 'BDT',
            'endpoints' => [
                'sandbox' => 'https://sandbox.upaybd.com/api/v1',
                'live' => 'https://payment.upaybd.com/api/v1',
            ],
        ],

        'cellfin' => [
            'sandbox' => env('CELLFIN_SANDBOX', true),
            'merchant_id' => env('CELLFIN_MERCHANT_ID', ''),
            'store_id' => env('CELLFIN_STORE_ID', ''),
            'secret_key' => env('CELLFIN_SECRET_KEY', ''),
            'currency' => 'BDT',
            'endpoints' => [
                'sandbox' => 'https://cellfin.islamibankbd.com/sandbox/api/v1',
                'live' => 'https://cellfin.islamibankbd.com/api/v1',
            ],
        ],

        'sslcommerz' => [
            'sandbox' => env('SSLCOMMERZ_SANDBOX', true),
            'store_id' => env('SSLCOMMERZ_STORE_ID', ''),
            'store_password' => env('SSLCOMMERZ_STORE_PASSWORD', ''),
            'currency' => 'BDT',
            'endpoints' => [
                'sandbox' => 'https://sandbox.sslcommerz.com',
                'live' => 'https://securepay.sslcommerz.com',
            ],
        ],

        'shurjopay' => [
            'sandbox' => env('SHURJOPAY_SANDBOX', true),
            'username' => env('SHURJOPAY_USERNAME', 'sp_sandbox'),
            'password' => env('SHURJOPAY_PASSWORD', 'pyRcsawValidated'),
            'prefix' => env('SHURJOPAY_PREFIX', 'NOK'),
            'currency' => 'BDT',
            'endpoints' => [
                'sandbox' => 'https://sandbox.shurjopayment.com/api',
                'live' => 'https://engine.shurjopayment.com/api',
            ],
        ],

    ],
];
