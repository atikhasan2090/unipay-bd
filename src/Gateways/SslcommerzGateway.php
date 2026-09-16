<?php

namespace Unipay\BD\Gateways;

use Unipay\BD\DTOs\PaymentRequest;
use Unipay\BD\DTOs\PaymentResponse;
use Unipay\BD\DTOs\RefundRequest;
use Unipay\BD\DTOs\RefundResponse;
use Unipay\BD\Enums\PaymentStatus;

class SslcommerzGateway extends AbstractGateway
{
    protected string $gatewayName = 'sslcommerz';

    public function createPayment(PaymentRequest $request): PaymentResponse
    {
        $url = $this->getEndpoint('gwprocess/v4/api.php');
        $callbackUrl = $request->callbackUrl ?? url('/unipay/callback/sslcommerz');

        $payload = [
            'store_id' => $this->config['store_id'] ?? '',
            'store_passwd' => $this->config['store_password'] ?? '',
            'total_amount' => number_format($request->amount, 2, '.', ''),
            'currency' => $request->currency ?? $this->config['currency'] ?? 'BDT',
            'tran_id' => $request->invoiceId,
            'success_url' => $callbackUrl,
            'fail_url' => $callbackUrl,
            'cancel_url' => $callbackUrl,
            'cus_name' => 'Customer',
            'cus_email' => 'customer@example.com',
            'cus_add1' => 'Dhaka',
            'cus_city' => 'Dhaka',
            'cus_postcode' => '1000',
            'cus_country' => 'Bangladesh',
            'cus_phone' => $request->customerMobile ?? '01700000000',
            'shipping_method' => 'NO',
            'product_name' => 'Order Payment',
            'product_category' => 'General',
            'product_profile' => 'general',
        ];

        $res = $this->httpClient->post($url, [
            'form_params' => $payload,
        ]);

        $data = json_decode($res->getBody()->getContents(), true);

        $statusStr = strtoupper($data['status'] ?? '');
        $redirectUrl = $data['GatewayPageURL'] ?? null;
        $sessionKey = $data['sessionkey'] ?? null;

        $response = new PaymentResponse(
            status: ($statusStr === 'SUCCESS' && $redirectUrl) ? PaymentStatus::PENDING : PaymentStatus::FAILED,
            paymentId: $sessionKey,
            invoiceId: $request->invoiceId,
            amount: $request->amount,
            currency: $payload['currency'],
            redirectUrl: $redirectUrl,
            message: $data['failedreason'] ?? ($redirectUrl ? 'SSLCommerz payment session created.' : 'SSLCommerz session creation failed.'),
            rawResponse: $data ?? [],
            gatewayName: $this->gatewayName
        );

        $this->logTransaction($response, $request->invoiceId, $this->gatewayName);

        return $response;
    }

    public function executePayment(string $paymentId, array $additionalParams = []): PaymentResponse
    {
        $valId = $additionalParams['val_id'] ?? $paymentId;
        return $this->queryPayment($valId);
    }

    public function queryPayment(string $paymentId): PaymentResponse
    {
        $url = $this->getEndpoint('validator/api/validationserverAPI.php');

        $res = $this->httpClient->get($url, [
            'query' => [
                'val_id' => $paymentId,
                'store_id' => $this->config['store_id'] ?? '',
                'store_passwd' => $this->config['store_password'] ?? '',
                'format' => 'json',
            ],
        ]);

        $data = json_decode($res->getBody()->getContents(), true);

        $statusStr = strtoupper($data['status'] ?? '');
        $isSuccess = ($statusStr === 'VALID' || $statusStr === 'VALIDATED');

        $response = new PaymentResponse(
            status: $isSuccess ? PaymentStatus::SUCCESS : PaymentStatus::FAILED,
            paymentId: $data['val_id'] ?? $paymentId,
            transactionId: $data['bank_tran_id'] ?? ($data['tran_id'] ?? null),
            invoiceId: $data['tran_id'] ?? null,
            amount: isset($data['amount']) ? (float) $data['amount'] : 0.0,
            currency: $data['currency'] ?? 'BDT',
            message: $data['status'] ?? ($isSuccess ? 'SSLCommerz payment validated.' : 'SSLCommerz payment validation failed.'),
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
        $url = $this->getEndpoint('validator/api/merchantTransAPI.php');

        $res = $this->httpClient->get($url, [
            'query' => [
                'refund_amount' => number_format($request->amount, 2, '.', ''),
                'refund_remarks' => $request->reason,
                'bank_tran_id' => $request->transactionId,
                'refe_id' => $request->paymentId,
                'store_id' => $this->config['store_id'] ?? '',
                'store_passwd' => $this->config['store_password'] ?? '',
                'format' => 'json',
            ],
        ]);

        $data = json_decode($res->getBody()->getContents(), true);

        $isSuccess = strtoupper($data['status'] ?? '') === 'SUCCESS';

        return new RefundResponse(
            status: $isSuccess ? PaymentStatus::REFUNDED : PaymentStatus::FAILED,
            refundTransactionId: $data['refund_ref_id'] ?? null,
            amount: $request->amount,
            message: $data['errorReason'] ?? ($isSuccess ? 'SSLCommerz refund submitted.' : 'SSLCommerz refund failed.'),
            rawResponse: $data ?? []
        );
    }
}
