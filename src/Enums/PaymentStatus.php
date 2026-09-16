<?php

namespace Unipay\BD\Enums;

enum PaymentStatus: string
{
    case SUCCESS = 'success';
    case PENDING = 'pending';
    case FAILED = 'failed';
    case CANCELLED = 'cancelled';
    case REFUNDED = 'refunded';

    public function isCompleted(): bool
    {
        return $this === self::SUCCESS;
    }
}
