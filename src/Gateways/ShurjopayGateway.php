<?php

namespace Unipay\BD\Gateways;

use Unipay\BD\DTOs\PaymentRequest;
use Unipay\BD\DTOs\PaymentResponse;
use Unipay\BD\DTOs\RefundRequest;
use Unipay\BD\DTOs\RefundResponse;
use Unipay\BD\Enums\PaymentStatus;

class ShurjopayGateway extends AbstractGateway
{
    protected string $gatewayName = 'shurjopay';

    public function getToken(): array
    {
        $url = $this->getEndpoint('get_token');

        $res = $this->httpClient->post($url, [
            'headers' => ['Content-Type' => 'application/json'],
            'json' => [
                'username' => $this->config['username'] ?? 'sp_sandbox',
                'password' => $this->config['password'] ?? 'pyRcsawValidated',
            ],
        ]);

        $data = json_decode($res->getBody()->getContents(), true);

        return [
            'token' => $data['token'] ?? '',
            'store_id' => $data['store_id'] ?? '',
        ];
    }

    public function createPayment(PaymentRequest $request): PaymentResponse
    {
        $auth = $this->getToken();
        $token = $auth['token'];

        $url = $this->getEndpoint('secret-pay');
        $callbackUrl = $request->callbackUrl ?? url('/unipay/callback/shurjopay');
        $prefix = $this->config['prefix'] ?? 'NOK';

        $payload = [
            'prefix' => $prefix,
            'token' => $token,
            'return_url' => $callbackUrl,
            'cancel_url' => $callbackUrl,
            'store_id' => $auth['store_id'],
            'amount' => number_format($request->amount, 2, '.', ''),
            'order_id' => $request->invoiceId,
            'currency' => $request->currency ?? $this->config['currency'] ?? 'BDT',
            'customer_name' => 'Customer',
            'customer_address' => 'Dhaka',
            'customer_email' => 'customer@example.com',
            'customer_phone' => $request->customerMobile ?? '01700000000',
            'customer_city' => 'Dhaka',
            'client_ip' => request()->ip() ?? '127.0.0.1',
        ];

        $res = $this->httpClient->post($url, [
            'headers' => [
                'Content-Type' => 'application/json',
                'Authorization' => 'Bearer ' . $token,
            ],
            'json' => $payload,
        ]);

        $data = json_decode($res->getBody()->getContents(), true);

        $checkoutUrl = $data['checkout_url'] ?? null;
        $spOrderUrl = $data['sp_order_id'] ?? null;

        $response = new PaymentResponse(
            status: $checkoutUrl ? PaymentStatus::PENDING : PaymentStatus::FAILED,
            paymentId: $spOrderUrl,
            invoiceId: $request->invoiceId,
            amount: $request->amount,
            currency: $payload['currency'],
            redirectUrl: $checkoutUrl,
            message: $data['message'] ?? ($checkoutUrl ? 'Shurjopay checkout URL created.' : 'Shurjopay payment creation failed.'),
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
        $auth = $this->getToken();
        $token = $auth['token'];

        $url = $this->getEndpoint('verification');

        $res = $this->httpClient->post($url, [
            'headers' => [
                'Content-Type' => 'application/json',
                'Authorization' => 'Bearer ' . $token,
            ],
            'json' => [
                'order_id' => $paymentId,
            ],
        ]);

        $data = json_decode($res->getBody()->getContents(), true);

        $item = is_array($data) && isset($data[0]) ? $data[0] : $data;
        $spStatusCode = $item['sp_code'] ?? ($item['sp_status_code'] ?? '');

        $isSuccess = $spStatusCode === '1000' || ($item['sp_massage'] ?? '') === 'Success';

        $response = new PaymentResponse(
            status: $isSuccess ? PaymentStatus::SUCCESS : PaymentStatus::FAILED,
            paymentId: $paymentId,
            transactionId: $item['bank_trx_id'] ?? null,
            invoiceId: $item['customer_order_id'] ?? null,
            amount: isset($item['amount']) ? (float) $item['amount'] : 0.0,
            currency: 'BDT',
            message: $item['sp_massage'] ?? ($isSuccess ? 'Shurjopay payment verified.' : 'Shurjopay payment failed.'),
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
        // Shurjopay refund endpoint
        $auth = $this->getToken();
        $token = $auth['token'];

        $url = $this->getEndpoint('refund');

        $res = $this->httpClient->post($url, [
            'headers' => [
                'Content-Type' => 'application/json',
                'Authorization' => 'Bearer ' . $token,
            ],
            'json' => [
                'bank_trx_id' => $request->transactionId,
                'amount' => number_format($request->amount, 2, '.', ''),
                'remarks' => $request->reason,
            ],
        ]);

        $data = json_decode($res->getBody()->getContents(), true);

        $isSuccess = ($data['sp_code'] ?? '') === '1000';

        return new RefundResponse(
            status: $isSuccess ? PaymentStatus::REFUNDED : PaymentStatus::FAILED,
            refundTransactionId: $data['refund_ref_id'] ?? null,
            amount: $request->amount,
            message: $data['sp_massage'] ?? ($isSuccess ? 'Shurjopay refund successful.' : 'Shurjopay refund failed.'),
            rawResponse: $data ?? []
        );
    }
}
