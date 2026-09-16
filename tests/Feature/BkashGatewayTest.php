<?php

namespace Unipay\BD\Tests\Feature;

use GuzzleHttp\Client;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Psr7\Response;
use Unipay\BD\DTOs\PaymentRequest;
use Unipay\BD\DTOs\RefundRequest;
use Unipay\BD\Enums\PaymentStatus;
use Unipay\BD\Exceptions\BkashApiException;
use Unipay\BD\Gateways\BkashGateway;
use Unipay\BD\Tests\TestCase;

class BkashGatewayTest extends TestCase
{
    public function test_bkash_grant_token_and_create_payment_success()
    {
        $mock = new MockHandler([
            new Response(200, [], json_encode([
                'statusCode' => '0000',
                'statusMessage' => 'Successful',
                'id_token' => 'dummy_bearer_token',
                'expires_in' => 3600,
            ])),
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

    public function test_bkash_grant_token_failure_throws_exception()
    {
        $mock = new MockHandler([
            new Response(200, [], json_encode([
                'statusCode' => '2001',
                'statusMessage' => 'Invalid App Key or App Secret',
            ])),
        ]);

        $handlerStack = HandlerStack::create($mock);
        $client = new Client(['handler' => $handlerStack]);

        $config = config('unipay.gateways.bkash');
        $gateway = new BkashGateway($config, $client);

        $this->expectException(BkashApiException::class);
        $gateway->getToken();
    }

    public function test_bkash_create_payment_failure_response()
    {
        $mock = new MockHandler([
            new Response(200, [], json_encode(['statusCode' => '0000', 'id_token' => 'token'])),
            new Response(200, [], json_encode([
                'statusCode' => '2002',
                'statusMessage' => 'Duplicate Invoice ID',
            ])),
        ]);

        $handlerStack = HandlerStack::create($mock);
        $client = new Client(['handler' => $handlerStack]);

        $config = config('unipay.gateways.bkash');
        $gateway = new BkashGateway($config, $client);

        $request = new PaymentRequest(amount: 1000.0, invoiceId: 'INV-BKASH-DUP');
        $response = $gateway->createPayment($request);

        $this->assertTrue($response->isFailed());
        $this->assertEquals('Duplicate Invoice ID', $response->message);
    }

    public function test_bkash_execute_payment_success()
    {
        $mock = new MockHandler([
            new Response(200, [], json_encode(['statusCode' => '0000', 'id_token' => 'token'])),
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

    public function test_bkash_query_payment_status()
    {
        $mock = new MockHandler([
            new Response(200, [], json_encode(['statusCode' => '0000', 'id_token' => 'token'])),
            new Response(200, [], json_encode([
                'statusCode' => '0000',
                'transactionStatus' => 'Completed',
                'trxID' => 'TRX_BKASH_999',
                'merchantInvoiceNumber' => 'INV-BKASH-1',
                'amount' => '1000.00',
            ])),
        ]);

        $handlerStack = HandlerStack::create($mock);
        $client = new Client(['handler' => $handlerStack]);

        $config = config('unipay.gateways.bkash');
        $gateway = new BkashGateway($config, $client);

        $response = $gateway->queryPayment('PAY_BKASH_123');

        $this->assertTrue($response->isSuccessful());
        $this->assertEquals('TRX_BKASH_999', $response->transactionId);
    }

    public function test_bkash_refund_payment_success_and_failure()
    {
        $mock = new MockHandler([
            new Response(200, [], json_encode(['statusCode' => '0000', 'id_token' => 'token'])),
            new Response(200, [], json_encode([
                'statusCode' => '0000',
                'statusMessage' => 'Successful',
                'refundTrxID' => 'RFD_BKASH_777',
            ])),
            new Response(200, [], json_encode([
                'statusCode' => '2005',
                'statusMessage' => 'Refund Limit Exceeded',
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

        $successRes = $gateway->refund($refundReq);
        $this->assertTrue($successRes->isSuccessful());
        $this->assertEquals('RFD_BKASH_777', $successRes->refundTransactionId);

        $failedRes = $gateway->refund($refundReq);
        $this->assertFalse($failedRes->isSuccessful());
    }
}
