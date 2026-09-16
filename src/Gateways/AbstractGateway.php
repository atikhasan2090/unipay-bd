<?php

namespace Unipay\BD\Gateways;

use GuzzleHttp\Client;
use Unipay\BD\Contracts\GatewayInterface;
use Unipay\BD\DTOs\PaymentResponse;
use Unipay\BD\Models\Transaction;

abstract class AbstractGateway implements GatewayInterface
{
    protected Client $httpClient;

    public function __construct(protected array $config, ?Client $httpClient = null)
    {
        $this->httpClient = $httpClient ?? new Client([
            'timeout' => 30,
            'connect_timeout' => 10,
        ]);
    }

    protected function getEndpoint(string $path = ''): string
    {
        $isSandbox = $this->config['sandbox'] ?? true;
        $baseUrl = $isSandbox
            ? ($this->config['endpoints']['sandbox'] ?? '')
            : ($this->config['endpoints']['live'] ?? '');

        return rtrim($baseUrl, '/') . '/' . ltrim($path, '/');
    }

    protected function logTransaction(PaymentResponse $response, string $invoiceId, string $gateway): void
    {
        if (!config('unipay.logging.enabled', true)) {
            return;
        }

        try {
            Transaction::updateOrCreate(
                [
                    'invoice_id' => $invoiceId,
                    'gateway' => $gateway,
                ],
                [
                    'payment_id' => $response->paymentId,
                    'transaction_id' => $response->transactionId,
                    'amount' => $response->amount,
                    'currency' => $response->currency,
                    'status' => $response->status->value,
                    'raw_response' => $response->rawResponse,
                ]
            );
        } catch (\Throwable $e) {
            // Silence database logging errors so payment processing flow is never interrupted
            logger()->error("UniPay logging error: " . $e->getMessage());
        }
    }
}
