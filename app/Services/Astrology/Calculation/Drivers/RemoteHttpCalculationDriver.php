<?php

declare(strict_types=1);

namespace App\Services\Astrology\Calculation\Drivers;

use App\Services\Astrology\Calculation\Exceptions\CalculationServiceUnavailableException;
use Illuminate\Support\Facades\Log;
use Throwable;

class RemoteHttpCalculationDriver implements CalculationDriverInterface
{
    public function __construct(
        protected string $baseUrl,
        protected string $secret,
        protected float $connectTimeout = 3.0,
        protected float $timeout = 10.0,
        protected bool $fallbackToLocal = false,
        protected ?LocalCalculationDriver $localDriver = null
    ) {
        $this->baseUrl = rtrim($baseUrl, '/');
    }

    /**
     * Send signed HTTP request to remote calculation microservice.
     *
     * @param string $method 'GET' or 'POST'
     * @param string $path E.g. '/calculate/kundli'
     * @param array<string, mixed>|null $data
     * @return array<string, mixed>
     * @throws CalculationServiceUnavailableException
     */
    protected function request(string $method, string $path, ?array $data = null): array
    {
        $url = "{$this->baseUrl}{$path}";
        $rawBody = $data !== null ? json_encode($data, JSON_UNESCAPED_SLASHES) : '';
        $timestamp = (string) time();
        $nonce = bin2hex(random_bytes(16));

        // Canonical string for HMAC: METHOD\nPATH\nTIMESTAMP\nNONCE\nBODY
        $canonical = "{$method}\n{$path}\n{$timestamp}\n{$nonce}\n{$rawBody}";
        $signature = hash_hmac('sha256', $canonical, $this->secret);

        $headers = [
            'Content-Type: application/json',
            'Accept: application/json',
            "X-Timestamp: {$timestamp}",
            "X-Nonce: {$nonce}",
            "X-Signature: {$signature}",
        ];

        if ($method === 'POST') {
            $headers[] = 'Content-Length: ' . strlen($rawBody);
        }

        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_IPRESOLVE, CURL_IPRESOLVE_V4);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT_MS, (int) ($this->connectTimeout * 1000));
        curl_setopt($ch, CURLOPT_TIMEOUT_MS, (int) ($this->timeout * 1000));
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);

        if ($method === 'POST') {
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, $rawBody);
        }

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlError = curl_error($ch);
        $curlErrno = curl_errno($ch);

        if ($response === false || $curlErrno !== 0) {
            $msg = "Calculation microservice unreachable at {$this->baseUrl}{$path}: [{$curlErrno}] {$curlError}";
            Log::error($msg);
            return $this->handleFailure($msg);
        }

        if ($httpCode >= 400) {
            $errBody = json_decode((string) $response, true);
            $errMsg = $errBody['message'] ?? "Remote service returned HTTP {$httpCode}";
            $msg = "Calculation microservice error on {$path} (HTTP {$httpCode}): {$errMsg}";
            Log::error($msg);
            return $this->handleFailure($msg);
        }

        $decoded = json_decode((string) $response, true);
        if (!is_array($decoded)) {
            $msg = "Calculation microservice returned invalid JSON from {$path}";
            Log::error($msg);
            return $this->handleFailure($msg);
        }

        if (($decoded['status'] ?? '') !== 'success') {
            $msg = $decoded['message'] ?? 'Calculation service reported an error';
            Log::error("Calculation microservice logical failure: {$msg}");
            return $this->handleFailure($msg);
        }

        return $decoded;
    }

    /**
     * Handle remote failure either by fallback to local driver or throwing exception.
     *
     * @return array<string, mixed>
     * @throws CalculationServiceUnavailableException
     */
    protected function handleFailure(string $errorMessage): array
    {
        if ($this->fallbackToLocal && $this->localDriver !== null) {
            Log::warning("[FALLBACK] Remote calculation service failure; falling back to local calculation driver: {$errorMessage}");
            throw new \RuntimeException("__FALLBACK_TRIGGERED__");
        }

        throw new CalculationServiceUnavailableException($errorMessage);
    }

    public function calculatePlanetaryPositions(float $jd): array
    {
        try {
            $res = $this->request('POST', '/calculate/planetary-positions', ['jd' => $jd]);
            return $res;
        } catch (\RuntimeException $e) {
            if ($e->getMessage() === '__FALLBACK_TRIGGERED__' && $this->localDriver) {
                return $this->localDriver->calculatePlanetaryPositions($jd);
            }
            throw $e;
        }
    }

    public function calculateDailyPanchang(array $payload): array
    {
        try {
            $res = $this->request('POST', '/calculate/daily-panchang', $payload);
            return $res['data'] ?? [];
        } catch (\RuntimeException $e) {
            if ($e->getMessage() === '__FALLBACK_TRIGGERED__' && $this->localDriver) {
                return $this->localDriver->calculateDailyPanchang($payload);
            }
            throw $e;
        }
    }

    public function calculateKundli(array $payload): array
    {
        try {
            $res = $this->request('POST', '/calculate/kundli', $payload);
            return $res['data'] ?? [];
        } catch (\RuntimeException $e) {
            if ($e->getMessage() === '__FALLBACK_TRIGGERED__' && $this->localDriver) {
                return $this->localDriver->calculateKundli($payload);
            }
            throw $e;
        }
    }

    public function calculateKundliComplete(array $payload): array
    {
        try {
            $res = $this->request('POST', '/calculate/kundli-complete', $payload);
            return $res['data'] ?? [];
        } catch (\RuntimeException $e) {
            if ($e->getMessage() === '__FALLBACK_TRIGGERED__' && $this->localDriver) {
                return $this->localDriver->calculateKundliComplete($payload);
            }
            throw $e;
        }
    }

    public function isHealthy(): bool
    {
        $ch = curl_init("{$this->baseUrl}/health");
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT_MS, 1500);
        curl_setopt($ch, CURLOPT_TIMEOUT_MS, 2000);

        $res = curl_exec($ch);
        $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);

        if ($res === false || $code !== 200) {
            return false;
        }

        $body = json_decode((string) $res, true);
        return is_array($body) && ($body['status'] ?? '') === 'ok';
    }
}
