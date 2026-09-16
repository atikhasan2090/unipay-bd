<?php

namespace Unipay\BD\Tests\Feature;

use GuzzleHttp\Client;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Psr7\Response;
use Unipay\BD\DTOs\PaymentRequest;
use Unipay\BD\DTOs\RefundRequest;
use Unipay\BD\Enums\PaymentStatus;
use Unipay\BD\Gateways\BkashGateway;
use Unipay\BD\Tests\TestCase;

class BkashGatewayTest extends TestCase
{
    public function test_bkash_grant_token_and_create_payment()
    {
        $mock = new MockHandler([
            // Grant Token Response
            new Response(200, [], json_encode([
                'statusCode' => '0000',
                'statusMessage' => 'Successful',
                'id_token' => 'dummy_bearer_token',
                'expires_in' => 3600,
            ])),
            // Create Payment Response
            new Response(200, [], json_encode([
                'statusCode' => '0000',
                'statusMessage' => 'Successful',
                'paymentID' => 'PAY_BKASH_123',
                'bkashURL' => 'https://tokenized.sandbox.bka.sh/checkout/PAY_BKASH_123',
                'merchantInvoiceNumber' => 'INV-BKASH-1',
            ])),
        ]);

        $handlerStack = HandlerStack::create($mock);
        $client = new Client(['handler' => $handlerStack]);

        $config = config('unipay.gateways.bkash');
        $gateway = new BkashGateway($config, $client);

        $request = new PaymentRequest(
            amount: 1000.0,
            invoiceId: 'INV-BKASH-1',
            customerMobile: '01700000000'
        );

        $response = $gateway->createPayment($request);

        $this->assertEquals(PaymentStatus::PENDING, $response->status);
        $this->assertEquals('PAY_BKASH_123', $response->paymentId);
        $this->assertEquals('https://tokenized.sandbox.bka.sh/checkout/PAY_BKASH_123', $response->redirectUrl);
    }

    public function test_bkash_execute_payment()
    {
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
                'currency' => 'BDT',
                'merchantInvoiceNumber' => 'INV-BKASH-1',
            ])),
        ]);

        $handlerStack = HandlerStack::create($mock);
        $client = new Client(['handler' => $handlerStack]);

        $config = config('unipay.gateways.bkash');
        $gateway = new BkashGateway($config, $client);

        $response = $gateway->executePayment('PAY_BKASH_123');

        $this->assertTrue($response->isSuccessful());
        $this->assertEquals('TRX_BKASH_999', $response->transactionId);
        $this->assertEquals(1000.0, $response->amount);
    }

    public function test_bkash_refund_payment()
    {
        $mock = new MockHandler([
            // Grant Token Response
            new Response(200, [], json_encode([
                'statusCode' => '0000',
                'id_token' => 'dummy_bearer_token',
            ])),
            // Refund Payment Response
            new Response(200, [], json_encode([
                'statusCode' => '0000',
                'statusMessage' => 'Successful',
                'refundTrxID' => 'RFD_BKASH_777',
            ])),
        ]);

        $handlerStack = HandlerStack::create($mock);
        $client = new Client(['handler' => $handlerStack]);

        $config = config('unipay.gateways.bkash');
        $gateway = new BkashGateway($config, $client);

        $refundReq = new RefundRequest(
            paymentId: 'PAY_BKASH_123',
            transactionId: 'TRX_BKASH_999',
            amount: 500.0,
            reason: 'Returned item'
        );

        $response = $gateway->refund($refundReq);

        $this->assertTrue($response->isSuccessful());
        $this->assertEquals('RFD_BKASH_777', $response->refundTransactionId);
    }
}
