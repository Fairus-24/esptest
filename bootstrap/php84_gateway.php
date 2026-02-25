<?php

declare(strict_types=1);

$basePath = dirname(__DIR__);
$env = loadSimpleEnv($basePath . DIRECTORY_SEPARATOR . '.env');

$autoStart = toBool($env['LARAVEL_HTTP_AUTO_START'] ?? 'true');
$serverHost = trim((string) ($env['LARAVEL_HTTP_HOST'] ?? '0.0.0.0'));
$serverPort = max(1, (int) ($env['LARAVEL_HTTP_PORT'] ?? 8000));
$healthHost = trim((string) ($env['LARAVEL_HTTP_HEALTH_HOST'] ?? '127.0.0.1'));
$healthPath = normalizePath((string) ($env['LARAVEL_HTTP_HEALTH_PATH'] ?? '/up'));
$phpBinary = trim((string) ($env['LARAVEL_HTTP_PHP_BINARY'] ?? 'php'));
$startCooldown = max(5, (int) ($env['LARAVEL_HTTP_START_COOLDOWN'] ?? 15));
$waitSeconds = max(1, (int) ($env['LARAVEL_HTTP_WAIT_SECONDS'] ?? 8));

if ($autoStart && !isServerHealthy($healthHost, $serverPort, $healthPath)) {
    $lockFile = $basePath . DIRECTORY_SEPARATOR . 'storage' . DIRECTORY_SEPARATOR . 'app' . DIRECTORY_SEPARATOR . 'http_server_autostart.lock';
    $stampFile = $basePath . DIRECTORY_SEPARATOR . 'storage' . DIRECTORY_SEPARATOR . 'app' . DIRECTORY_SEPARATOR . 'http_server.last_start';
    $logFile = $basePath . DIRECTORY_SEPARATOR . 'storage' . DIRECTORY_SEPARATOR . 'logs' . DIRECTORY_SEPARATOR . 'laravel_http_server.log';

    $lockHandle = @fopen($lockFile, 'c+');
    if ($lockHandle !== false && @flock($lockHandle, LOCK_EX | LOCK_NB)) {
        $last = 0;
        if (is_file($stampFile)) {
            $last = (int) trim((string) @file_get_contents($stampFile));
        }

        if ((time() - $last) >= $startCooldown && !isPortOpen($healthHost, $serverPort)) {
            @file_put_contents($stampFile, (string) time(), LOCK_EX);
            startHttpServer($phpBinary, $basePath, $serverHost, $serverPort, $logFile);
        }

        @flock($lockHandle, LOCK_UN);
        @fclose($lockHandle);
    } elseif ($lockHandle !== false) {
        @fclose($lockHandle);
    }

    waitForServer($healthHost, $serverPort, $healthPath, $waitSeconds);
}

$requestUri = (string) ($_SERVER['REQUEST_URI'] ?? '/');
$scriptDir = str_replace('\\', '/', dirname((string) ($_SERVER['SCRIPT_NAME'] ?? '')));
$scriptDir = rtrim($scriptDir, '/');
if ($scriptDir !== '' && $scriptDir !== '/' && str_starts_with($requestUri, $scriptDir)) {
    $requestUri = substr($requestUri, strlen($scriptDir));
    if ($requestUri === '' || $requestUri[0] !== '/') {
        $requestUri = '/' . $requestUri;
    }
}

$targetUrl = 'http://' . $healthHost . ':' . $serverPort . $requestUri;
$result = proxyToTarget($targetUrl);

if ($result['ok'] !== true) {
    http_response_code(503);
    header('Content-Type: text/plain; charset=utf-8');
    echo 'Service temporarily unavailable. Laravel HTTP server is not ready.';
    exit;
}

http_response_code((int) $result['status']);
foreach ($result['headers'] as $headerLine) {
    header($headerLine, false);
}
echo $result['body'];
exit;

