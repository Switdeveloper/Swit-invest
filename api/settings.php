<?php
require_once __DIR__ . '/db.php';

header('Content-Type: application/json');

if (session_status() === PHP_SESSION_NONE) {
    ini_set('session.use_strict_mode', 1);
    ini_set('session.use_only_cookies', 1);
    ini_set('session.cookie_httponly', 1);
    ini_set('session.cookie_samesite', 'Lax');
    ini_set('session.gc_maxlifetime', 7200);
    session_start();
}

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

$method = $_SERVER['REQUEST_METHOD'];

if ($method === 'POST' && $_GET['action'] === 'auth') {
    $input = json_decode(file_get_contents('php://input'), true);
    $password = $input['password'] ?? '';

    $adminHash = ADMIN_PASSWORD_HASH;
    if (empty($adminHash)) {
        $db = getDB();
        $stmt = $db->prepare("SELECT setting_value FROM admin_settings WHERE setting_key = 'password_hash' LIMIT 1");
        $stmt->execute();
        $row = $stmt->fetch();
        $adminHash = $row['setting_value'] ?? '';
    }

    if (empty($adminHash)) {
        sendJSON(['error' => 'No admin password configured'], 500);
    }

    if (password_verify($password, $adminHash)) {
        $token = bin2hex(random_bytes(32));
        session_regenerate_id(true);
        $_SESSION['api_admin_token'] = $token;
        $_SESSION['api_admin_expires'] = time() + 7200;
        session_write_close();
        sendJSON(['token' => $token, 'success' => true]);
    } else {
        sendJSON(['error' => 'Invalid password'], 401);
    }
}

if ($method === 'GET' && $_GET['action'] === 'check') {
    $token = $_GET['token'] ?? $_COOKIE['api_admin_token'] ?? '';
    $valid = !empty($token)
        && ($_SESSION['api_admin_token'] ?? '') === $token
        && ($_SESSION['api_admin_expires'] ?? 0) > time();
    if (!$valid) {
        $_SESSION['api_admin_token'] = '';
        $_SESSION['api_admin_expires'] = 0;
    }
    session_write_close();
    sendJSON(['authenticated' => $valid]);
}

if ($method === 'GET' && $_GET['action'] === 'list') {
    verifyAuth();
    try {
        $db = getDB();
        $stmt = $db->query("SELECT id, name, display_name, CASE WHEN api_key != '' THEN 1 ELSE 0 END as has_key, COALESCE(base_url, '') as base_url, is_active, updated_at FROM api_providers ORDER BY id");
        $providers = $stmt->fetchAll();
        sendJSON(['providers' => $providers]);
    } catch (Exception $e) {
        sendJSON(['error' => 'Failed to fetch providers'], 500);
    }
}

if ($method === 'POST' && $_GET['action'] === 'save') {
    verifyAuth();
    $input = json_decode(file_get_contents('php://input'), true);
    $id = $input['id'] ?? null;
    $name = trim($input['name'] ?? '');
    $displayName = trim($input['display_name'] ?? '');
    $apiKey = $input['api_key'] ?? '';
    $baseUrl = trim($input['base_url'] ?? '');
    $isActive = !empty($input['is_active']) ? 1 : 0;

    if (empty($name) || empty($displayName)) {
        sendJSON(['error' => 'Name and display name are required'], 400);
    }

    if (!preg_match('/^[a-zA-Z0-9_-]+$/', $name)) {
        sendJSON(['error' => 'Name must be alphanumeric (letters, numbers, hyphens, underscores only)'], 400);
    }

    try {
        $db = getDB();

        if ($id) {
            if (!empty($apiKey)) {
                $stmt = $db->prepare("UPDATE api_providers SET name = ?, display_name = ?, api_key = ?, base_url = ?, is_active = ? WHERE id = ?");
                $stmt->execute([$name, $displayName, $apiKey, $baseUrl ?: null, $isActive, $id]);
            } else {
                $stmt = $db->prepare("UPDATE api_providers SET name = ?, display_name = ?, base_url = ?, is_active = ? WHERE id = ?");
                $stmt->execute([$name, $displayName, $baseUrl ?: null, $isActive, $id]);
            }
        } else {
            if (empty($apiKey)) {
                sendJSON(['error' => 'API key is required for new providers'], 400);
            }
            $stmt = $db->prepare("INSERT INTO api_providers (name, display_name, api_key, base_url, is_active) VALUES (?, ?, ?, ?, ?)");
            $stmt->execute([$name, $displayName, $apiKey, $baseUrl ?: null, $isActive]);
            $id = $db->lastInsertId();
        }

        sendJSON(['success' => true, 'id' => (int)$id]);
    } catch (PDOException $e) {
        if ($e->getCode() == 23000) {
            sendJSON(['error' => 'A provider with this name already exists'], 409);
        }
        sendJSON(['error' => 'Failed to save provider'], 500);
    }
}

if ($method === 'DELETE' && $_GET['action'] === 'delete') {
    verifyAuth();
    $id = (int)($_GET['id'] ?? 0);
    if ($id < 1) {
        sendJSON(['error' => 'Invalid provider ID'], 400);
    }
    try {
        $db = getDB();
        $stmt = $db->prepare("DELETE FROM api_providers WHERE id = ?");
        $stmt->execute([$id]);
        sendJSON(['success' => true]);
    } catch (Exception $e) {
        sendJSON(['error' => 'Failed to delete provider'], 500);
    }
}

sendJSON(['error' => 'Invalid action'], 400);

function verifyAuth(): void {
    $headers = getallheaders();
    $authHeader = '';
    foreach (['Authorization', 'authorization', 'AUTHORIZATION'] as $h) {
        if (isset($headers[$h])) { $authHeader = $headers[$h]; break; }
    }
    $token = '';

    if (preg_match('/^Bearer\s+(.+)$/i', $authHeader, $m)) {
        $token = $m[1];
    }

    if (empty($token)) {
        $token = $_SESSION['api_admin_token'] ?? '';
    }

    if (empty($token)) {
        sendJSON(['error' => 'Authentication required'], 401);
    }

    $valid = ($_SESSION['api_admin_token'] ?? '') === $token
          && ($_SESSION['api_admin_expires'] ?? 0) > time();

    if (!$valid) {
        $_SESSION['api_admin_token'] = '';
        $_SESSION['api_admin_expires'] = 0;
        session_write_close();
        sendJSON(['error' => 'Invalid or expired session'], 401);
    }

    $_SESSION['api_admin_expires'] = time() + 7200;
    session_write_close();
}
