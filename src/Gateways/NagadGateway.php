<?php

namespace Unipay\BD\Gateways;

use Unipay\BD\Gateways\AbstractGateway;
use Unipay\BD\DTOs\PaymentRequest;
use Unipay\BD\DTOs\PaymentResponse;
use Unipay\BD\DTOs\RefundRequest;
use Unipay\BD\DTOs\RefundResponse;
use Unipay\BD\Enums\PaymentStatus;
use Unipay\BD\Exceptions\NagadEncryptionException;

class NagadGateway extends AbstractGateway
{
    protected string $gatewayName = 'nagad';

    /**
     * Encrypt plaintext string using Nagad PGW Public Key.
     */
    public function encryptPublicKey(string $data): string
    {
        $publicKey = $this->config['public_key'] ?? '';
        $formattedKey = $this->formatPublicKey($publicKey);

        $pubKeyResource = openssl_pkey_get_public($formattedKey);
        $details = $pubKeyResource ? openssl_pkey_get_details($pubKeyResource) : null;
        $keySizeBytes = isset($details['bits']) ? (int) ($details['bits'] / 8) : 128;
        $chunkSize = $keySizeBytes - 11;

        $encrypted = '';
        foreach (str_split($data, $chunkSize) as $chunk) {
            $partial = '';
            $ok = openssl_public_encrypt($chunk, $partial, $formattedKey, OPENSSL_PKCS1_PADDING);
            if (!$ok) {
                throw new NagadEncryptionException('Nagad RSA Public Key encryption failed: ' . openssl_error_string());
            }
            $encrypted .= $partial;
        }

        return base64_encode($encrypted);
    }

    /**
     * Decrypt base64 cipher text using Merchant Private Key.
     */
    public function decryptPrivateKey(string $cipherText): string
    {
        $privateKey = $this->config['private_key'] ?? '';
        $formattedKey = $this->formatPrivateKey($privateKey);

        $privKeyResource = openssl_pkey_get_private($formattedKey);
        $details = $privKeyResource ? openssl_pkey_get_details($privKeyResource) : null;
        $keySizeBytes = isset($details['bits']) ? (int) ($details['bits'] / 8) : 128;

        $rawCipher = base64_decode($cipherText);
        $decrypted = '';

        foreach (str_split($rawCipher, $keySizeBytes) as $chunk) {
            $partial = '';
            $ok = openssl_private_decrypt($chunk, $partial, $formattedKey, OPENSSL_PKCS1_PADDING);
            if (!$ok) {
                throw new NagadEncryptionException('Nagad RSA Private Key decryption failed: ' . openssl_error_string());
            }
            $decrypted .= $partial;
        }

        return $decrypted;
    }

    /**
     * Generate RSA SHA256 Signature using Merchant Private Key.
     */
    public function generateSignature(string $data): string
    {
        $privateKey = $this->config['private_key'] ?? '';
        $formattedKey = $this->formatPrivateKey($privateKey);

        $signature = '';
        $ok = openssl_sign($data, $signature, $formattedKey, OPENSSL_ALGO_SHA256);
        if (!$ok) {
            throw new NagadEncryptionException('Nagad RSA Signature generation failed: ' . openssl_error_string());
        }

        return base64_encode($signature);
    }

    private function formatPublicKey(string $key): string
    {
        if (str_contains($key, '-----BEGIN PUBLIC KEY-----')) {
            return $key;
        }
        return "-----BEGIN PUBLIC KEY-----\n" . wordwrap($key, 64, "\n", true) . "\n-----END PUBLIC KEY-----";
    }

    private function formatPrivateKey(string $key): string
    {
        if (str_contains($key, '-----BEGIN RSA PRIVATE KEY-----') || str_contains($key, '-----BEGIN PRIVATE KEY-----')) {
            return $key;
        }
        return "-----BEGIN PRIVATE KEY-----\n" . wordwrap($key, 64, "\n", true) . "\n-----END PRIVATE KEY-----";
    }

