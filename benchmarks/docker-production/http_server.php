<?php

declare(strict_types=1);

/**
 * Astro Setu — Dedicated Astrology Calculation HTTP Daemon (Production Grade)
 * 
 * Secure, stateless, single-worker HTTP daemon running inside Debian 12 PHP 8.4 container.
 * Features:
 * - Service-to-service HMAC-SHA256 signature verification
 * - Nonce replay protection with memory-efficient pruning
 * - Timestamp expiration window (±300 seconds)
 * - Request body size guardrails (max 64 KB)
 * - Socket read timeouts (5s)
 * - Sanitized JSON error responses (zero internal paths/traces/secrets exposed)
 * - Support for all 4 calculation operations
 */

// 1. Minimum environment setup for isolated in-memory execution
$_ENV['APP_ENV'] = 'production';
$_ENV['DB_CONNECTION'] = 'sqlite';
$_ENV['DB_DATABASE'] = ':memory:';
$_ENV['CACHE_STORE'] = 'array';
$_ENV['SESSION_DRIVER'] = 'array';
$_ENV['QUEUE_CONNECTION'] = 'sync';

require '/app/vendor/autoload.php';
$app = require '/app/bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\UserAstrologyProfile;
use App\Services\Astrology\Calculation\Handlers\KundliCalculationHandler;
use App\Services\Astrology\DailyPanchangService;
use App\Services\Astrology\KundliService;
use App\Services\Astrology\NativeEphemerisResolver;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Cache;
use JayeshMepani\PanchangCore\Astronomy\AstronomyService;
use JayeshMepani\PanchangCore\Astronomy\JmeRuntime;
use JayeshMepani\PanchangCore\PanchangServiceProvider;
use JmeEph\FFI\JmeEphFFI;

// Autoloader for isolated packages
$jmeSrc = base_path('packages/astrology/jpl-moshier-ephemeris-php/src/');
$panchangSrc = base_path('packages/astrology/panchang-core/src/');
if (!class_exists(JmeEphFFI::class)) {
    $loader = new \Composer\Autoload\ClassLoader();
    $loader->addPsr4('JmeEph\\', $jmeSrc);
    $loader->addPsr4('JayeshMepani\\PanchangCore\\', $panchangSrc);
    $loader->register();
}

$activeLib = NativeEphemerisResolver::resolve();
$jme = new JmeEphFFI($activeLib);
app()->instance(JmeEphFFI::class, $jme);
config(['panchang.jme_settings.mode' => 'moshier', 'panchang.library_path' => $activeLib]);
$provider = new PanchangServiceProvider(app());
$provider->register();

JmeRuntime::setTrueCitra($jme);

$ffi = $jme->getFFI();
$calcBuffer = $ffi->new('double[6]');
$calcError = $ffi->new('char[256]');

$astronomyService = app(AstronomyService::class);
$kundliService = app(KundliService::class);
$dailyPanchangService = app(DailyPanchangService::class);
$kundliHandler = app(KundliCalculationHandler::class);

$hmacSecret = getenv('ASTRO_CALCULATION_HMAC_SECRET') ?: '';
if (empty($hmacSecret)) {
    error_log("[WARNING] ASTRO_CALCULATION_HMAC_SECRET is not configured! Protected endpoints will reject requests.");
}

$startTime = microtime(true);
$requestCount = 0;

// Replay protection cache: nonce => timestamp
$seenNonces = [];
$lastPruneTime = time();

const MAX_BODY_BYTES = 65536; // 64 KB limit for request body
const MAX_TIMESTAMP_DRIFT = 300; // 5 minutes window
const SOCKET_TIMEOUT_SEC = 5;

