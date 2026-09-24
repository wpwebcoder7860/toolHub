<?php
declare(strict_types=1);
require __DIR__ . '/../common/auth.php';

header('Content-Type: application/json');
$me = requireLogin('manage_users', true);

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    authJsonExit(405, 'POST only');
}
checkCsrf();

$store  = loadUsers();
$action = (string) ($_POST['action'] ?? '');

function respond(bool $ok, string $message, array $extra = []): never
{
    echo json_encode(['status' => $ok, 'message' => $message] + $extra);
    exit;
}

function superAdminCount(array $users): int
{
    return count(array_filter($users, fn($u) => $u['role'] === 'super_admin'));
}

// Keep only known keys, coerce to bool; super_admin ignores this entirely
function sanitizePermissions(string $role): array
{
    if ($role === 'super_admin') {
        return [];
    }
    $raw = json_decode((string) ($_POST['permissions'] ?? '{}'), true);
    if (!is_array($raw)) {
        $raw = [];
    }
    $out = [];
    foreach (array_keys(PERMISSION_MODULES) as $key) {
        $out[$key] = !empty($raw[$key]);
    }
    if ($role !== 'admin') {
        $out['manage_users'] = false; // only role=admin is ever eligible (besides super_admin)
    }
    return $out;
}

function indexOfUser(array $users, int $id): int
{
    foreach ($users as $i => $u) {
        if ((int) $u['id'] === $id) {
            return $i;
        }
    }
    respond(false, 'User not found');
}

function validPassword(string $pwd): void
{
    if (mb_strlen($pwd) < 3) {
        respond(false, 'Password must be at least 3 characters');
    }
}

$validRoles = array_keys($store['roles']);

switch ($action) {
    case 'list':
        $list = array_map(fn($u) => [
            'id'          => $u['id'],
            'username'    => $u['username'],
            'role'        => $u['role'],
            'permissions' => $u['permissions'] ?? [],
            'self'        => (int) $u['id'] === (int) $me['id'],
        ], $store['users']);
        respond(true, 'ok', ['data' => $list, 'roles' => $validRoles, 'modules' => PERMISSION_MODULES, 'groups' => PERMISSION_GROUPS]);

    case 'add':
        $username = trim((string) ($_POST['username'] ?? ''));
        $password = (string) ($_POST['password'] ?? '');
        $role     = (string) ($_POST['role'] ?? '');
        if (!preg_match('/^[A-Za-z0-9_.-]{3,30}$/', $username)) {
            respond(false, 'Username: 3-30 chars (letters, digits, _ . -)');
        }
        if (!in_array($role, $validRoles, true)) {
            respond(false, 'Invalid role');
        }
        validPassword($password);
        foreach ($store['users'] as $u) {
            if (strcasecmp($u['username'], $username) === 0) {
                respond(false, 'Username already exists');
            }
        }
        $newId = $store['users'] ? max(array_column($store['users'], 'id')) + 1 : 1;
        $store['users'][] = [
            'id'          => $newId,
            'username'    => $username,
            'password'    => $password,
            'role'        => $role,
            'permissions' => sanitizePermissions($role),
        ];
        if (!saveUsers($store)) {
            respond(false, 'users.json is not writable on server, check file permission');
        }
        respond(true, 'User added successfully');

    case 'edit':
        $id   = (int) ($_POST['id'] ?? 0);
        $role = (string) ($_POST['role'] ?? '');
        if (!in_array($role, $validRoles, true)) {
            respond(false, 'Invalid role');
        }
        $i = indexOfUser($store['users'], $id);
        if ($store['users'][$i]['role'] === 'super_admin' && $role !== 'super_admin' && superAdminCount($store['users']) <= 1) {
            respond(false, 'Cannot demote the last super admin');
        }
        $store['users'][$i]['role'] = $role;
        $store['users'][$i]['permissions'] = sanitizePermissions($role);
        if (!saveUsers($store)) {
            respond(false, 'users.json is not writable on server, check file permission');
        }
        respond(true, 'Role updated successfully');

    case 'reset':
        $id       = (int) ($_POST['id'] ?? 0);
        $password = (string) ($_POST['password'] ?? '');
        validPassword($password);
        $i = indexOfUser($store['users'], $id);
        $store['users'][$i]['password'] = $password;
        if (!saveUsers($store)) {
            respond(false, 'users.json is not writable on server, check file permission');
        }
        respond(true, 'Password reset successfully');

    case 'delete':
        $id = (int) ($_POST['id'] ?? 0);
        if ($id === (int) $me['id']) {
            respond(false, 'You cannot delete your own account');
        }
        $i = indexOfUser($store['users'], $id);
        if ($store['users'][$i]['role'] === 'super_admin' && superAdminCount($store['users']) <= 1) {
            respond(false, 'Cannot delete the last super admin');
        }
        array_splice($store['users'], $i, 1);
        if (!saveUsers($store)) {
            respond(false, 'users.json is not writable on server, check file permission');
        }
        respond(true, 'User deleted successfully');

    default:
        respond(false, 'Invalid action');
}