    private function generateRandomString(int $length = 40): string
    {
        return bin2hex(random_bytes((int) ceil($length / 2)));
    }

    public function createPayment(PaymentRequest $request): PaymentResponse
    {
        $merchantId = $this->config['merchant_id'] ?? '';
        $merchantNumber = $this->config['merchant_number'] ?? '';
        $dateTime = date('YmdHis');
        $orderId = $request->invoiceId;
        $challenge = $this->generateRandomString(20);

        // Step 1: Initialize payload
        $initData = [
            'merchantId' => $merchantId,
            'datetime' => $dateTime,
            'orderId' => $orderId,
            'challenge' => $challenge,
        ];

        $sensitiveDataJson = json_encode($initData, JSON_UNESCAPED_SLASHES);
        $sensitiveDataEncrypted = $this->encryptPublicKey($sensitiveDataJson);
        $signature = $this->generateSignature($sensitiveDataJson);

        $initUrl = $this->getEndpoint("check-out/initialize/{$merchantId}/{$orderId}");

        $headers = [
            'Content-Type' => 'application/json',
            'X-KM-Api-Version' => 'v-0.2.0',
            'X-KM-IP-V4' => request()->ip() ?? '127.0.0.1',
            'X-KM-Client-Type' => 'PC_WEB',
        ];

        $res = $this->httpClient->post($initUrl, [
            'headers' => $headers,
            'json' => [
                'accountNumber' => $merchantNumber,
                'dateTime' => $dateTime,
                'sensitiveData' => $sensitiveDataEncrypted,
                'signature' => $signature,
            ],
        ]);

        $initResponseBody = json_decode($res->getBody()->getContents(), true);

        if (!isset($initResponseBody['sensitiveData'])) {
            $response = new PaymentResponse(
                status: PaymentStatus::FAILED,
                invoiceId: $orderId,
                amount: $request->amount,
                message: $initResponseBody['reason'] ?? 'Nagad initialization failed.',
                rawResponse: $initResponseBody ?? [],
                gatewayName: $this->gatewayName
            );
            $this->logTransaction($response, $orderId, $this->gatewayName);
            return $response;
        }

        // Decrypt Nagad initialization response
        $decryptedInitDataJson = $this->decryptPrivateKey($initResponseBody['sensitiveData']);
        $decryptedInitData = json_decode($decryptedInitDataJson, true);

        $paymentReferenceId = $decryptedInitData['paymentReferenceId'] ?? null;
        $nagadChallenge = $decryptedInitData['challenge'] ?? null;

        if (!$paymentReferenceId) {
            $response = new PaymentResponse(
                status: PaymentStatus::FAILED,
                invoiceId: $orderId,
                amount: $request->amount,
                message: 'Nagad payment reference ID missing from decrypted payload.',
                rawResponse: $decryptedInitData ?? [],
                gatewayName: $this->gatewayName
            );
            $this->logTransaction($response, $orderId, $this->gatewayName);
            return $response;
        }

        // Step 2: Complete Checkout Initialization
        $callbackUrl = $request->callbackUrl ?? url('/unipay/callback/nagad');

        $completeSensitiveData = [
            'merchantId' => $merchantId,
            'orderId' => $orderId,
            'currencyCode' => '050', // BDT code
            'amount' => number_format($request->amount, 2, '.', ''),
            'challenge' => $nagadChallenge,
            'callbackUrl' => $callbackUrl,
        ];

        $completeJson = json_encode($completeSensitiveData, JSON_UNESCAPED_SLASHES);
        $completeEncrypted = $this->encryptPublicKey($completeJson);
        $completeSignature = $this->generateSignature($completeJson);

        $completeUrl = $this->getEndpoint("check-out/complete/{$paymentReferenceId}");

        $completeRes = $this->httpClient->post($completeUrl, [
            'headers' => $headers,
            'json' => [
                'merchantId' => $merchantId,
                'orderId' => $orderId,
                'currencyCode' => '050',
                'amount' => number_format($request->amount, 2, '.', ''),
                'challenge' => $nagadChallenge,
                'sensitiveData' => $completeEncrypted,
                'signature' => $completeSignature,
            ],
        ]);

        $completeResponseBody = json_decode($completeRes->getBody()->getContents(), true);

        $redirectUrl = $completeResponseBody['callBackUrl'] ?? null;

        $response = new PaymentResponse(
            status: $redirectUrl ? PaymentStatus::PENDING : PaymentStatus::FAILED,
            paymentId: $paymentReferenceId,
            invoiceId: $orderId,
            amount: $request->amount,
            currency: 'BDT',
            redirectUrl: $redirectUrl,
            message: $completeResponseBody['message'] ?? ($redirectUrl ? 'Nagad checkout initialized.' : 'Nagad checkout failed.'),
            rawResponse: $completeResponseBody,
            gatewayName: $this->gatewayName
        );

        $this->logTransaction($response, $orderId, $this->gatewayName);

        return $response;
    }

