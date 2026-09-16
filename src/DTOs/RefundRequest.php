<?php

namespace Unipay\BD\DTOs;

class RefundRequest
{
    public function __construct(
        public readonly string $paymentId,
        public readonly string $transactionId,
        public readonly float $amount,
        public readonly string $reason = '',
        public readonly ?string $sku = null,
        public readonly array $additionalData = []
    ) {
    }

    public function toArray(): array
    {
        return [
            'payment_id' => $this->paymentId,
            'transaction_id' => $this->transactionId,
            'amount' => $this->amount,
            'reason' => $this->reason,
            'sku' => $this->sku,
            'additional_data' => $this->additionalData,
        ];
    }
}
