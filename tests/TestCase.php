<?php

namespace Unipay\BD\Tests;

use Orchestra\Testbench\TestCase as BaseTestCase;
use Unipay\BD\UnipayServiceProvider;

abstract class TestCase extends BaseTestCase
{
    protected static ?array $dummyKeyPair = null;

    protected function getPackageProviders($app): array
    {
        return [
            UnipayServiceProvider::class,
        ];
    }

    protected function getPackageAliases($app): array
    {
        return [
            'Payment' => \Unipay\BD\Facades\Payment::class,
        ];
    }

    protected function defineEnvironment($app): void
    {
        $app['config']->set('unipay.default', 'bkash');
        $app['config']->set('unipay.logging.enabled', false);
        $app['config']->set('unipay.gateways.bkash', [
            'sandbox' => true,
            'app_key' => 'test_app_key',
            'app_secret' => 'test_app_secret',
            'username' => 'test_username',
            'password' => 'test_password',
            'currency' => 'BDT',
            'intent' => 'sale',
            'endpoints' => [
                'sandbox' => 'https://tokenized.sandbox.bka.sh/v1.2.0-beta',
            ],
        ]);

        $keyPair = $this->getDummyKeyPair();

        $app['config']->set('unipay.gateways.nagad', [
            'sandbox' => true,
            'merchant_id' => '68625001',
            'merchant_number' => '01700000000',
            'public_key' => $keyPair['public'],
            'private_key' => $keyPair['private'],
            'endpoints' => [
                'sandbox' => 'http://sandbox.mynagad.com:9778/api/dfs',
            ],
        ]);
    }

    protected function getDummyKeyPair(): array
    {
        if (static::$dummyKeyPair !== null) {
            return static::$dummyKeyPair;
        }

        $res = openssl_pkey_new([
            'private_key_bits' => 1024,
            'private_key_type' => OPENSSL_KEYTYPE_RSA,
        ]);
        openssl_pkey_export($res, $privateKey);
        $details = openssl_pkey_get_details($res);

        static::$dummyKeyPair = [
            'private' => $privateKey,
            'public' => $details['key'],
        ];

        return static::$dummyKeyPair;
    }
}
