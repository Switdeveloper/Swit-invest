<?php
require_once __DIR__ . '/../api/db.php';

ini_set('session.use_strict_mode', 1);
ini_set('session.use_only_cookies', 1);
ini_set('session.cookie_httponly', 1);
ini_set('session.cookie_samesite', 'Strict');
session_start();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'login') {
        $password = $_POST['password'] ?? '';
        $stmt = $pdo->prepare("SELECT setting_value FROM admin_settings WHERE setting_key = 'password_hash' LIMIT 1");
        $stmt->execute();
        $row = $stmt->fetch();
        $storedHash = $row['setting_value'] ?? '';

        // Fallback to .env constant if no password in DB yet
        if (empty($storedHash) && defined('ADMIN_PASSWORD_HASH') && ADMIN_PASSWORD_HASH) {
            $storedHash = ADMIN_PASSWORD_HASH;
        }

        if ($storedHash && password_verify($password, $storedHash)) {
            session_regenerate_id(true);
            $_SESSION['admin_auth'] = true;
            $_SESSION['admin_expires'] = time() + 7200;
        }
        header('Location: /admin/');
        exit;
    }

    if ($action === 'setup') {
        $new = $_POST['new_password'] ?? '';
        $confirm = $_POST['confirm_password'] ?? '';
        if (strlen($new) < 6) {
            die('Password must be at least 6 characters.');
        }
        if ($new !== $confirm) {
            die('Passwords do not match.');
        }
        $hash = password_hash($new, PASSWORD_BCRYPT);
        $stmt = $pdo->prepare("INSERT INTO admin_settings (setting_key, setting_value) VALUES ('password_hash', ?) ON DUPLICATE KEY UPDATE setting_value = ?");
        $stmt->execute([$hash, $hash]);
        session_regenerate_id(true);
        $_SESSION['admin_auth'] = true;
        $_SESSION['admin_expires'] = time() + 7200;
        header('Location: /admin/');
        exit;
    }

    if ($action === 'change_password') {
        if (empty($_SESSION['admin_auth'])) {
            http_response_code(401);
            echo 'Not authenticated';
            exit;
        }
        $old = $_POST['old_password'] ?? '';
        $new = $_POST['new_password'] ?? '';
        $confirm = $_POST['confirm_password'] ?? '';

        if (strlen($new) < 6) {
            $error = 'Password must be at least 6 characters.';
        } elseif ($new !== $confirm) {
            $error = 'Passwords do not match.';
        } else {
            $stmt = $pdo->prepare("SELECT setting_value FROM admin_settings WHERE setting_key = 'password_hash' LIMIT 1");
            $stmt->execute();
            $row = $stmt->fetch();
            $storedHash = $row['setting_value'] ?? '';

            if (!$storedHash || !password_verify($old, $storedHash)) {
                $error = 'Current password is incorrect.';
            } else {
                $hash = password_hash($new, PASSWORD_BCRYPT);
                $stmt = $pdo->prepare("UPDATE admin_settings SET setting_value = ? WHERE setting_key = 'password_hash'");
                $stmt->execute([$hash]);
                $_SESSION['password_changed'] = true;
            }
        }
        $_SESSION['password_error'] = $error ?? null;
        header('Location: /admin/');
        exit;
    }

    if ($action === 'save_provider') {
        if (empty($_SESSION['admin_auth'])) {
            http_response_code(401);
            echo 'Not authenticated';
            exit;
        }
        $id = (int)($_POST['id'] ?? 0);
        $name = trim($_POST['name'] ?? '');
        $displayName = trim($_POST['display_name'] ?? '');
        $apiKey = $_POST['api_key'] ?? '';
        $baseUrl = trim($_POST['base_url'] ?? '');
        $modelsRaw = trim($_POST['models'] ?? '');
        $defaultModel = trim($_POST['default_model'] ?? '');
        $isActive = !empty($_POST['is_active']) ? 1 : 0;

        // Normalize models: comma-separated to JSON array
        $models = '[]';
        if ($modelsRaw) {
            $parts = array_map('trim', explode(',', $modelsRaw));
            $parts = array_values(array_filter($parts));
            $models = json_encode($parts);
        }

        if (empty($name) || empty($displayName)) {
            $_SESSION['provider_error'] = 'Name and display name are required.';
            header('Location: /admin/');
            exit;
        }
        if (!preg_match('/^[a-zA-Z0-9_-]+$/', $name)) {
            $_SESSION['provider_error'] = 'Name must be alphanumeric (letters, numbers, hyphens, underscores only).';
            header('Location: /admin/');
            exit;
        }

        try {
            if ($id) {
                if ($apiKey) {
                    $stmt = $pdo->prepare("UPDATE api_providers SET name = ?, display_name = ?, api_key = ?, base_url = ?, models = ?, default_model = ?, is_active = ? WHERE id = ?");
                    $stmt->execute([$name, $displayName, $apiKey, $baseUrl ?: null, $models, $defaultModel ?: null, $isActive, $id]);
                } else {
                    $stmt = $pdo->prepare("UPDATE api_providers SET name = ?, display_name = ?, base_url = ?, models = ?, default_model = ?, is_active = ? WHERE id = ?");
                    $stmt->execute([$name, $displayName, $baseUrl ?: null, $models, $defaultModel ?: null, $isActive, $id]);
                }
            } else {
                if (empty($apiKey)) {
                    $_SESSION['provider_error'] = 'API key is required for new providers.';
                    header('Location: /admin/');
                    exit;
                }
                $stmt = $pdo->prepare("INSERT INTO api_providers (name, display_name, api_key, base_url, models, default_model, is_active) VALUES (?, ?, ?, ?, ?, ?, ?)");
                $stmt->execute([$name, $displayName, $apiKey, $baseUrl ?: null, $models, $defaultModel ?: null, $isActive]);
            }
            $_SESSION['provider_saved'] = true;
        } catch (PDOException $e) {
            if ($e->getCode() == 23000) {
                $_SESSION['provider_error'] = 'A provider with this name already exists.';
            } else {
                $_SESSION['provider_error'] = 'Failed to save provider.';
            }
        }
        header('Location: /admin/');
        exit;
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['delete'])) {
    if (empty($_SESSION['admin_auth'])) {
        http_response_code(401);
        exit;
    }
    $id = (int)$_GET['delete'];
    if ($id > 0) {
        $stmt = $pdo->prepare("DELETE FROM api_providers WHERE id = ?");
        $stmt->execute([$id]);
    }
    header('Location: /admin/');
    exit;
}

