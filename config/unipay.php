<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Default Payment Gateway Driver
    |--------------------------------------------------------------------------
    |
    | This option controls the default payment gateway driver that will be used
    | when no driver is explicitly specified during payment creation.
    |
    | Supported drivers: "bkash", "nagad"
    |
    */

    'default' => env('UNIPAY_DEFAULT_DRIVER', 'bkash'),

    /*
    |--------------------------------------------------------------------------
    | Transaction Logging
    |--------------------------------------------------------------------------
    |
    | Enable auto-logging of payment transactions to the database.
    |
    */

    'logging' => [
        'enabled' => env('UNIPAY_LOGGING_ENABLED', true),
        'table_name' => 'unipay_transactions',
    ],

    /*
    |--------------------------------------------------------------------------
    | Routes & Callback Settings
    |--------------------------------------------------------------------------
    |
    | Settings for package callback and IPN webhook handling.
    |
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

    ],
];
