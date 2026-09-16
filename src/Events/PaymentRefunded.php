<?php

namespace Unipay\BD\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use Unipay\BD\DTOs\RefundResponse;
use Unipay\BD\Models\Transaction;

class PaymentRefunded
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public readonly RefundResponse $response,
        public readonly ?Transaction $transaction = null
    ) {
    }
}