$loggedIn = !empty($_SESSION['admin_auth']) && ($_SESSION['admin_expires'] ?? 0) > time();

if (!$loggedIn) {
    $_SESSION['admin_auth'] = false;

    $stmt = $pdo->prepare("SELECT setting_value FROM admin_settings WHERE setting_key = 'password_hash' LIMIT 1");
    $stmt->execute();
    $row = $stmt->fetch();
    $hasPassword = !empty($row['setting_value']) || (defined('ADMIN_PASSWORD_HASH') && ADMIN_PASSWORD_HASH);
    ?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<meta name="robots" content="noindex,nofollow">
<title>Admin Panel - SwitDeveloper</title>
<script src="https://cdn.tailwindcss.com"></script>
<style>body{font-family:Inter,sans-serif;background:#0d152e;}</style>
</head>
<body class="min-h-screen flex items-center justify-center p-4">
<div class="bg-white rounded-2xl shadow-2xl p-8 w-full max-w-md">
<?php if ($hasPassword): ?>
    <div class="text-center mb-8">
        <h1 class="text-3xl font-bold text-gray-800">Admin Panel</h1>
        <p class="text-gray-500 mt-2">Enter your admin password to continue</p>
    </div>
    <form method="POST" class="space-y-4">
        <input type="hidden" name="action" value="login">
        <input type="password" name="password" required placeholder="Admin Password" class="w-full border border-gray-300 rounded-lg px-4 py-3 focus:outline-none focus:border-primary-red focus:ring-1 focus:ring-primary-red transition">
        <button type="submit" class="w-full bg-primary-red text-white py-3 rounded-lg font-semibold hover:bg-red-800 transition">Sign In</button>
    </form>
<?php else: ?>
    <div class="text-center mb-8">
        <h1 class="text-3xl font-bold text-gray-800">First-Time Setup</h1>
        <p class="text-gray-500 mt-2">Create your admin password to secure the panel</p>
    </div>
    <form method="POST" class="space-y-4" onsubmit="return this.querySelector('[name=new_password]').value === this.querySelector('[name=confirm_password]').value || (alert('Passwords do not match.'), false)">
        <input type="hidden" name="action" value="setup">
        <input type="password" name="new_password" required minlength="6" placeholder="New Password (min 6 chars)" class="w-full border border-gray-300 rounded-lg px-4 py-3 focus:outline-none focus:border-primary-red focus:ring-1 focus:ring-primary-red transition">
        <input type="password" name="confirm_password" required minlength="6" placeholder="Confirm Password" class="w-full border border-gray-300 rounded-lg px-4 py-3 focus:outline-none focus:border-primary-red focus:ring-1 focus:ring-primary-red transition">
        <button type="submit" class="w-full bg-primary-red text-white py-3 rounded-lg font-semibold hover:bg-red-800 transition">Set Password &amp; Login</button>
    </form>
<?php endif; ?>
</div>
</body>
</html>
    <?php
    exit;
}

$stmt = $pdo->query("SELECT id, name, display_name, CASE WHEN api_key != '' THEN 1 ELSE 0 END as has_key, COALESCE(base_url, '') as base_url, COALESCE(models, '[]') as models, COALESCE(default_model, '') as default_model, is_active, updated_at FROM api_providers ORDER BY id");
$providers = $stmt->fetchAll();

$success = $_SESSION['provider_saved'] ?? false;
$error = $_SESSION['provider_error'] ?? '';
$pwSuccess = $_SESSION['password_changed'] ?? false;
$pwError = $_SESSION['password_error'] ?? '';
unset($_SESSION['provider_saved'], $_SESSION['provider_error'], $_SESSION['password_changed'], $_SESSION['password_error']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Admin Dashboard - SwitDeveloper</title>
<script src="https://cdn.tailwindcss.com"></script>
<link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
<style>body{font-family:Inter,sans-serif;background:#f3f4f6;}</style>
</head>
<body>
<nav class="bg-dark-blue text-white px-6 py-4 flex items-center justify-between" style="background:#0d152e">
    <span class="text-xl font-bold">SwitDeveloper Admin</span>
    <a href="?logout=1" class="text-red-400 hover:text-red-300 text-sm"><i class="fas fa-sign-out-alt mr-1"></i> Logout</a>
</nav>
<main class="max-w-6xl mx-auto p-6 space-y-8">

<?php if ($pwSuccess): ?>
<div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded-lg">Password changed successfully.</div>
<?php endif; ?>
<?php if ($pwError): ?>
<div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded-lg"><?= htmlspecialchars($pwError) ?></div>
<?php endif; ?>
<?php if ($success): ?>
<div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded-lg">Provider saved successfully.</div>
<?php endif; ?>
<?php if ($error): ?>
<div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded-lg"><?= htmlspecialchars($error) ?></div>
<?php endif; ?>

<!-- API Providers Section -->
<section class="bg-white rounded-2xl shadow-lg p-6">
    <div class="flex items-center justify-between mb-6">
        <h2 class="text-2xl font-bold text-gray-800"><i class="fas fa-key text-primary-red mr-2" style="color:#c91414"></i> API Providers</h2>
        <button onclick="document.getElementById('providerForm').classList.toggle('hidden');document.getElementById('providerForm').reset();document.getElementById('providerId').value=''" class="bg-primary-red text-white px-4 py-2 rounded-lg text-sm font-semibold hover:bg-red-800 transition" style="background:#c91414">+ Add Provider</button>
    </div>
    <div class="overflow-x-auto">
        <table class="w-full text-sm">
            <thead>
                <tr class="border-b text-left text-gray-500 uppercase text-xs">
                    <th class="pb-3 font-semibold">Name</th>
                    <th class="pb-3 font-semibold">Display Name</th>
                    <th class="pb-3 font-semibold">API Key</th>
                    <th class="pb-3 font-semibold">Base URL</th>
                    <th class="pb-3 font-semibold">Default Model</th>
                    <th class="pb-3 font-semibold">Active</th>
                    <th class="pb-3 font-semibold">Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($providers as $p): ?>
                <tr class="border-b hover:bg-gray-50">
                    <td class="py-3 font-medium"><?= htmlspecialchars($p['name']) ?></td>
                    <td class="py-3"><?= htmlspecialchars($p['display_name']) ?></td>
                    <td class="py-3"><?= $p['has_key'] ? '<span class="text-green-600 font-medium">Saved</span>' : '<span class="text-red-500">Missing</span>' ?></td>
                    <td class="py-3 text-gray-500"><?= htmlspecialchars($p['base_url'] ?: 'Default') ?></td>
                    <td class="py-3"><span class="text-xs text-gray-500"><?= htmlspecialchars($p['default_model'] ?: '—') ?></span></td>
                    <td class="py-3"><?= $p['is_active'] ? '<span class="text-green-600">Active</span>' : '<span class="text-gray-400">Inactive</span>' ?></td>
                    <td class="py-3 flex gap-2">
                        <button onclick="editProvider(<?= $p['id'] ?>, '<?= addslashes($p['name']) ?>', '<?= addslashes($p['display_name']) ?>', '<?= addslashes($p['base_url']) ?>', '<?= addslashes($p['models']) ?>', '<?= addslashes($p['default_model']) ?>', <?= $p['is_active'] ?>)" class="text-blue-600 hover:text-blue-800 text-sm font-medium">Edit</button>
                        <a href="?delete=<?= $p['id'] ?>" onclick="return confirm('Delete <?= addslashes($p['name']) ?>?')" class="text-red-600 hover:text-red-800 text-sm font-medium">Delete</a>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>

    <form id="providerForm" method="POST" class="hidden mt-6 border-t pt-6 space-y-4">
        <input type="hidden" name="action" value="save_provider">
        <input type="hidden" name="id" id="providerId" value="0">
        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
            <div>
                <label class="block text-sm font-semibold text-gray-700 mb-1">Provider Name</label>
                <input type="text" name="name" id="providerName" required placeholder="e.g. openrouter" class="w-full border border-gray-300 rounded-lg px-3 py-2.5 text-sm focus:outline-none focus:border-primary-red">
            </div>
            <div>
                <label class="block text-sm font-semibold text-gray-700 mb-1">Display Name</label>
                <input type="text" name="display_name" id="providerDisplay" required placeholder="e.g. OpenRouter" class="w-full border border-gray-300 rounded-lg px-3 py-2.5 text-sm focus:outline-none focus:border-primary-red">
            </div>
            <div>
                <label class="block text-sm font-semibold text-gray-700 mb-1">API Key</label>
                <input type="password" name="api_key" id="providerKey" placeholder="Leave blank to keep existing" class="w-full border border-gray-300 rounded-lg px-3 py-2.5 text-sm focus:outline-none focus:border-primary-red">
            </div>
            <div>
                <label class="block text-sm font-semibold text-gray-700 mb-1">Base URL (optional)</label>
                <input type="url" name="base_url" id="providerUrl" placeholder="e.g. https://openrouter.ai/api/v1" class="w-full border border-gray-300 rounded-lg px-3 py-2.5 text-sm focus:outline-none focus:border-primary-red">
            </div>
            <div>
                <label class="block text-sm font-semibold text-gray-700 mb-1">Models (comma-separated)</label>
                <input type="text" name="models" id="providerModels" placeholder="e.g. gpt-4o,claude-3.5-sonnet" class="w-full border border-gray-300 rounded-lg px-3 py-2.5 text-sm focus:outline-none focus:border-primary-red">
            </div>
            <div>
                <label class="block text-sm font-semibold text-gray-700 mb-1">Default Model</label>
                <input type="text" name="default_model" id="providerDefaultModel" placeholder="e.g. gpt-4o" class="w-full border border-gray-300 rounded-lg px-3 py-2.5 text-sm focus:outline-none focus:border-primary-red">
            </div>
        </div>
        <label class="flex items-center gap-2">
            <input type="checkbox" name="is_active" value="1" checked class="rounded">
            <span class="text-sm font-medium text-gray-700">Active</span>
        </label>
        <div class="flex gap-3">
            <button type="submit" class="bg-primary-red text-white px-6 py-2.5 rounded-lg text-sm font-semibold hover:bg-red-800 transition" style="background:#c91414">Save Provider</button>
            <button type="button" onclick="this.form.reset();this.closest('form').classList.add('hidden')" class="text-gray-500 hover:text-gray-700 text-sm font-medium">Cancel</button>
        </div>
    </form>
</section>

<!-- Change Password Section -->
<section class="bg-white rounded-2xl shadow-lg p-6">
    <h2 class="text-2xl font-bold text-gray-800 mb-6"><i class="fas fa-lock text-primary-red mr-2" style="color:#c91414"></i> Change Password</h2>
    <form method="POST" class="max-w-md space-y-4">
        <input type="hidden" name="action" value="change_password">
        <div>
            <label class="block text-sm font-semibold text-gray-700 mb-1">Current Password</label>
            <input type="password" name="old_password" required class="w-full border border-gray-300 rounded-lg px-3 py-2.5 text-sm focus:outline-none focus:border-primary-red">
        </div>
        <div>
            <label class="block text-sm font-semibold text-gray-700 mb-1">New Password</label>
            <input type="password" name="new_password" required minlength="6" class="w-full border border-gray-300 rounded-lg px-3 py-2.5 text-sm focus:outline-none focus:border-primary-red">
        </div>
        <div>
            <label class="block text-sm font-semibold text-gray-700 mb-1">Confirm New Password</label>
            <input type="password" name="confirm_password" required minlength="6" class="w-full border border-gray-300 rounded-lg px-3 py-2.5 text-sm focus:outline-none focus:border-primary-red">
        </div>
        <button type="submit" class="bg-primary-red text-white px-6 py-2.5 rounded-lg text-sm font-semibold hover:bg-red-800 transition" style="background:#c91414">Update Password</button>
    </form>
</section>

</main>
<script>
function editProvider(id, name, display, url, models, defaultModel, active) {
    document.getElementById('providerId').value = id;
    document.getElementById('providerName').value = name;
    document.getElementById('providerDisplay').value = display;
    document.getElementById('providerUrl').value = url === '' ? '' : url;
    document.getElementById('providerKey').value = '';
    try {
        const m = JSON.parse(models);
        document.getElementById('providerModels').value = Array.isArray(m) ? m.join(', ') : '';
    } catch(e) {
        document.getElementById('providerModels').value = '';
    }
    document.getElementById('providerDefaultModel').value = defaultModel === '' ? '' : defaultModel;
    document.querySelector('[name="is_active"]').checked = active === 1;
    document.getElementById('providerForm').classList.remove('hidden');
}
</script>
</body>
</html>
<?php
if (isset($_GET['logout'])) {
    $_SESSION = [];
    session_destroy();
    header('Location: /admin/');
    exit;
}
?>