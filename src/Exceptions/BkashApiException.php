<?php

namespace Unipay\BD\Exceptions;

class BkashApiException extends UnipayException
{
    public function __construct(string $message, public readonly array $response = [], int $code = 0)
    {
        parent::__construct($message, $code);
    }
}
