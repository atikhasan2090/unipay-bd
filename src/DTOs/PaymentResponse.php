<?php

namespace Unipay\BD\DTOs;

use Unipay\BD\Enums\PaymentStatus;

class PaymentResponse
{
    public function __construct(
        public readonly PaymentStatus $status,
        public readonly ?string $paymentId = null,
        public readonly ?string $transactionId = null,
        public readonly ?string $invoiceId = null,
        public readonly float $amount = 0.0,
        public readonly string $currency = 'BDT',
        public readonly ?string $redirectUrl = null,
        public readonly string $message = '',
        public readonly array $rawResponse = [],
        public readonly string $gatewayName = ''
    ) {
    }

    public function isSuccessful(): bool
    {
        return $this->status === PaymentStatus::SUCCESS;
    }

    public function isPending(): bool
    {
        return $this->status === PaymentStatus::PENDING;
    }

    public function isFailed(): bool
    {
        return $this->status === PaymentStatus::FAILED;
    }

    public function toArray(): array
    {
        return [
            'status' => $this->status->value,
            'payment_id' => $this->paymentId,
            'transaction_id' => $this->transactionId,
            'invoice_id' => $this->invoiceId,
            'amount' => $this->amount,
            'currency' => $this->currency,
            'redirect_url' => $this->redirectUrl,
            'message' => $this->message,
            'raw_response' => $this->rawResponse,
            'gateway_name' => $this->gatewayName,
        ];
    }
}
