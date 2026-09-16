<?php

namespace Unipay\BD\Contracts;

use Unipay\BD\DTOs\PaymentRequest;
use Unipay\BD\DTOs\PaymentResponse;
use Unipay\BD\DTOs\RefundRequest;
use Unipay\BD\DTOs\RefundResponse;

interface GatewayInterface
{
    /**
     * Initialize a payment request and get redirect URL / session.
     */
    public function createPayment(PaymentRequest $request): PaymentResponse;

    /**
     * Execute / finalize an initialized payment (e.g. upon user callback).
     */
    public function executePayment(string $paymentId, array $additionalParams = []): PaymentResponse;

    /**
     * Query the status of an existing payment by payment ID or transaction reference.
     */
    public function queryPayment(string $paymentId): PaymentResponse;

    /**
     * Refund a completed transaction.
     */
    public function refund(RefundRequest $request): RefundResponse;
}
