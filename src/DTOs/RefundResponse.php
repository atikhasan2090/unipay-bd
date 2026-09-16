<?php

namespace Unipay\BD\DTOs;

use Unipay\BD\Enums\PaymentStatus;

class RefundResponse
{
    public function __construct(
        public readonly PaymentStatus $status,
        public readonly ?string $refundTransactionId = null,
        public readonly float $amount = 0.0,
        public readonly string $message = '',
        public readonly array $rawResponse = []
    ) {
    }

    public function isSuccessful(): bool
    {
        return $this->status === PaymentStatus::REFUNDED || $this->status === PaymentStatus::SUCCESS;
    }

    public function toArray(): array
    {
        return [
            'status' => $this->status->value,
            'refund_transaction_id' => $this->refundTransactionId,
            'amount' => $this->amount,
            'message' => $this->message,
            'raw_response' => $this->rawResponse,
        ];
    }
}
