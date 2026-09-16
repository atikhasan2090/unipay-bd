<?php

namespace Unipay\BD\Tests\Feature;

use GuzzleHttp\Client;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Psr7\Response;
use Unipay\BD\DTOs\PaymentRequest;
use Unipay\BD\DTOs\RefundRequest;
use Unipay\BD\Enums\PaymentStatus;
use Unipay\BD\Facades\Payment;
use Unipay\BD\Gateways\CellfinGateway;
use Unipay\BD\Gateways\RocketGateway;
use Unipay\BD\Gateways\ShurjopayGateway;
use Unipay\BD\Gateways\SslcommerzGateway;
use Unipay\BD\Gateways\UpayGateway;
use Unipay\BD\Tests\TestCase;

class NewGatewaysTest extends TestCase
{
    public function test_payment_manager_resolves_all_new_drivers()
    {
        $this->assertInstanceOf(RocketGateway::class, Payment::driver('rocket'));
        $this->assertInstanceOf(UpayGateway::class, Payment::driver('upay'));
        $this->assertInstanceOf(CellfinGateway::class, Payment::driver('cellfin'));
        $this->assertInstanceOf(SslcommerzGateway::class, Payment::driver('sslcommerz'));
        $this->assertInstanceOf(ShurjopayGateway::class, Payment::driver('shurjopay'));
    }

    public function test_rocket_create_verify_and_refund_payment()
    {
        $mock = new MockHandler([
            new Response(200, [], json_encode([
                'status_code' => '200',
                'payment_id' => 'ROCKET_PAY_123',
                'payment_url' => 'https://rocket.dutchbanglabank.com/pay/123',
                'message' => 'Initiated',
            ])),
            new Response(200, [], json_encode([
                'status_code' => '200',
                'transaction_status' => 'SUCCESS',
                'transaction_id' => 'TRX_ROCKET_999',
                'invoice_id' => 'INV-ROCKET-1',
                'amount' => '500.00',
            ])),
            new Response(200, [], json_encode([
                'status_code' => '200',
                'refund_id' => 'RFD_ROCKET_111',
            ])),
        ]);

        $handlerStack = HandlerStack::create($mock);
        $client = new Client(['handler' => $handlerStack]);

        $gateway = new RocketGateway(config('unipay.gateways.rocket'), $client);

        $request = new PaymentRequest(amount: 500.0, invoiceId: 'INV-ROCKET-1');
        $initResponse = $gateway->createPayment($request);

        $this->assertEquals(PaymentStatus::PENDING, $initResponse->status);
        $this->assertEquals('ROCKET_PAY_123', $initResponse->paymentId);

        $verifyResponse = $gateway->queryPayment('ROCKET_PAY_123');
        $this->assertTrue($verifyResponse->isSuccessful());
        $this->assertEquals('TRX_ROCKET_999', $verifyResponse->transactionId);

        $refundReq = new RefundRequest(
            paymentId: 'ROCKET_PAY_123',
            transactionId: 'TRX_ROCKET_999',
            amount: 500.0
        );
        $refundRes = $gateway->refund($refundReq);
        $this->assertTrue($refundRes->isSuccessful());
        $this->assertEquals('RFD_ROCKET_111', $refundRes->refundTransactionId);
    }

    public function test_upay_create_verify_and_refund_payment()
    {
        $mock = new MockHandler([
            new Response(200, [], json_encode(['data' => ['token' => 'upay_token_123']])),
            new Response(200, [], json_encode([
                'data' => [
                    'payment_url' => 'https://upaybd.com/pay/456',
                    'trx_id' => 'UPAY_PAY_456',
                ],
            ])),
            new Response(200, [], json_encode(['data' => ['token' => 'upay_token_123']])),
            new Response(200, [], json_encode([
                'data' => [
                    'status' => 'SUCCESS',
                    'upay_trx_id' => 'TRX_UPAY_888',
                    'txn_id' => 'INV-UPAY-1',
                    'amount' => '750.00',
                ],
            ])),
            new Response(200, [], json_encode(['data' => ['token' => 'upay_token_123']])),
            new Response(200, [], json_encode([
                'status' => 'SUCCESS',
                'data' => ['refund_trx_id' => 'RFD_UPAY_222'],
            ])),
        ]);

        $handlerStack = HandlerStack::create($mock);
        $client = new Client(['handler' => $handlerStack]);

        $gateway = new UpayGateway(config('unipay.gateways.upay'), $client);

        $request = new PaymentRequest(amount: 750.0, invoiceId: 'INV-UPAY-1');
        $initResponse = $gateway->createPayment($request);

        $this->assertEquals(PaymentStatus::PENDING, $initResponse->status);
        $this->assertEquals('UPAY_PAY_456', $initResponse->paymentId);

        $verifyResponse = $gateway->queryPayment('UPAY_PAY_456');
        $this->assertTrue($verifyResponse->isSuccessful());

        $refundReq = new RefundRequest(
            paymentId: 'UPAY_PAY_456',
            transactionId: 'TRX_UPAY_888',
            amount: 750.0
        );
        $refundRes = $gateway->refund($refundReq);
        $this->assertTrue($refundRes->isSuccessful());
        $this->assertEquals('RFD_UPAY_222', $refundRes->refundTransactionId);
    }

