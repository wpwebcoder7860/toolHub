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

// Every logged-in user gets these, regardless of role or permissions —
// only Axis Dummy Data is per-user gated (see PERMISSION_MODULES).
const AUTH_UNIVERSAL_PERMS = ['decrypt', 'encrypt', 'deploy', 'manage_keys'];

// Assignable, per-user toggles. super_admin always has manage_users
// regardless of this map (hardcoded bypass in can()); role=user can
// never have it, even if this map somehow says true (see can()).
const PERMISSION_MODULES = [
    'dummy_view'   => ['label' => 'View', 'group' => 'Axis Dummy Data'],
    'dummy_add'    => ['label' => 'Add', 'group' => 'Axis Dummy Data'],
    'dummy_update' => ['label' => 'Update', 'group' => 'Axis Dummy Data'],
    'dummy_delete' => ['label' => 'Delete', 'group' => 'Axis Dummy Data'],
    'dummy_status' => ['label' => 'Status Toggle', 'group' => 'Axis Dummy Data'],
    'manage_users' => ['label' => 'Manage Users', 'group' => 'Admin'],
];

// Icon + description per PERMISSION_MODULES group, for the Users UI
const PERMISSION_GROUPS = [
    'Axis Dummy Data' => ['icon' => 'db', 'desc' => 'Manage and view axis dummy data'],
    'Admin'           => ['icon' => 'gear', 'desc' => 'Access to admin features'],
];

// Old fixed role -> perm lists, used only to migrate legacy user records
const AUTH_LEGACY_ROLE_PERMS = [
    'admin' => ['dummy_view', 'dummy_add', 'dummy_update', 'dummy_delete', 'dummy_status', 'manage_users'],
    'user'  => ['dummy_view'],
];

function authDefaultStore(): array
{
    return [
        // Roles are now just labels; actual access is per-user 'permissions'
        'roles' => ['super_admin' => [], 'admin' => [], 'user' => []],
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
    // New default roles reach old users.json files without a manual edit
    foreach (authDefaultStore()['roles'] as $role => $perms) {
        $store['roles'][$role] = array_values(array_unique(array_merge($store['roles'][$role] ?? [], $perms)));
    }
    // Legacy rows (pre-per-user-permissions) get a permissions map derived
    // from their old fixed role list, so nobody loses access on upgrade.
    foreach ($store['users'] as &$u) {
        if (!isset($u['permissions']) && $u['role'] !== 'super_admin') {
            $legacy = AUTH_LEGACY_ROLE_PERMS[$u['role']] ?? [];
            $u['permissions'] = array_fill_keys($legacy, true);
        } elseif (!isset($u['permissions'])) {
            $u['permissions'] = [];
        }
    }
    unset($u);
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
    if ($user['role'] === 'super_admin') {
        return true; // fixed in code, users.json can't take this away
    }
    if (in_array($perm, AUTH_UNIVERSAL_PERMS, true)) {
        return true; // Decrypt/Encrypt/Deploy + key management: every logged-in user
    }
    if ($perm === 'manage_users') {
        // super_admin already returned true above; role=user is never eligible
        // even if the stored permissions map somehow has it set
        return $user['role'] === 'admin' && !empty($user['permissions']['manage_users']);
    }
    $p = $user['permissions'] ?? [];
    return !empty($p[$perm]); // only dummy_* is per-user gated
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
