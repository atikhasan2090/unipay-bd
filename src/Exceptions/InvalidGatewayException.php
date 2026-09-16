<?php

namespace Unipay\BD\Exceptions;

class InvalidGatewayException extends UnipayException
{
    public static function make(string $name): self
    {
        return new self("Payment gateway driver [{$name}] is not supported.");
    }
}
