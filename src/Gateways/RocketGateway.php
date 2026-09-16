<?php

namespace Unipay\BD\Gateways;

use Unipay\BD\DTOs\PaymentRequest;
use Unipay\BD\DTOs\PaymentResponse;
use Unipay\BD\DTOs\RefundRequest;
use Unipay\BD\DTOs\RefundResponse;
use Unipay\BD\Enums\PaymentStatus;

class RocketGateway extends AbstractGateway
{
    protected string $gatewayName = 'rocket';

    public function createPayment(PaymentRequest $request): PaymentResponse
    {
        $url = $this->getEndpoint('payment/initiate');
        $callbackUrl = $request->callbackUrl ?? url('/unipay/callback/rocket');

        $payload = [
            'merchant_id' => $this->config['merchant_id'] ?? '',
            'terminal_id' => $this->config['terminal_id'] ?? '',
            'password' => $this->config['password'] ?? '',
            'invoice_id' => $request->invoiceId,
            'amount' => number_format($request->amount, 2, '.', ''),
            'currency' => $request->currency ?? $this->config['currency'] ?? 'BDT',
            'customer_mobile' => $request->customerMobile ?? '',
            'redirect_url' => $callbackUrl,
        ];

        $res = $this->httpClient->post($url, [
            'headers' => ['Content-Type' => 'application/json'],
            'json' => $payload,
        ]);

        $data = json_decode($res->getBody()->getContents(), true);

        $isPending = ($data['status_code'] ?? '') === '200' || isset($data['payment_url']);

        $response = new PaymentResponse(
            status: $isPending ? PaymentStatus::PENDING : PaymentStatus::FAILED,
            paymentId: $data['payment_id'] ?? null,
            invoiceId: $request->invoiceId,
            amount: $request->amount,
            currency: $payload['currency'],
            redirectUrl: $data['payment_url'] ?? null,
            message: $data['message'] ?? ($isPending ? 'Rocket payment initiated.' : 'Rocket payment initiation failed.'),
            rawResponse: $data ?? [],
            gatewayName: $this->gatewayName
        );

        $this->logTransaction($response, $request->invoiceId, $this->gatewayName);

        return $response;
    }

    public function executePayment(string $paymentId, array $additionalParams = []): PaymentResponse
    {
        return $this->queryPayment($paymentId);
    }

    public function queryPayment(string $paymentId): PaymentResponse
    {
        $url = $this->getEndpoint('payment/verify');

        $res = $this->httpClient->post($url, [
            'headers' => ['Content-Type' => 'application/json'],
            'json' => [
                'merchant_id' => $this->config['merchant_id'] ?? '',
                'password' => $this->config['password'] ?? '',
                'payment_id' => $paymentId,
            ],
        ]);

        $data = json_decode($res->getBody()->getContents(), true);

        $isSuccess = ($data['status_code'] ?? '') === '200' && ($data['transaction_status'] ?? '') === 'SUCCESS';

        $response = new PaymentResponse(
            status: $isSuccess ? PaymentStatus::SUCCESS : PaymentStatus::FAILED,
            paymentId: $paymentId,
            transactionId: $data['transaction_id'] ?? null,
            invoiceId: $data['invoice_id'] ?? null,
            amount: isset($data['amount']) ? (float) $data['amount'] : 0.0,
            currency: 'BDT',
            message: $data['message'] ?? ($isSuccess ? 'Rocket payment verified.' : 'Rocket payment verification failed.'),
            rawResponse: $data ?? [],
            gatewayName: $this->gatewayName
        );

        if (!empty($data['invoice_id'])) {
            $this->logTransaction($response, $data['invoice_id'], $this->gatewayName);
        }

        return $response;
    }

    public function refund(RefundRequest $request): RefundResponse
    {
        $url = $this->getEndpoint('payment/refund');

        $res = $this->httpClient->post($url, [
            'headers' => ['Content-Type' => 'application/json'],
            'json' => [
                'merchant_id' => $this->config['merchant_id'] ?? '',
                'password' => $this->config['password'] ?? '',
                'payment_id' => $request->paymentId,
                'transaction_id' => $request->transactionId,
                'amount' => number_format($request->amount, 2, '.', ''),
                'reason' => $request->reason,
            ],
        ]);

        $data = json_decode($res->getBody()->getContents(), true);

        $isSuccess = ($data['status_code'] ?? '') === '200';

        return new RefundResponse(
            status: $isSuccess ? PaymentStatus::REFUNDED : PaymentStatus::FAILED,
            refundTransactionId: $data['refund_id'] ?? null,
            amount: $request->amount,
            message: $data['message'] ?? ($isSuccess ? 'Rocket refund successful.' : 'Rocket refund failed.'),
            rawResponse: $data ?? []
        );
    }
}
