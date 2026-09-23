<?php
declare(strict_types=1);

const AUTH_USERS_FILE = __DIR__ . '/users.json';

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_name('DECTOOLSESS');
    session_set_cookie_params([
        'lifetime' => 0,
        'path'     => '/',
        'httponly' => true,
        'samesite' => 'Strict',
        'secure'   => !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off',
    ]);
    session_start();
}

function authDefaultStore(): array
{
    return [
        'roles' => [
            'admin' => ['decrypt', 'encrypt', 'deploy', 'manage_keys', 'manage_users', 'dummy_view', 'dummy_manage'],
            'user'  => ['decrypt', 'encrypt', 'deploy', 'dummy_view'],
        ],
        'users' => [],
    ];
}

function loadUsers(): array
{
    if (!file_exists(AUTH_USERS_FILE)) {
        return authDefaultStore();
    }
    $store = json_decode((string) file_get_contents(AUTH_USERS_FILE), true);
    if (!is_array($store) || !isset($store['roles'], $store['users'])) {
        return authDefaultStore();
    }
    // New default perms reach old users.json files without a manual edit
    foreach (authDefaultStore()['roles'] as $role => $perms) {
        $store['roles'][$role] = array_values(array_unique(array_merge($store['roles'][$role] ?? [], $perms)));
    }
    return $store;
}

function saveUsers(array $store): bool
{
    $store['users'] = array_values($store['users']);
    $json = json_encode($store, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
    // @: a PHP warning here would break the JSON response
    return @file_put_contents(AUTH_USERS_FILE, $json, LOCK_EX) !== false;
}

function findUserById(array $store, int $id): ?array
{
    foreach ($store['users'] as $u) {
        if ((int) $u['id'] === $id) {
            return $u;
        }
    }
    return null;
}

function currentUser(): ?array
{
    if (empty($_SESSION['auth_uid'])) {
        return null;
    }
    // Re-read each hit so deleted/demoted users lose access at once
    $user = findUserById(loadUsers(), (int) $_SESSION['auth_uid']);
    if ($user === null) {
        unset($_SESSION['auth_uid']);
    }
    return $user;
}

function can(string $perm): bool
{
    $user = currentUser();
    if ($user === null) {
        return false;
    }
    $roles = loadUsers()['roles'];
    return in_array($perm, $roles[$user['role']] ?? [], true);
}

function requireLogin(?string $perm = null, bool $isApi = false): array
{
    $user = currentUser();
    if ($user === null) {
        if ($isApi) {
            authJsonExit(401, 'Login required');
        }
        $rel = authRelPath(__DIR__, authScriptDir());
        $page = basename((string) $_SERVER['SCRIPT_FILENAME']);
        $qs = (string) ($_SERVER['QUERY_STRING'] ?? '');
        $_SESSION['auth_return'] = $rel . ($page === 'index.php' && $rel !== '' ? '' : $page) . ($qs !== '' ? '?' . $qs : '');
        header('Location: ' . authPrefix() . 'login.php');
        exit;
    }
    if ($perm !== null && !can($perm)) {
        if ($isApi) {
            authJsonExit(403, 'Permission denied');
        }
        http_response_code(403);
        echo 'Permission denied. <a href="' . authHubUrl() . '">Back to Tool Hub</a> · <a href="' . authPrefix() . 'logout.php">Logout</a>';
        exit;
    }
    return $user;
}

function authJsonExit(int $code, string $message): never
{
    http_response_code($code);
    header('Content-Type: application/json');
    echo json_encode(['status' => false, 'message' => $message]);
    exit;
}

function authScriptDir(): string
{
    return dirname((string) $_SERVER['SCRIPT_FILENAME']);
}

// Relative URL path between two folders, so any host/base path works
function authRelPath(string $fromDir, string $toDir): string
{
    $split = static fn(string $d): array => array_values(array_filter(explode('/', str_replace('\\', '/', (string) realpath($d))), 'strlen'));
    $from = $split($fromDir);
    $to = $split($toDir);
    $i = 0;
    while ($i < count($from) && $i < count($to) && strcasecmp($from[$i], $to[$i]) === 0) {
        $i++;
    }
    $rel = str_repeat('../', count($from) - $i) . implode('/', array_slice($to, $i));
    return $rel === '' || str_ends_with($rel, '/') ? $rel : $rel . '/';
}

// Path to common/ from the calling page: '' here, '../common/' from a tool
function authPrefix(): string
{
    return authRelPath(authScriptDir(), __DIR__);
}

// Path to the toolHub card index from the calling page
function authHubUrl(): string
{
    $rel = authRelPath(authScriptDir(), dirname(__DIR__));
    return $rel === '' ? 'index.php' : $rel;
}

// Plain-text by user's choice; old bcrypt rows still verify
function checkPassword(string $stored, string $input): bool
{
    if (str_starts_with($stored, '$2y$')) {
        return password_verify($input, $stored);
    }
    return hash_equals($stored, $input);
}

// Relative paths only (leading ../ ok), blocks open redirects
function authReturnUrl(): string
{
    $url = (string) ($_SESSION['auth_return'] ?? '');
    unset($_SESSION['auth_return']);
    $path = preg_replace('~^(\.\./)+~', '', explode('?', $url, 2)[0]);
    if ($url === '' || !preg_match('~^[A-Za-z0-9_\-/.]*(\?[^\r\n]*)?$~', $url)
        || $path === null || str_starts_with($path, '/') || str_contains($path, '..') || str_contains($url, '//')) {
        return authHubUrl();
    }
    return $url;
}

function csrfToken(): string
{
    if (empty($_SESSION['csrf'])) {
        $_SESSION['csrf'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf'];
}

function checkCsrf(): void
{
    $sent = $_POST['csrf'] ?? ($_SERVER['HTTP_X_CSRF_TOKEN'] ?? '');
    if (!is_string($sent) || !hash_equals(csrfToken(), $sent)) {
        authJsonExit(403, 'Invalid CSRF token');
    }
}

function h(string $s): string
{
    return htmlspecialchars($s, ENT_QUOTES, 'UTF-8');
}