function loadSimpleEnv(string $filePath): array
{
    $values = [];
    if (!is_file($filePath)) {
        return $values;
    }

    $lines = @file($filePath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    if (!is_array($lines)) {
        return $values;
    }

    foreach ($lines as $line) {
        $trimmed = trim($line);
        if ($trimmed === '' || str_starts_with($trimmed, '#')) {
            continue;
        }

        $pos = strpos($trimmed, '=');
        if ($pos === false) {
            continue;
        }

        $key = trim(substr($trimmed, 0, $pos));
        $val = trim(substr($trimmed, $pos + 1));
        if ($key === '') {
            continue;
        }

        if (strlen($val) >= 2) {
            $first = $val[0];
            $last = $val[strlen($val) - 1];
            if (($first === '"' && $last === '"') || ($first === "'" && $last === "'")) {
                $val = substr($val, 1, -1);
            }
        }

        $values[$key] = $val;
    }

    return $values;
}

function toBool(string $value): bool
{
    $normalized = strtolower(trim($value));
    return !in_array($normalized, ['0', 'false', 'off', 'no', ''], true);
}

function normalizePath(string $path): string
{
    $trimmed = trim($path);
    if ($trimmed === '') {
        return '';
    }

    return str_starts_with($trimmed, '/') ? $trimmed : '/' . $trimmed;
}

function isServerHealthy(string $host, int $port, string $healthPath): bool
{
    if (!isPortOpen($host, $port)) {
        return false;
    }

    if ($healthPath === '') {
        return true;
    }

    $url = 'http://' . $host . ':' . $port . $healthPath;
    $context = stream_context_create([
        'http' => [
            'method' => 'GET',
            'timeout' => 2,
            'ignore_errors' => true,
        ],
    ]);

    @file_get_contents($url, false, $context);
    $status = extractStatusCode($http_response_header ?? []);
    return $status >= 200 && $status < 500;
}

function isPortOpen(string $host, int $port): bool
{
    $socket = @fsockopen($host, $port, $errno, $errstr, 1.0);
    if ($socket === false) {
        return false;
    }

    @fclose($socket);
    return true;
}

function waitForServer(string $host, int $port, string $healthPath, int $seconds): void
{
    $deadline = microtime(true) + $seconds;
    while (microtime(true) < $deadline) {
        if (isServerHealthy($host, $port, $healthPath)) {
            return;
        }

        usleep(250000);
    }
}

function startHttpServer(string $phpBinary, string $basePath, string $host, int $port, string $logFile): void
{
    if ($phpBinary === '') {
        return;
    }

    if (looksLikePath($phpBinary) && !is_file($phpBinary)) {
        return;
    }

    $command = quoteForShell($phpBinary)
        . ' '
        . quoteForShell($basePath . DIRECTORY_SEPARATOR . 'artisan')
        . ' serve --host=' . $host
        . ' --port=' . $port
        . ' >> '
        . quoteForShell($logFile)
        . ' 2>&1';

    if (DIRECTORY_SEPARATOR === '\\') {
        @pclose(@popen('start "" /B ' . $command, 'r'));
    } else {
        @exec('nohup ' . $command . ' >/dev/null 2>&1 &');
    }
}

function proxyToTarget(string $targetUrl): array
{
    $method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
    $headers = gatherRequestHeaders();
    $body = file_get_contents('php://input');

    if (function_exists('curl_init')) {
        return proxyWithCurl($targetUrl, $method, $headers, (string) $body);
    }

    return proxyWithStreams($targetUrl, $method, $headers, (string) $body);
}

function proxyWithCurl(string $url, string $method, array $headers, string $body): array
{
    $ch = curl_init($url);
    if ($ch === false) {
        return ['ok' => false, 'status' => 503, 'headers' => [], 'body' => ''];
    }

    $forwardHeaders = [];
    foreach ($headers as $name => $value) {
        $key = strtolower($name);
        if (in_array($key, ['host', 'content-length', 'connection'], true)) {
            continue;
        }
        $forwardHeaders[] = $name . ': ' . $value;
    }

    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HEADER => true,
        CURLOPT_FOLLOWLOCATION => false,
        CURLOPT_CUSTOMREQUEST => $method,
        CURLOPT_TIMEOUT => 20,
        CURLOPT_CONNECTTIMEOUT => 3,
        CURLOPT_HTTPHEADER => $forwardHeaders,
    ]);

    if ($body !== '' && !in_array(strtoupper($method), ['GET', 'HEAD'], true)) {
        curl_setopt($ch, CURLOPT_POSTFIELDS, $body);
    }

    $response = curl_exec($ch);
    if ($response === false) {
        curl_close($ch);
        return ['ok' => false, 'status' => 503, 'headers' => [], 'body' => ''];
    }

    $headerSize = (int) curl_getinfo($ch, CURLINFO_HEADER_SIZE);
    $status = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    $rawHeaders = substr($response, 0, $headerSize);
    $rawBody = substr($response, $headerSize);
    $responseHeaders = parseResponseHeaders($rawHeaders);

    return [
        'ok' => true,
        'status' => $status > 0 ? $status : 200,
        'headers' => $responseHeaders,
        'body' => $rawBody,
    ];
}