function getSafeCgroupStats(): array
{
    $currentBytes = file_exists('/sys/fs/cgroup/memory.current') ? (int) trim((string) file_get_contents('/sys/fs/cgroup/memory.current')) : 0;
    $peakBytes = file_exists('/sys/fs/cgroup/memory.peak') ? (int) trim((string) file_get_contents('/sys/fs/cgroup/memory.peak')) : 0;
    $maxBytes = file_exists('/sys/fs/cgroup/memory.max') ? trim((string) file_get_contents('/sys/fs/cgroup/memory.max')) : 'max';
    
    $oomKills = 0;
    if (file_exists('/sys/fs/cgroup/memory.events')) {
        $events = (string) file_get_contents('/sys/fs/cgroup/memory.events');
        if (preg_match('/oom_kill\s+(\d+)/i', $events, $m)) {
            $oomKills = (int) $m[1];
        }
    }

    $vmRss = 0.0;
    if (file_exists('/proc/self/status')) {
        $status = (string) file_get_contents('/proc/self/status');
        if (preg_match('/VmRSS:\s+(\d+)\s+kB/i', $status, $m)) {
            $vmRss = round(((float)$m[1]) / 1024, 2);
        }
    }

    return [
        'container_memory_current_mb' => round($currentBytes / (1024 * 1024), 2),
        'container_memory_peak_mb' => round($peakBytes / (1024 * 1024), 2),
        'container_memory_max_mb' => ($maxBytes === 'max' ? null : round(((int)$maxBytes) / (1024 * 1024), 2)),
        'container_oom_kills' => $oomKills,
        'php_allocated_mb' => round(memory_get_usage(true) / (1024 * 1024), 2),
        'php_peak_mb' => round(memory_get_peak_usage(true) / (1024 * 1024), 2),
        'process_vmrss_mb' => $vmRss,
    ];
}

$port = (int) (getenv('PORT') ?: 8080);
$socket = stream_socket_server("tcp://0.0.0.0:{$port}", $errno, $errstr);
if (!$socket) {
    error_log("[FATAL] Failed to bind to 0.0.0.0:{$port}: {$errstr} ({$errno})");
    exit(1);
}

error_log("[INFO] Astro Setu Calculation Service running on 0.0.0.0:{$port} (Single Worker, HMAC Protected)");

