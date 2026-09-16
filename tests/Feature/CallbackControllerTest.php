<?php

namespace Unipay\BD\Tests\Feature;

use GuzzleHttp\Client;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Psr7\Response;
use Illuminate\Support\Facades\Event;
use Unipay\BD\Events\PaymentSucceeded;
use Unipay\BD\Gateways\BkashGateway;
use Unipay\BD\Tests\TestCase;

class CallbackControllerTest extends TestCase
{
    public function test_callback_controller_handles_bkash_success()
    {
        Event::fake();

        // Swap out Guzzle client inside BkashGateway with MockHandler
        $mock = new MockHandler([
            // Grant Token Response
            new Response(200, [], json_encode([
                'statusCode' => '0000',
                'id_token' => 'dummy_bearer_token',
            ])),
            // Execute Payment Response
            new Response(200, [], json_encode([
                'statusCode' => '0000',
                'statusMessage' => 'Successful',
                'paymentID' => 'PAY_BKASH_123',
                'trxID' => 'TRX_BKASH_999',
                'amount' => '1000.00',
                'merchantInvoiceNumber' => 'INV-BKASH-1',
            ])),
        ]);

        $handlerStack = HandlerStack::create($mock);
        $client = new Client(['handler' => $handlerStack]);

        $this->app->extend('unipay', function ($manager) use ($client) {
            $manager->extend('bkash', function () use ($client) {
                return new BkashGateway(config('unipay.gateways.bkash'), $client);
            });
            return $manager;
        });

        $response = $this->getJson('/unipay/callback/bkash?paymentID=PAY_BKASH_123&status=success');

        $response->assertStatus(200);
        $response->assertJson(['status' => 'success']);

        Event::assertDispatched(PaymentSucceeded::class);
    }
}