    public function test_cellfin_create_verify_and_refund_payment()
    {
        $mock = new MockHandler([
            new Response(200, [], json_encode([
                'code' => '200',
                'cellfin_ref_id' => 'CELLFIN_789',
                'redirect_url' => 'https://cellfin.islamibankbd.com/checkout/789',
            ])),
            new Response(200, [], json_encode([
                'status' => 'SUCCESS',
                'trx_id' => 'TRX_CELLFIN_777',
                'reference_id' => 'INV-CELLFIN-1',
                'amount' => 1200.0,
            ])),
            new Response(200, [], json_encode([
                'status' => 'SUCCESS',
                'refund_ref_id' => 'RFD_CELLFIN_333',
            ])),
        ]);

        $handlerStack = HandlerStack::create($mock);
        $client = new Client(['handler' => $handlerStack]);

        $gateway = new CellfinGateway(config('unipay.gateways.cellfin'), $client);

        $request = new PaymentRequest(amount: 1200.0, invoiceId: 'INV-CELLFIN-1');
        $initResponse = $gateway->createPayment($request);

        $this->assertEquals(PaymentStatus::PENDING, $initResponse->status);

        $verifyResponse = $gateway->queryPayment('CELLFIN_789');
        $this->assertTrue($verifyResponse->isSuccessful());

        $refundReq = new RefundRequest(
            paymentId: 'CELLFIN_789',
            transactionId: 'TRX_CELLFIN_777',
            amount: 1200.0
        );
        $refundRes = $gateway->refund($refundReq);
        $this->assertTrue($refundRes->isSuccessful());
        $this->assertEquals('RFD_CELLFIN_333', $refundRes->refundTransactionId);
    }

    public function test_sslcommerz_create_verify_and_refund_payment()
    {
        $mock = new MockHandler([
            new Response(200, [], json_encode([
                'status' => 'SUCCESS',
                'sessionkey' => 'SSL_SESS_123',
                'GatewayPageURL' => 'https://sandbox.sslcommerz.com/easycheckout/123',
            ])),
            new Response(200, [], json_encode([
                'status' => 'VALID',
                'val_id' => 'SSL_VAL_999',
                'bank_tran_id' => 'TRX_SSL_555',
                'tran_id' => 'INV-SSL-1',
                'amount' => '2500.00',
                'currency' => 'BDT',
            ])),
            new Response(200, [], json_encode([
                'status' => 'SUCCESS',
                'refund_ref_id' => 'RFD_SSL_444',
            ])),
        ]);

        $handlerStack = HandlerStack::create($mock);
        $client = new Client(['handler' => $handlerStack]);

        $gateway = new SslcommerzGateway(config('unipay.gateways.sslcommerz'), $client);

        $request = new PaymentRequest(amount: 2500.0, invoiceId: 'INV-SSL-1');
        $initResponse = $gateway->createPayment($request);

        $this->assertEquals(PaymentStatus::PENDING, $initResponse->status);

        $verifyResponse = $gateway->queryPayment('SSL_VAL_999');
        $this->assertTrue($verifyResponse->isSuccessful());

        $refundReq = new RefundRequest(
            paymentId: 'SSL_SESS_123',
            transactionId: 'TRX_SSL_555',
            amount: 2500.0
        );
        $refundRes = $gateway->refund($refundReq);
        $this->assertTrue($refundRes->isSuccessful());
        $this->assertEquals('RFD_SSL_444', $refundRes->refundTransactionId);
    }

    public function test_shurjopay_create_verify_and_refund_payment()
    {
        $mock = new MockHandler([
            new Response(200, [], json_encode([
                'token' => 'sp_token_123',
                'store_id' => 1,
            ])),
            new Response(200, [], json_encode([
                'checkout_url' => 'https://sandbox.shurjopayment.com/pay/123',
                'sp_order_id' => 'SP_ORD_1001',
            ])),
            new Response(200, [], json_encode([
                'token' => 'sp_token_123',
                'store_id' => 1,
            ])),
            new Response(200, [], json_encode([[
                'sp_code' => '1000',
                'sp_massage' => 'Success',
                'bank_trx_id' => 'TRX_SP_333',
                'customer_order_id' => 'INV-SP-1',
                'amount' => 3000.0,
            ]])),
            new Response(200, [], json_encode([
                'token' => 'sp_token_123',
                'store_id' => 1,
            ])),
            new Response(200, [], json_encode([
                'sp_code' => '1000',
                'sp_massage' => 'Refund Processed',
                'refund_ref_id' => 'RFD_SP_555',
            ])),
        ]);

        $handlerStack = HandlerStack::create($mock);
        $client = new Client(['handler' => $handlerStack]);

        $gateway = new ShurjopayGateway(config('unipay.gateways.shurjopay'), $client);

        $request = new PaymentRequest(amount: 3000.0, invoiceId: 'INV-SP-1');
        $initResponse = $gateway->createPayment($request);

        $this->assertEquals(PaymentStatus::PENDING, $initResponse->status);

        $verifyResponse = $gateway->queryPayment('SP_ORD_1001');
        $this->assertTrue($verifyResponse->isSuccessful());

        $refundReq = new RefundRequest(
            paymentId: 'SP_ORD_1001',
            transactionId: 'TRX_SP_333',
            amount: 3000.0
        );
        $refundRes = $gateway->refund($refundReq);
        $this->assertTrue($refundRes->isSuccessful());
        $this->assertEquals('RFD_SP_555', $refundRes->refundTransactionId);
    }
}