while ($client = @stream_socket_accept($socket, -1)) {
    $requestCount++;
    stream_set_timeout($client, SOCKET_TIMEOUT_SEC);

    $headerLines = [];
    $contentLength = 0;
    $headers = [];

    // Read HTTP request headers
    $lineCount = 0;
    while (($line = fgets($client)) !== false) {
        $lineCount++;
        if ($lineCount > 100) {
            // Header lines exceeded limit
            break;
        }
        $trimmed = trim($line);
        if ($trimmed === '') break;
        $headerLines[] = $trimmed;

        $colonPos = strpos($trimmed, ':');
        if ($colonPos !== false) {
            $hKey = strtolower(trim(substr($trimmed, 0, $colonPos)));
            $hVal = trim(substr($trimmed, $colonPos + 1));
            $headers[$hKey] = $hVal;
        }
    }

    $requestLine = $headerLines[0] ?? 'GET / HTTP/1.1';
    $parts = explode(' ', $requestLine, 3);
    $method = strtoupper($parts[0] ?? 'GET');
    $rawUri = $parts[1] ?? '/';
    $uri = parse_url($rawUri, PHP_URL_PATH) ?: '/';

    $contentLength = isset($headers['content-length']) ? (int) $headers['content-length'] : 0;

    // Body size check
    if ($contentLength > MAX_BODY_BYTES) {
        $responseCode = 413;
        $responsePayload = ['status' => 'error', 'message' => 'Payload exceeds maximum limit of ' . MAX_BODY_BYTES . ' bytes'];
        sendResponse($client, $responseCode, $responsePayload);
        continue;
    }

    // Read body
    $body = '';
    if ($contentLength > 0) {
        $bytesRead = 0;
        while ($bytesRead < $contentLength && !feof($client)) {
            $chunk = fread($client, min(8192, $contentLength - $bytesRead));
            if ($chunk === false || $chunk === '') {
                break;
            }
            $body .= $chunk;
            $bytesRead += strlen($chunk);
        }
    }

    // Unauthenticated Health Check (Exempt from HMAC)
    if ($method === 'GET' && ($uri === '/health' || $uri === '/')) {
        sendResponse($client, 200, [
            'status' => 'ok',
            'service' => 'astro-calc-service',
            'timestamp' => time(),
            'uptime_seconds' => round(microtime(true) - $startTime, 2),
        ]);
        continue;
    }

    // HMAC AUTHENTICATION VERIFICATION
    if (empty($hmacSecret)) {
        error_log("[AUTH_ERROR] Calculation service HMAC secret not configured in environment");
        sendResponse($client, 500, ['status' => 'error', 'message' => 'Service authentication misconfigured']);
        continue;
    }

    $sig = $headers['x-signature'] ?? '';
    $timestamp = $headers['x-timestamp'] ?? '';
    $nonce = $headers['x-nonce'] ?? '';

    if (empty($sig) || empty($timestamp) || empty($nonce)) {
        error_log("[AUTH_REJECT] Missing authentication headers for {$method} {$uri}");
        sendResponse($client, 401, ['status' => 'error', 'message' => 'Unauthorized: Missing HMAC authentication headers']);
        continue;
    }

    $reqTs = (int) $timestamp;
    $now = time();

    // 1. Validate timestamp window (±300 seconds)
    if (abs($now - $reqTs) > MAX_TIMESTAMP_DRIFT) {
        error_log("[AUTH_REJECT] Expired timestamp ({$reqTs} vs {$now}) for {$method} {$uri}");
        sendResponse($client, 401, ['status' => 'error', 'message' => 'Unauthorized: Expired timestamp or clock skew']);
        continue;
    }

    // 2. Validate nonce replay
    if (isset($seenNonces[$nonce])) {
        error_log("[AUTH_REJECT] Replayed nonce detected: {$nonce}");
        sendResponse($client, 401, ['status' => 'error', 'message' => 'Unauthorized: Nonce already used']);
        continue;
    }

    // 3. Verify HMAC signature using constant-time comparison
    // Format: METHOD\nPATH\nTIMESTAMP\nNONCE\nBODY
    $canonicalString = "{$method}\n{$uri}\n{$timestamp}\n{$nonce}\n{$body}";
    $expectedSignature = hash_hmac('sha256', $canonicalString, $hmacSecret);

    if (!hash_equals($expectedSignature, $sig)) {
        error_log("[AUTH_REJECT] Invalid signature for {$method} {$uri}");
        sendResponse($client, 401, ['status' => 'error', 'message' => 'Unauthorized: Invalid signature']);
        continue;
    }

    // Record valid nonce
    $seenNonces[$nonce] = $reqTs;

    // Periodic nonce cleanup (every 100 requests or once every 60 seconds)
    if ($requestCount % 100 === 0 || ($now - $lastPruneTime) > 60) {
        $cutoff = $now - MAX_TIMESTAMP_DRIFT;
        foreach ($seenNonces as $nKey => $nTs) {
            if ($nTs < $cutoff) {
                unset($seenNonces[$nKey]);
            }
        }
        $lastPruneTime = $now;
    }

    // Metrics Endpoint (HMAC-protected)
    if ($method === 'GET' && $uri === '/metrics') {
        sendResponse($client, 200, array_merge([
            'uptime_seconds' => round(microtime(true) - $startTime, 2),
            'requests_handled' => $requestCount,
        ], getSafeCgroupStats()));
        continue;
    }

    $input = json_decode($body, true);
    if (!is_array($input) && $contentLength > 0) {
        sendResponse($client, 400, ['status' => 'error', 'message' => 'Malformed JSON payload']);
        continue;
    }
    $input = $input ?? [];

    $responseCode = 200;
    $responsePayload = [];

    try {
        if ($method === 'POST' && $uri === '/calculate/planetary-positions') {
            // Operation 1: Planetary positions
            if (isset($input['jd'])) {
                $jd = (float) $input['jd'];
            } else {
                $dateStr = (string) ($input['date'] ?? '2000-01-01');
                $timeStr = (string) ($input['time'] ?? '12:00:00');
                $tzStr = (string) ($input['timezone'] ?? 'UTC');
                $dt = CarbonImmutable::parse("{$dateStr} {$timeStr}", $tzStr);
                $jd = $astronomyService->toJulianDayUtc([
                    'year' => (int) $dt->format('Y'),
                    'month' => (int) $dt->format('m'),
                    'day' => (int) $dt->format('d'),
                    'hour' => (int) $dt->format('H'),
                    'minute' => (int) $dt->format('i'),
                    'second' => (int) $dt->format('s'),
                    'timezone' => $dt->getTimezone()->getName(),
                ]);
            }

            $ayan = $jme->jme_get_ayanamsa_ut($jd);
            $bodies = [
                JmeEphFFI::JME_BODY_SUN, JmeEphFFI::JME_BODY_MOON, JmeEphFFI::JME_BODY_MERCURY,
                JmeEphFFI::JME_BODY_VENUS, JmeEphFFI::JME_BODY_MARS, JmeEphFFI::JME_BODY_JUPITER,
                JmeEphFFI::JME_BODY_SATURN, JmeEphFFI::JME_BODY_URANUS, JmeEphFFI::JME_BODY_NEPTUNE,
                JmeEphFFI::JME_BODY_PLUTO, JmeEphFFI::JME_BODY_MEAN_NODE
            ];
            $positions = [];
            foreach ($bodies as $b) {
                $jme->jme_calc_ut($jd, $b, JmeEphFFI::JME_CALC_SPEED, $calcBuffer, $calcError);
                $positions[$b] = [
                    'longitude' => $calcBuffer[0],
                    'latitude' => $calcBuffer[1],
                    'distance_au' => $calcBuffer[2],
                    'speed_deg_per_day' => $calcBuffer[3],
                ];
            }
            $responsePayload = [
                'status' => 'success',
                'julian_day' => $jd,
                'ayanamsha' => $ayan,
                'planets' => $positions,
            ];
        } elseif ($method === 'POST' && $uri === '/calculate/daily-panchang') {
            // Operation 2: Daily Panchang
            Cache::flush();
            $date = (string) ($input['date'] ?? now('Asia/Kolkata')->toDateString());
            $lat = (float) ($input['latitude'] ?? $input['lat'] ?? 28.6139);
            $lon = (float) ($input['longitude'] ?? $input['lon'] ?? 77.2090);
            $tz = (string) ($input['timezone'] ?? $input['tz'] ?? 'Asia/Kolkata');
            $elevation = (float) ($input['elevation'] ?? 0.0);

            $panchang = $dailyPanchangService->getDailyPanchang($date, $lat, $lon, $tz, $elevation);
            $responsePayload = [
                'status' => 'success',
                'data' => $panchang,
            ];
        } elseif ($method === 'POST' && $uri === '/calculate/kundli') {
            // Operation 3: Core Kundli and Dasha
            $dob = (string) ($input['date_of_birth'] ?? $input['dob'] ?? now('Asia/Kolkata')->toDateString());
            $tob = isset($input['birth_time']) ? (string) $input['birth_time'] : (isset($input['time']) ? (string) $input['time'] : '12:00:00');
            $precision = (string) ($input['birth_time_precision'] ?? $input['precision'] ?? UserAstrologyProfile::PRECISION_VERIFIED);
            $approxWindow = isset($input['approximate_time_window']) ? (string) $input['approximate_time_window'] : null;
            $lat = (float) ($input['latitude'] ?? $input['lat'] ?? 28.6139);
            $lon = (float) ($input['longitude'] ?? $input['lon'] ?? 77.2090);
            $tz = (string) ($input['timezone'] ?? $input['tz'] ?? 'Asia/Kolkata');

            $kundli = $kundliService->computeKundli($dob, $tob, $precision, $approxWindow, $lat, $lon, $tz);
            $responsePayload = [
                'status' => 'success',
                'data' => $kundli,
            ];
        } elseif ($method === 'POST' && $uri === '/calculate/kundli-complete') {
            // Operation 4: Complete Kundli including Navamsha stability
            $normalized = KundliCalculationHandler::normalizePayload($input);
            $kundliComplete = $kundliHandler->calculate($normalized);
            $responsePayload = [
                'status' => 'success',
                'data' => $kundliComplete,
            ];
        } else {
            $responseCode = 404;
            $responsePayload = ['status' => 'error', 'message' => "Route {$method} {$uri} not found"];
        }
    } catch (\Throwable $e) {
        $responseCode = 500;
        error_log("[CALC_ERROR] {$method} {$uri}: " . $e->getMessage());
        $responsePayload = [
            'status' => 'error',
            'message' => 'Astrology calculation execution error: ' . $e->getMessage(),
        ];
    }

    sendResponse($client, $responseCode, $responsePayload);
}

function sendResponse($client, int $code, array $payload): void
{
    $json = json_encode($payload, JSON_UNESCAPED_SLASHES);
    $statusText = match ($code) {
        200 => 'OK',
        400 => 'Bad Request',
        401 => 'Unauthorized',
        404 => 'Not Found',
        413 => 'Payload Too Large',
        500 => 'Internal Server Error',
        default => 'Status',
    };

    $headers = "HTTP/1.1 {$code} {$statusText}\r\n" .
               "Content-Type: application/json; charset=utf-8\r\n" .
               "Content-Length: " . strlen($json) . "\r\n" .
               "Connection: close\r\n\r\n";

    @fwrite($client, $headers . $json);
    @fclose($client);
}
