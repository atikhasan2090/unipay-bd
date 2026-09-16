<?php

namespace Unipay\BD\Tests\Feature;

use GuzzleHttp\Client;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Psr7\Response;
use Unipay\BD\DTOs\PaymentRequest;
use Unipay\BD\Enums\PaymentStatus;
use Unipay\BD\Gateways\NagadGateway;
use Unipay\BD\Tests\TestCase;

class NagadGatewayTest extends TestCase
{
    public function test_nagad_rsa_encryption_and_signature()
    {
        $config = config('unipay.gateways.nagad');
        $gateway = new NagadGateway($config);

        $plainText = 'Hello UniPay Nagad';
        $encrypted = $gateway->encryptPublicKey($plainText);
        $this->assertNotEmpty($encrypted);

        $decrypted = $gateway->decryptPrivateKey($encrypted);
        $this->assertEquals($plainText, $decrypted);

        $signature = $gateway->generateSignature($plainText);
        $this->assertNotEmpty($signature);
    }

    public function test_nagad_create_payment_flow()
    {
        $config = config('unipay.gateways.nagad');
        $gatewayTemp = new NagadGateway($config);

        // Prepare encrypted mock sensitive response for Nagad initialize
        $initSensitiveJson = json_encode([
            'paymentReferenceId' => 'NAGAD_REF_1001',
            'challenge' => 'challenge_from_nagad_123',
        ]);
        $encryptedInitSensitive = $gatewayTemp->encryptPublicKey($initSensitiveJson);

        $mock = new MockHandler([
            // Initialize Endpoint Response
            new Response(200, [], json_encode([
                'sensitiveData' => $encryptedInitSensitive,
            ])),
            // Complete Endpoint Response
            new Response(200, [], json_encode([
                'callBackUrl' => 'http://sandbox.mynagad.com/checkout/NAGAD_REF_1001',
                'message' => 'Success',
            ])),
        ]);

        $handlerStack = HandlerStack::create($mock);
        $client = new Client(['handler' => $handlerStack]);

        $gateway = new NagadGateway($config, $client);

        $request = new PaymentRequest(
            amount: 1500.0,
            invoiceId: 'INV-NAGAD-1'
        );

        $response = $gateway->createPayment($request);

        $this->assertEquals(PaymentStatus::PENDING, $response->status);
        $this->assertEquals('NAGAD_REF_1001', $response->paymentId);
        $this->assertEquals('http://sandbox.mynagad.com/checkout/NAGAD_REF_1001', $response->redirectUrl);
    }

    public function test_nagad_verify_payment()
    {
        $mock = new MockHandler([
            // GET verify/payment/{id} Response
            new Response(200, [], json_encode([
                'status' => 'SUCCESS',
                'paymentRefId' => 'NAGAD_REF_1001',
                'issuerPaymentRefNo' => 'TRX_NAGAD_888',
                'orderId' => 'INV-NAGAD-1',
                'amount' => '1500.00',
                'message' => 'Payment Successful',
            ])),
        ]);

        $handlerStack = HandlerStack::create($mock);
        $client = new Client(['handler' => $handlerStack]);

        $config = config('unipay.gateways.nagad');
        $gateway = new NagadGateway($config, $client);

        $response = $gateway->queryPayment('NAGAD_REF_1001');

        $this->assertTrue($response->isSuccessful());
        $this->assertEquals('TRX_NAGAD_888', $response->transactionId);
        $this->assertEquals(1500.0, $response->amount);
    }
}
