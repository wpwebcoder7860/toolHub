<?php
declare(strict_types=1);
require __DIR__ . '/auth.php';

if (currentUser() !== null) {
    header('Location: ' . authHubUrl());
    exit;
}

$store = loadUsers();
$isSetup = empty($store['users']);
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim((string) ($_POST['username'] ?? ''));
    $password = (string) ($_POST['password'] ?? '');

    if (!hash_equals(csrfToken(), (string) ($_POST['csrf'] ?? ''))) {
        $error = 'Session expired, please try again.';
    } elseif ($isSetup) {
        $confirm = (string) ($_POST['confirm'] ?? '');
        if (!preg_match('/^[A-Za-z0-9_.-]{3,30}$/', $username)) {
            $error = 'Username: 3-30 chars (letters, digits, _ . -)';
        } elseif (mb_strlen($password) < 3) {
            $error = 'Password must be at least 3 characters.';
        } elseif ($password !== $confirm) {
            $error = 'Passwords do not match.';
        } else {
            $store['users'][] = [
                'id'          => 1,
                'username'    => $username,
                'password'    => $password,
                'role'        => 'super_admin', // sole account, must never be locked out
                'permissions' => [],
            ];
            if (!saveUsers($store)) {
                $error = 'users.json is not writable on server, check file permission.';
            } else {
                session_regenerate_id(true);
                $_SESSION['auth_uid'] = 1;
                header('Location: ' . authReturnUrl());
                exit;
            }
        }
    } else {
        $match = null;
        foreach ($store['users'] as $u) {
            if (strcasecmp($u['username'], $username) === 0) {
                $match = $u;
                break;
            }
        }
        if ($match !== null && checkPassword((string) $match['password'], $password)) {
            session_regenerate_id(true);
            $_SESSION['auth_uid'] = (int) $match['id'];
            header('Location: ' . authReturnUrl());
            exit;
        }
        $error = 'Invalid username or password.';
    }
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <title><?= $isSetup ? 'Create Admin' : 'Login' ?></title>
    <meta name="viewport" content="width=device-width,initial-scale=1,shrink-to-fit=no">
    <link href="assets/bootstrap.min.css" rel="stylesheet">
    <link href="assets/hub.css" rel="stylesheet">
    <link rel="shortcut icon" href="/srfAddon/images/favicon.ico">
    <style>
        body {
            background: linear-gradient(135deg, #2f5bea 0%, #4f7cf7 55%, #7c9bfb 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 16px;
        }

        .login-card {
            width: 100%;
            max-width: 380px;
            background: #1f2937;
            color: #fff;
            border-radius: 8px;
            padding: 28px;
            box-shadow: 0 10px 30px rgba(0, 0, 0, .25);
        }

        .login-card .form-control {
            background: #111827;
            color: #fff;
            border-color: #374151;
        }
    </style>
</head>

<body>
    <a href="<?= h(authHubUrl()) ?>" class="hub-back on-dark floating" title="Back to Tool Hub"><svg viewBox="0 0 24 24"><path d="M19 12H5M11 18l-6-6 6-6" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" /></svg>Tool Hub</a>
    <form class="login-card" method="post" autocomplete="off">
        <h5 class="mb-1 fw-bold"><?= $isSetup ? 'Create first admin' : 'Tool Hub Login' ?></h5>
        <p class="small text-white-50 mb-3">
            <?= $isSetup ? 'No users exist yet. This account will be the Super Admin.' : 'Sign in to continue.' ?>
        </p>
        <?php if ($error !== ''): ?>
            <div class="alert alert-danger py-2 small"><?= h($error) ?></div>
        <?php endif; ?>
        <input type="hidden" name="csrf" value="<?= h(csrfToken()) ?>">
        <div class="mb-2">
            <label class="form-label small">Username</label>
            <input type="text" name="username" class="form-control" required autofocus
                value="<?= h((string) ($_POST['username'] ?? '')) ?>">
        </div>
        <div class="mb-2">
            <label class="form-label small">Password</label>
            <input type="password" name="password" class="form-control" required>
        </div>
        <?php if ($isSetup): ?>
            <div class="mb-2">
                <label class="form-label small">Confirm Password</label>
                <input type="password" name="confirm" class="form-control" required>
            </div>
        <?php endif; ?>
        <button type="submit" class="btn btn-primary w-100 mt-2"><?= $isSetup ? 'CREATE SUPER ADMIN' : 'LOGIN' ?></button>
    </form>
</body>

</html>
