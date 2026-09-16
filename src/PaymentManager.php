<?php

namespace Unipay\BD;

use Illuminate\Support\Manager;
use Unipay\BD\Contracts\GatewayInterface;
use Unipay\BD\Exceptions\InvalidGatewayException;
use Unipay\BD\Gateways\BkashGateway;
use Unipay\BD\Gateways\NagadGateway;

class PaymentManager extends Manager
{
    public function getDefaultDriver(): string
    {
        return $this->config->get('unipay.default', 'bkash');
    }

    protected function createBkashDriver(): GatewayInterface
    {
        $config = $this->config->get('unipay.gateways.bkash', []);
        return new BkashGateway($config);
    }

    protected function createNagadDriver(): GatewayInterface
    {
        $config = $this->config->get('unipay.gateways.nagad', []);
        return new NagadGateway($config);
    }

    /**
     * Resolve custom driver or throw InvalidGatewayException if missing.
     */
    protected function createDriver($driver)
    {
        if (isset($this->customCreators[$driver])) {
            return $this->callCustomCreator($driver);
        }

        $method = 'create' . ucfirst($driver) . 'Driver';

        if (method_exists($this, $method)) {
            return $this->{$method}();
        }

        throw InvalidGatewayException::make($driver);
    }
}
