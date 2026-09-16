<?php

namespace Unipay\BD;

use Illuminate\Support\Manager;
use Unipay\BD\Contracts\GatewayInterface;
use Unipay\BD\Exceptions\InvalidGatewayException;
use Unipay\BD\Gateways\BkashGateway;
use Unipay\BD\Gateways\CellfinGateway;
use Unipay\BD\Gateways\NagadGateway;
use Unipay\BD\Gateways\RocketGateway;
use Unipay\BD\Gateways\ShurjopayGateway;
use Unipay\BD\Gateways\SslcommerzGateway;
use Unipay\BD\Gateways\UpayGateway;

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

    protected function createRocketDriver(): GatewayInterface
    {
        $config = $this->config->get('unipay.gateways.rocket', []);
        return new RocketGateway($config);
    }

    protected function createUpayDriver(): GatewayInterface
    {
        $config = $this->config->get('unipay.gateways.upay', []);
        return new UpayGateway($config);
    }

    protected function createCellfinDriver(): GatewayInterface
    {
        $config = $this->config->get('unipay.gateways.cellfin', []);
        return new CellfinGateway($config);
    }

    protected function createSslcommerzDriver(): GatewayInterface
    {
        $config = $this->config->get('unipay.gateways.sslcommerz', []);
        return new SslcommerzGateway($config);
    }

    protected function createShurjopayDriver(): GatewayInterface
    {
        $config = $this->config->get('unipay.gateways.shurjopay', []);
        return new ShurjopayGateway($config);
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
