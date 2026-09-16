<?php

namespace Unipay\BD\Tests\Unit;

use Unipay\BD\DTOs\PaymentRequest;
use Unipay\BD\DTOs\PaymentResponse;

use Unipay\BD\Enums\PaymentStatus;
use Unipay\BD\Tests\TestCase;

class DtoTest extends TestCase
{
    public function test_payment_request_dto_serializes_correctly()
    {
        $request = new PaymentRequest(
            amount: 500.0,
            invoiceId: 'INV-1001',
            callbackUrl: 'https://example.com/callback',
            customerMobile: '01711111111'
        );

        $array = $request->toArray();
        $this->assertEquals(500.0, $array['amount']);
        $this->assertEquals('INV-1001', $array['invoice_id']);
        $this->assertEquals('https://example.com/callback', $array['callback_url']);
        $this->assertEquals('01711111111', $array['customer_mobile']);
    }

    public function test_payment_response_dto_helper_methods()
    {
        $successResponse = new PaymentResponse(
            status: PaymentStatus::SUCCESS,
            transactionId: 'TRX12345',
            amount: 500.0
        );

        $this->assertTrue($successResponse->isSuccessful());
        $this->assertFalse($successResponse->isFailed());

        $failedResponse = new PaymentResponse(
            status: PaymentStatus::FAILED,
            message: 'Error'
        );

        $this->assertFalse($failedResponse->isSuccessful());
        $this->assertTrue($failedResponse->isFailed());
    }
}