    public function executePayment(string $paymentId, array $additionalParams = []): PaymentResponse
    {
        return $this->queryPayment($paymentId);
    }

    public function queryPayment(string $paymentId): PaymentResponse
    {
        $url = $this->getEndpoint("verify/payment/{$paymentId}");

        $res = $this->httpClient->get($url, [
            'headers' => [
                'Content-Type' => 'application/json',
                'X-KM-Api-Version' => 'v-0.2.0',
            ],
        ]);

        $data = json_decode($res->getBody()->getContents(), true);

        $statusStr = strtoupper($data['status'] ?? '');
        $isSuccess = ($statusStr === 'SUCCESS' || $statusStr === 'COMPLETED');

        $response = new PaymentResponse(
            status: $isSuccess ? PaymentStatus::SUCCESS : PaymentStatus::FAILED,
            paymentId: $paymentId,
            transactionId: $data['issuerPaymentRefNo'] ?? ($data['paymentRefId'] ?? null),
            invoiceId: $data['orderId'] ?? null,
            amount: isset($data['amount']) ? (float) $data['amount'] : 0.0,
            currency: 'BDT',
            message: $data['message'] ?? ($isSuccess ? 'Nagad payment verified successfully.' : 'Nagad payment failed.'),
            rawResponse: $data,
            gatewayName: $this->gatewayName
        );

        if (!empty($data['orderId'])) {
            $this->logTransaction($response, $data['orderId'], $this->gatewayName);
        }

        return $response;
    }

    public function refund(RefundRequest $request): RefundResponse
    {
        // Nagad Merchant Refund Endpoint
        $url = $this->getEndpoint("purchase/refund");

        $merchantId = $this->config['merchant_id'] ?? '';
        $dateTime = date('YmdHis');

        $refundData = [
            'merchantId' => $merchantId,
            'originalOrderNo' => $request->invoiceId ?? $request->paymentId,
            'refundAmount' => number_format($request->amount, 2, '.', ''),
            'refundReason' => $request->reason,
            'datetime' => $dateTime,
        ];

        $refundJson = json_encode($refundData, JSON_UNESCAPED_SLASHES);
        $encrypted = $this->encryptPublicKey($refundJson);
        $signature = $this->generateSignature($refundJson);

        $res = $this->httpClient->post($url, [
            'headers' => [
                'Content-Type' => 'application/json',
                'X-KM-Api-Version' => 'v-0.2.0',
            ],
            'json' => [
                'merchantId' => $merchantId,
                'sensitiveData' => $encrypted,
                'signature' => $signature,
            ],
        ]);

        $data = json_decode($res->getBody()->getContents(), true);

        $isSuccess = strtoupper($data['status'] ?? '') === 'SUCCESS';

        return new RefundResponse(
            status: $isSuccess ? PaymentStatus::REFUNDED : PaymentStatus::FAILED,
            refundTransactionId: $data['refundRefNo'] ?? null,
            amount: $request->amount,
            message: $data['message'] ?? ($isSuccess ? 'Nagad refund successful.' : 'Nagad refund failed.'),
            rawResponse: $data
        );
    }
}
