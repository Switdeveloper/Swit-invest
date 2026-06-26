<?php
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

require_once __DIR__ . '/config.php';

$provider = $_GET['provider'] ?? 'openrouter';
$allowed = ['openrouter', 'openai', 'anthropic'];

if (!in_array($provider, $allowed)) {
    http_response_code(400);
    header('Content-Type: application/json');
    echo json_encode(['error' => 'Invalid provider']);
    exit;
}

try {
    require_once __DIR__ . '/db.php';
    $db = getDB();
    $stmt = $db->prepare("SELECT models, default_model FROM api_providers WHERE name = ? AND is_active = 1 LIMIT 1");
    $stmt->execute([$provider]);
    $row = $stmt->fetch();

    if ($row) {
        $models = json_decode($row['models'] ?? '[]', true);
        if (!is_array($models) || empty($models)) {
            $models = getDefaultModels($provider);
        }
        $defaultModel = $row['default_model'] ?: ($models[0] ?? '');
        header('Content-Type: application/json');
        echo json_encode(['models' => $models, 'default' => $defaultModel]);
    } else {
        $models = getDefaultModels($provider);
        header('Content-Type: application/json');
        echo json_encode(['models' => $models, 'default' => $models[0] ?? '']);
    }
} catch (Exception $e) {
    $models = getDefaultModels($provider);
    header('Content-Type: application/json');
    echo json_encode(['models' => $models, 'default' => $models[0] ?? '']);
}

function getDefaultModels(string $provider): array {
    $defaults = [
        'openrouter' => ['gpt-4o', 'gpt-4o-mini', 'claude-3.5-sonnet', 'claude-3-haiku', 'gemini-pro', 'gemini-2.0-flash', 'mistral-large', 'llama-3.1-70b', 'deepseek-coder'],
        'openai' => ['gpt-4o', 'gpt-4o-mini', 'gpt-4-turbo', 'gpt-3.5-turbo'],
        'anthropic' => ['claude-3.5-sonnet', 'claude-3-haiku', 'claude-3-opus'],
    ];
    return $defaults[$provider] ?? ['gpt-4o'];
}