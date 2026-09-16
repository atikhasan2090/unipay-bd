<?php

namespace Unipay\BD\Gateways;

use Unipay\BD\DTOs\PaymentRequest;
use Unipay\BD\DTOs\PaymentResponse;
use Unipay\BD\DTOs\RefundRequest;
use Unipay\BD\DTOs\RefundResponse;
use Unipay\BD\Enums\PaymentStatus;

class CellfinGateway extends AbstractGateway
{
    protected string $gatewayName = 'cellfin';

    public function createPayment(PaymentRequest $request): PaymentResponse
    {
        $url = $this->getEndpoint('checkout/create');
        $callbackUrl = $request->callbackUrl ?? url('/unipay/callback/cellfin');

        $payload = [
            'merchant_id' => $this->config['merchant_id'] ?? '',
            'store_id' => $this->config['store_id'] ?? '',
            'secret_key' => $this->config['secret_key'] ?? '',
            'reference_id' => $request->invoiceId,
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

        $paymentUrl = $data['redirect_url'] ?? $data['payment_url'] ?? null;
        $paymentId = $data['cellfin_ref_id'] ?? $data['reference_id'] ?? null;

        $response = new PaymentResponse(
            status: $paymentUrl ? PaymentStatus::PENDING : PaymentStatus::FAILED,
            paymentId: $paymentId,
            invoiceId: $request->invoiceId,
            amount: $request->amount,
            currency: $payload['currency'],
            redirectUrl: $paymentUrl,
            message: $data['message'] ?? ($paymentUrl ? 'CellFin payment initiated.' : 'CellFin payment initiation failed.'),
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
        $url = $this->getEndpoint('checkout/verify');

        $res = $this->httpClient->post($url, [
            'headers' => ['Content-Type' => 'application/json'],
            'json' => [
                'merchant_id' => $this->config['merchant_id'] ?? '',
                'secret_key' => $this->config['secret_key'] ?? '',
                'cellfin_ref_id' => $paymentId,
            ],
        ]);

        $data = json_decode($res->getBody()->getContents(), true);

        $isSuccess = ($data['status'] ?? '') === 'SUCCESS' || ($data['code'] ?? '') === '200';

        $response = new PaymentResponse(
            status: $isSuccess ? PaymentStatus::SUCCESS : PaymentStatus::FAILED,
            paymentId: $paymentId,
            transactionId: $data['trx_id'] ?? null,
            invoiceId: $data['reference_id'] ?? null,
            amount: isset($data['amount']) ? (float) $data['amount'] : 0.0,
            currency: 'BDT',
            message: $data['message'] ?? ($isSuccess ? 'CellFin payment verified.' : 'CellFin payment verification failed.'),
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
        $url = $this->getEndpoint('checkout/refund');

        $res = $this->httpClient->post($url, [
            'headers' => ['Content-Type' => 'application/json'],
            'json' => [
                'merchant_id' => $this->config['merchant_id'] ?? '',
                'secret_key' => $this->config['secret_key'] ?? '',
                'cellfin_ref_id' => $request->paymentId,
                'trx_id' => $request->transactionId,
                'amount' => number_format($request->amount, 2, '.', ''),
                'reason' => $request->reason,
            ],
        ]);

        $data = json_decode($res->getBody()->getContents(), true);

        $isSuccess = ($data['status'] ?? '') === 'SUCCESS' || ($data['code'] ?? '') === '200';

        return new RefundResponse(
            status: $isSuccess ? PaymentStatus::REFUNDED : PaymentStatus::FAILED,
            refundTransactionId: $data['refund_ref_id'] ?? null,
            amount: $request->amount,
            message: $data['message'] ?? ($isSuccess ? 'CellFin refund successful.' : 'CellFin refund failed.'),
            rawResponse: $data ?? []
        );
    }
}
