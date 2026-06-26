<?php
if (basename($_SERVER['SCRIPT_FILENAME'] ?? '') === 'config.php') {
    http_response_code(403);
    exit;
}
error_reporting(0);
ini_set('display_errors', 0);

header_remove('X-Powered-By');

$required = ['DB_HOST', 'DB_NAME', 'DB_USER', 'DB_PASS'];
foreach ($required as $key) {
    if (!getenv($key)) {
        http_response_code(500);
        header('Content-Type: application/json');
        echo json_encode(['error' => 'Server configuration incomplete']);
        exit;
    }
}

define('DB_HOST', getenv('DB_HOST'));
define('DB_NAME', getenv('DB_NAME'));
define('DB_USER', getenv('DB_USER'));
define('DB_PASS', getenv('DB_PASS'));

define('ADMIN_PASSWORD_HASH', getenv('ADMIN_PASSWORD_HASH') ?: '');
