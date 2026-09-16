<?php

namespace Unipay\BD\Gateways;

use Unipay\BD\DTOs\PaymentRequest;
use Unipay\BD\DTOs\PaymentResponse;
use Unipay\BD\DTOs\RefundRequest;
use Unipay\BD\DTOs\RefundResponse;
use Unipay\BD\Enums\PaymentStatus;

class UpayGateway extends AbstractGateway
{
    protected string $gatewayName = 'upay';

    public function getToken(): string
    {
        $url = $this->getEndpoint('auth/token');

        $res = $this->httpClient->post($url, [
            'headers' => ['Content-Type' => 'application/json'],
            'json' => [
                'merchant_id' => $this->config['merchant_id'] ?? '',
                'merchant_key' => $this->config['merchant_key'] ?? '',
                'merchant_code' => $this->config['merchant_code'] ?? '',
                'password' => $this->config['password'] ?? '',
            ],
        ]);

        $data = json_decode($res->getBody()->getContents(), true);

        return $data['data']['token'] ?? $data['token'] ?? '';
    }

    public function createPayment(PaymentRequest $request): PaymentResponse
    {
        $token = $this->getToken();
        $url = $this->getEndpoint('checkout/init');
        $callbackUrl = $request->callbackUrl ?? url('/unipay/callback/upay');

        $payload = [
            'merchant_id' => $this->config['merchant_id'] ?? '',
            'txn_id' => $request->invoiceId,
            'amount' => number_format($request->amount, 2, '.', ''),
            'currency' => $request->currency ?? $this->config['currency'] ?? 'BDT',
            'redirect_url' => $callbackUrl,
            'customer_mobile' => $request->customerMobile ?? '',
        ];

        $res = $this->httpClient->post($url, [
            'headers' => [
                'Content-Type' => 'application/json',
                'Authorization' => 'Bearer ' . $token,
            ],
            'json' => $payload,
        ]);

        $data = json_decode($res->getBody()->getContents(), true);

        $paymentUrl = $data['data']['payment_url'] ?? $data['payment_url'] ?? null;
        $paymentId = $data['data']['trx_id'] ?? $data['trx_id'] ?? null;

        $response = new PaymentResponse(
            status: $paymentUrl ? PaymentStatus::PENDING : PaymentStatus::FAILED,
            paymentId: $paymentId,
            invoiceId: $request->invoiceId,
            amount: $request->amount,
            currency: $payload['currency'],
            redirectUrl: $paymentUrl,
            message: $data['message'] ?? ($paymentUrl ? 'Upay payment initiated.' : 'Upay payment initiation failed.'),
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
        $token = $this->getToken();
        $url = $this->getEndpoint('single-transaction/status/' . $paymentId);

        $res = $this->httpClient->get($url, [
            'headers' => [
                'Content-Type' => 'application/json',
                'Authorization' => 'Bearer ' . $token,
            ],
        ]);

        $data = json_decode($res->getBody()->getContents(), true);

        $statusStr = strtoupper($data['data']['status'] ?? $data['status'] ?? '');
        $isSuccess = ($statusStr === 'SUCCESS' || $statusStr === 'COMPLETED');

        $response = new PaymentResponse(
            status: $isSuccess ? PaymentStatus::SUCCESS : PaymentStatus::FAILED,
            paymentId: $paymentId,
            transactionId: $data['data']['upay_trx_id'] ?? $data['upay_trx_id'] ?? null,
            invoiceId: $data['data']['txn_id'] ?? $data['txn_id'] ?? null,
            amount: isset($data['data']['amount']) ? (float) $data['data']['amount'] : 0.0,
            currency: 'BDT',
            message: $data['message'] ?? ($isSuccess ? 'Upay payment verified.' : 'Upay payment verification failed.'),
            rawResponse: $data ?? [],
            gatewayName: $this->gatewayName
        );

        if (!empty($response->invoiceId)) {
            $this->logTransaction($response, $response->invoiceId, $this->gatewayName);
        }

        return $response;
    }

    public function refund(RefundRequest $request): RefundResponse
    {
        $token = $this->getToken();
        $url = $this->getEndpoint('transaction/refund');

        $res = $this->httpClient->post($url, [
            'headers' => [
                'Content-Type' => 'application/json',
                'Authorization' => 'Bearer ' . $token,
            ],
            'json' => [
                'trx_id' => $request->paymentId,
                'upay_trx_id' => $request->transactionId,
                'amount' => number_format($request->amount, 2, '.', ''),
                'reason' => $request->reason,
            ],
        ]);

        $data = json_decode($res->getBody()->getContents(), true);

        $isSuccess = strtoupper($data['status'] ?? '') === 'SUCCESS';

        return new RefundResponse(
            status: $isSuccess ? PaymentStatus::REFUNDED : PaymentStatus::FAILED,
            refundTransactionId: $data['data']['refund_trx_id'] ?? $data['refund_trx_id'] ?? null,
            amount: $request->amount,
            message: $data['message'] ?? ($isSuccess ? 'Upay refund successful.' : 'Upay refund failed.'),
            rawResponse: $data ?? []
        );
    }
}
