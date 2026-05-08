<?php
/**
 * Minimal PayHub HTTP client for OpenCart. Avoids the per-extension
 * Composer install path by inlining the methods we need
 * (payments.create / confirmOtp / refund / retrieve, webhook
 * verification). Use the official `payhub/payhub` SDK in
 * Composer-managed deployments instead.
 */

namespace Opencart\System\Library\Payhub;

class PayhubClient {
    public function __construct(
        private string $apiKey,
        private string $baseUrl = 'https://app.payhub.ly',
    ) {}

    public function createPayment(array $req, string $idempotencyKey): array {
        return $this->request('POST', '/v1/payments', $req, $idempotencyKey);
    }

    public function confirmOtp(string $paymentId, string $code, string $idempotencyKey): array {
        return $this->request('POST', '/v1/payments/' . rawurlencode($paymentId) . '/otp', ['code' => $code], $idempotencyKey);
    }

    public function refund(string $paymentId, ?int $amountMinor, ?string $reason, string $idempotencyKey): array {
        $body = [];
        if ($amountMinor !== null) $body['amount_minor'] = $amountMinor;
        if ($reason !== null)      $body['reason'] = $reason;
        return $this->request('POST', '/v1/payments/' . rawurlencode($paymentId) . '/refund', $body, $idempotencyKey);
    }

    public function retrieve(string $paymentId): array {
        return $this->request('GET', '/v1/payments/' . rawurlencode($paymentId), null, null);
    }

    private function request(string $method, string $path, ?array $body, ?string $idempotencyKey): array {
        $ch = curl_init($this->baseUrl . $path);
        $headers = [
            'Authorization: Bearer ' . $this->apiKey,
            'Accept: application/json',
            'User-Agent: payhub-opencart/0.2.0',
        ];
        if ($body !== null) {
            $headers[] = 'Content-Type: application/json';
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($body, JSON_UNESCAPED_SLASHES));
        }
        if ($idempotencyKey !== null) {
            $headers[] = 'Idempotency-Key: ' . $idempotencyKey;
        }
        curl_setopt_array($ch, [
            CURLOPT_CUSTOMREQUEST => $method,
            CURLOPT_HTTPHEADER => $headers,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 30,
            CURLOPT_FAILONERROR => false,
        ]);
        $resp = curl_exec($ch);
        $status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $err = curl_error($ch);
        curl_close($ch);
        if ($resp === false) {
            throw new \RuntimeException("PayHub network error: {$err}");
        }
        $decoded = $resp === '' ? [] : (json_decode((string) $resp, true) ?? []);
        if ($status < 200 || $status >= 300) {
            $msg = is_array($decoded) && isset($decoded['error']['message']) ? (string) $decoded['error']['message'] : (string) $resp;
            throw new \RuntimeException("PayHub HTTP {$status}: {$msg}");
        }
        return is_array($decoded) ? $decoded : [];
    }

    public static function verifyWebhook(string $secretBin, string $body, string $header, int $toleranceSeconds = 300): array {
        $parts = [];
        foreach (explode(',', $header) as $seg) {
            if (!str_contains($seg, '=')) continue;
            [$k, $v] = explode('=', $seg, 2);
            $parts[trim($k)] = trim($v);
        }
        if (!isset($parts['t'], $parts['v1']) || !ctype_digit($parts['t'])) {
            throw new \RuntimeException('malformed Hub-Signature');
        }
        $skew = abs(time() - (int) $parts['t']);
        if ($skew > $toleranceSeconds) {
            throw new \RuntimeException("timestamp out of tolerance: {$skew}s");
        }
        $expected = hash_hmac('sha256', $parts['t'] . '.' . $body, $secretBin);
        if (!hash_equals($expected, $parts['v1'])) {
            throw new \RuntimeException('signature mismatch');
        }
        $decoded = json_decode($body, true);
        if (!is_array($decoded)) {
            throw new \RuntimeException('webhook body is not a JSON object');
        }
        return $decoded;
    }
}
