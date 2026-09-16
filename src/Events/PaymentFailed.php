<?php

namespace Unipay\BD\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use Unipay\BD\DTOs\PaymentResponse;
use Unipay\BD\Models\Transaction;

class PaymentFailed
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public readonly PaymentResponse $response,
        public readonly ?Transaction $transaction = null
    ) {
    }
}