function proxyWithStreams(string $url, string $method, array $headers, string $body): array
{
    $lines = [];
    foreach ($headers as $name => $value) {
        $key = strtolower($name);
        if (in_array($key, ['host', 'content-length', 'connection'], true)) {
            continue;
        }
        $lines[] = $name . ': ' . $value;
    }

    $context = stream_context_create([
        'http' => [
            'method' => $method,
            'header' => implode("\r\n", $lines),
            'content' => $body,
            'ignore_errors' => true,
            'timeout' => 20,
        ],
    ]);

    $responseBody = @file_get_contents($url, false, $context);
    if ($responseBody === false) {
        return ['ok' => false, 'status' => 503, 'headers' => [], 'body' => ''];
    }

    $status = extractStatusCode($http_response_header ?? []);
    $responseHeaders = parseResponseHeadersFromArray($http_response_header ?? []);

    return [
        'ok' => true,
        'status' => $status > 0 ? $status : 200,
        'headers' => $responseHeaders,
        'body' => $responseBody,
    ];
}

function gatherRequestHeaders(): array
{
    $headers = [];
    if (function_exists('getallheaders')) {
        $raw = getallheaders();
        if (is_array($raw)) {
            foreach ($raw as $name => $value) {
                if (is_string($name) && is_string($value)) {
                    $headers[$name] = $value;
                }
            }
        }
    } else {
        foreach ($_SERVER as $name => $value) {
            if (!str_starts_with($name, 'HTTP_')) {
                continue;
            }

            $headerName = str_replace('_', '-', ucwords(strtolower(substr($name, 5)), '_'));
            $headers[$headerName] = (string) $value;
        }
    }

    return $headers;
}

function parseResponseHeaders(string $rawHeaders): array
{
    $lines = preg_split('/\r\n|\n|\r/', $rawHeaders);
    if (!is_array($lines)) {
        return [];
    }

    $out = [];
    foreach ($lines as $line) {
        $trimmed = trim($line);
        if ($trimmed === '' || str_starts_with(strtoupper($trimmed), 'HTTP/')) {
            continue;
        }

        if (shouldSkipHeader($trimmed)) {
            continue;
        }

        $out[] = $trimmed;
    }

    return $out;
}

function parseResponseHeadersFromArray(array $headers): array
{
    $out = [];
    foreach ($headers as $line) {
        if (!is_string($line)) {
            continue;
        }

        $trimmed = trim($line);
        if ($trimmed === '' || str_starts_with(strtoupper($trimmed), 'HTTP/')) {
            continue;
        }

        if (shouldSkipHeader($trimmed)) {
            continue;
        }

        $out[] = $trimmed;
    }

    return $out;
}

function shouldSkipHeader(string $line): bool
{
    $lower = strtolower($line);
    return str_starts_with($lower, 'transfer-encoding:')
        || str_starts_with($lower, 'connection:')
        || str_starts_with($lower, 'content-length:')
        || str_starts_with($lower, 'keep-alive:');
}

function extractStatusCode(array $headers): int
{
    foreach (array_reverse($headers) as $line) {
        if (!is_string($line)) {
            continue;
        }

        if (preg_match('/^HTTP\/\d(?:\.\d)?\s+(\d{3})/i', trim($line), $matches) === 1) {
            return (int) $matches[1];
        }
    }

    return 0;
}

function looksLikePath(string $value): bool
{
    return str_contains($value, '\\') || str_contains($value, '/');
}

function quoteForShell(string $value): string
{
    if (DIRECTORY_SEPARATOR === '\\') {
        return '"' . str_replace('"', '\"', $value) . '"';
    }

    return escapeshellarg($value);
}
