<?php

namespace Unipay\BD\Facades;

use Illuminate\Support\Facades\Facade;
use Unipay\BD\Contracts\GatewayInterface;
use Unipay\BD\DTOs\PaymentRequest;
use Unipay\BD\DTOs\PaymentResponse;
use Unipay\BD\DTOs\RefundRequest;
use Unipay\BD\DTOs\RefundResponse;

/**
 * @method static GatewayInterface driver(string|null $driver = null)
 * @method static PaymentResponse createPayment(PaymentRequest $request)
 * @method static PaymentResponse executePayment(string $paymentId, array $additionalParams = [])
 * @method static PaymentResponse queryPayment(string $paymentId)
 * @method static RefundResponse refund(RefundRequest $request)
 *
 * @see \Unipay\BD\PaymentManager
 */
class Payment extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return 'unipay';
    }
}
