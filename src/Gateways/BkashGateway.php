<?php

namespace Unipay\BD\Gateways;

use Illuminate\Support\Facades\Cache;
use Unipay\BD\Gateways\AbstractGateway;
use Unipay\BD\DTOs\PaymentRequest;
use Unipay\BD\DTOs\PaymentResponse;
use Unipay\BD\DTOs\RefundRequest;
use Unipay\BD\DTOs\RefundResponse;
use Unipay\BD\Enums\PaymentStatus;
use Unipay\BD\Exceptions\BkashApiException;

class BkashGateway extends AbstractGateway
{
    protected string $gatewayName = 'bkash';

    public function getToken(): string
    {
        $cacheKey = 'unipay_bkash_token_' . md5($this->config['app_key'] ?? '');

        return Cache::remember($cacheKey, 3500, function () {
            $url = $this->getEndpoint('tokenized/checkout/token/grant');

            $response = $this->httpClient->post($url, [
                'headers' => [
                    'Content-Type' => 'application/json',
                    'username' => $this->config['username'] ?? '',
                    'password' => $this->config['password'] ?? '',
                ],
                'json' => [
                    'app_key' => $this->config['app_key'] ?? '',
                    'app_secret' => $this->config['app_secret'] ?? '',
                ],
            ]);

            $data = json_decode($response->getBody()->getContents(), true);

            if (!isset($data['id_token'])) {
                $msg = $data['statusMessage'] ?? 'Failed to retrieve bKash grant token.';
                throw new BkashApiException($msg, $data ?? []);
            }

            return $data['id_token'];
        });
    }

    public function createPayment(PaymentRequest $request): PaymentResponse
    {
        $token = $this->getToken();
        $url = $this->getEndpoint('tokenized/checkout/create');

        $callbackUrl = $request->callbackUrl ?? url('/unipay/callback/bkash');

        $payload = [
            'mode' => '0011',
            'payerReference' => $request->customerMobile ?? '01700000000',
            'callbackURL' => $callbackUrl,
            'amount' => number_format($request->amount, 2, '.', ''),
            'currency' => $request->currency ?? $this->config['currency'] ?? 'BDT',
            'intent' => $this->config['intent'] ?? 'sale',
            'merchantInvoiceNumber' => $request->invoiceId,
        ];

        $res = $this->httpClient->post($url, [
            'headers' => [
                'Content-Type' => 'application/json',
                'Authorization' => $token,
                'x-app-key' => $this->config['app_key'] ?? '',
            ],
            'json' => $payload,
        ]);

        $data = json_decode($res->getBody()->getContents(), true);

        if (($data['statusCode'] ?? '') !== '0000') {
            $response = new PaymentResponse(
                status: PaymentStatus::FAILED,
                invoiceId: $request->invoiceId,
                amount: $request->amount,
                message: $data['statusMessage'] ?? 'bKash payment initialization failed.',
                rawResponse: $data ?? [],
                gatewayName: $this->gatewayName
            );
            $this->logTransaction($response, $request->invoiceId, $this->gatewayName);
            return $response;
        }

        $response = new PaymentResponse(
            status: PaymentStatus::PENDING,
            paymentId: $data['paymentID'] ?? null,
            invoiceId: $request->invoiceId,
            amount: $request->amount,
            currency: $payload['currency'],
            redirectUrl: $data['bkashURL'] ?? null,
            message: $data['statusMessage'] ?? 'Payment initialized successfully.',
            rawResponse: $data,
            gatewayName: $this->gatewayName
        );

        $this->logTransaction($response, $request->invoiceId, $this->gatewayName);

        return $response;
    }

    public function executePayment(string $paymentId, array $additionalParams = []): PaymentResponse
    {
        $token = $this->getToken();
        $url = $this->getEndpoint('tokenized/checkout/execute');

        $res = $this->httpClient->post($url, [
            'headers' => [
                'Content-Type' => 'application/json',
                'Authorization' => $token,
                'x-app-key' => $this->config['app_key'] ?? '',
            ],
            'json' => [
                'paymentID' => $paymentId,
            ],
        ]);

        $data = json_decode($res->getBody()->getContents(), true);

        $isSuccess = ($data['statusCode'] ?? '') === '0000';
        $status = $isSuccess ? PaymentStatus::SUCCESS : PaymentStatus::FAILED;

        $invoiceId = $data['merchantInvoiceNumber'] ?? ($additionalParams['invoice_id'] ?? '');

        $response = new PaymentResponse(
            status: $status,
            paymentId: $paymentId,
            transactionId: $data['trxID'] ?? null,
            invoiceId: $invoiceId,
            amount: isset($data['amount']) ? (float) $data['amount'] : 0.0,
            currency: $data['currency'] ?? 'BDT',
            message: $data['statusMessage'] ?? ($isSuccess ? 'Payment executed successfully.' : 'Payment execution failed.'),
            rawResponse: $data,
            gatewayName: $this->gatewayName
        );

        if ($invoiceId) {
            $this->logTransaction($response, $invoiceId, $this->gatewayName);
        }

        return $response;
    }

    public function queryPayment(string $paymentId): PaymentResponse
    {
        $token = $this->getToken();
        $url = $this->getEndpoint('tokenized/checkout/payment/status');

        $res = $this->httpClient->post($url, [
            'headers' => [
                'Content-Type' => 'application/json',
                'Authorization' => $token,
                'x-app-key' => $this->config['app_key'] ?? '',
            ],
            'json' => [
                'paymentID' => $paymentId,
            ],
        ]);

        $data = json_decode($res->getBody()->getContents(), true);

        $transactionStatus = $data['transactionStatus'] ?? '';
        $isSuccess = ($data['statusCode'] ?? '') === '0000' && ($transactionStatus === 'Completed' || $transactionStatus === 'Initiated');

        $response = new PaymentResponse(
            status: $isSuccess ? PaymentStatus::SUCCESS : PaymentStatus::FAILED,
            paymentId: $paymentId,
            transactionId: $data['trxID'] ?? null,
            invoiceId: $data['merchantInvoiceNumber'] ?? null,
            amount: isset($data['amount']) ? (float) $data['amount'] : 0.0,
            currency: $data['currency'] ?? 'BDT',
            message: $data['statusMessage'] ?? 'Payment status query finished.',
            rawResponse: $data,
            gatewayName: $this->gatewayName
        );

        return $response;
    }

    public function refund(RefundRequest $request): RefundResponse
    {
        $token = $this->getToken();
        $url = $this->getEndpoint('tokenized/checkout/payment/refund');

        $res = $this->httpClient->post($url, [
            'headers' => [
                'Content-Type' => 'application/json',
                'Authorization' => $token,
                'x-app-key' => $this->config['app_key'] ?? '',
            ],
            'json' => [
                'paymentID' => $request->paymentId,
                'amount' => number_format($request->amount, 2, '.', ''),
                'trxID' => $request->transactionId,
                'sku' => $request->sku ?? 'unipay_refund',
                'reason' => $request->reason,
            ],
        ]);

        $data = json_decode($res->getBody()->getContents(), true);

        $isSuccess = ($data['statusCode'] ?? '') === '0000';

        return new RefundResponse(
            status: $isSuccess ? PaymentStatus::REFUNDED : PaymentStatus::FAILED,
            refundTransactionId: $data['refundTrxID'] ?? null,
            amount: $request->amount,
            message: $data['statusMessage'] ?? ($isSuccess ? 'Refund processed successfully.' : 'Refund failed.'),
            rawResponse: $data
        );
    }
}
