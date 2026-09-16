<?php

namespace Unipay\BD\DTOs;

class PaymentRequest
{
    public function __construct(
        public readonly float $amount,
        public readonly string $invoiceId,
        public readonly ?string $callbackUrl = null,
        public readonly string $currency = 'BDT',
        public readonly ?string $customerMobile = null,
        public readonly array $additionalData = []
    ) {
    }

    public function toArray(): array
    {
        return [
            'amount' => $this->amount,
            'invoice_id' => $this->invoiceId,
            'callback_url' => $this->callbackUrl,
            'currency' => $this->currency,
            'customer_mobile' => $this->customerMobile,
            'additional_data' => $this->additionalData,
        ];
    }
}
