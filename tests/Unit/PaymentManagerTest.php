<?php

namespace Unipay\BD\Tests\Unit;

use Unipay\BD\Contracts\GatewayInterface;
use Unipay\BD\Exceptions\InvalidGatewayException;
use Unipay\BD\Facades\Payment;
use Unipay\BD\Gateways\BkashGateway;
use Unipay\BD\Gateways\NagadGateway;
use Unipay\BD\PaymentManager;
use Unipay\BD\Tests\TestCase;

class PaymentManagerTest extends TestCase
{
    public function test_it_resolves_default_bkash_driver()
    {
        $driver = Payment::driver();
        $this->assertInstanceOf(GatewayInterface::class, $driver);
        $this->assertInstanceOf(BkashGateway::class, $driver);
    }

    public function test_it_resolves_nagad_driver_explicitly()
    {
        $driver = Payment::driver('nagad');
        $this->assertInstanceOf(GatewayInterface::class, $driver);
        $this->assertInstanceOf(NagadGateway::class, $driver);
    }

    public function test_it_throws_exception_for_invalid_driver()
    {
        $this->expectException(InvalidGatewayException::class);
        Payment::driver('unsupported_gateway');
    }
}
