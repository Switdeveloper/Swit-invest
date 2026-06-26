<?php
require_once __DIR__ . '/db.php';

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    sendJSON(['error' => 'Method not allowed'], 405);
}

$input = file_get_contents('php://input');
$body = json_decode($input, true);

if (!$body || !isset($body['messages'])) {
    sendJSON(['error' => 'Invalid request body. Required: messages'], 400);
}

$providerName = $body['_provider'] ?? $_SERVER['HTTP_X_PROVIDER'] ?? 'openrouter';
unset($body['_provider']);

try {
    $db = getDB();
    $stmt = $db->prepare("SELECT api_key, base_url FROM api_providers WHERE name = ? AND is_active = 1 LIMIT 1");
    $stmt->execute([$providerName]);
    $provider = $stmt->fetch();

    if (!$provider) {
        sendJSON([
            'error' => "Provider '$providerName' not found or inactive. Available: openrouter, openai, anthropic"
        ], 404);
    }

    $apiKey = $provider['api_key'];
    if (empty($apiKey)) {
        sendJSON([
            'error' => "API key for '$providerName' is not set. Configure it in the API Settings page."
        ], 401);
    }

    $baseUrl = $provider['base_url'];
    if (empty($baseUrl)) {
        $defaults = [
            'openrouter' => 'https://openrouter.ai/api/v1',
            'openai'     => 'https://api.openai.com/v1',
            'anthropic'  => 'https://api.anthropic.com/v1',
        ];
        $baseUrl = $defaults[$providerName] ?? '';
    }

$allowedEndpoints = ['chat/completions', 'completions', 'messages', 'moderations'];
$requestPath = $_GET['endpoint'] ?? '/chat/completions';
$requestPath = ltrim($requestPath, '/');
if (!in_array($requestPath, $allowedEndpoints)) {
    sendJSON(['error' => 'Invalid endpoint'], 400);
}
$targetUrl = rtrim($baseUrl, '/') . '/' . $requestPath;

    $requestHeaders = [
        'Content-Type: application/json',
    ];

    if ($providerName === 'anthropic') {
        $requestHeaders[] = "x-api-key: $apiKey";
        $requestHeaders[] = 'anthropic-version: 2023-06-01';
    } else {
        $requestHeaders[] = "Authorization: Bearer $apiKey";
    }

    $referer = $_SERVER['HTTP_REFERER'] ?? ($_SERVER['HTTP_ORIGIN'] ?? '');
    if (!empty($referer)) {
        $requestHeaders[] = "HTTP-Referer: $referer";
    }
    $requestHeaders[] = 'X-Title: SwitDeveloper';

    if (strlen(json_encode($body)) > 500000) {
        sendJSON(['error' => 'Request too large'], 413);
    }

    $ch = curl_init();
    curl_setopt_array($ch, [
        CURLOPT_URL            => $targetUrl,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => json_encode($body),
        CURLOPT_HTTPHEADER     => $requestHeaders,
        CURLOPT_TIMEOUT        => 120,
        CURLOPT_CONNECTTIMEOUT => 15,
        CURLOPT_FOLLOWLOCATION => false,
        CURLOPT_SSL_VERIFYPEER => true,
        CURLOPT_SSL_VERIFYHOST => 2,
    ]);

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlError = curl_error($ch);
    curl_close($ch);

    if ($curlError) {
        sendJSON(['error' => "Upstream request failed: $curlError"], 502);
    }

    http_response_code($httpCode);
    header('Content-Type: application/json');
    echo $response;
    exit;

} catch (Exception $e) {
    sendJSON(['error' => 'Internal server error'], 500);
}
