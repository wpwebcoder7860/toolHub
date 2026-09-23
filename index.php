<?php
declare(strict_types=1);
// Public card index; each tool page does its own login check
require __DIR__ . '/common/auth.php';

$me = currentUser();

$tools = [
    ['href' => 'crypto/', 'title' => 'Decrypt', 'desc' => 'Paste an encrypted response and get readable, formatted JSON.', 'perm' => 'decrypt', 'icon' => 'unlock', 'tone' => 'blue'],
    ['href' => 'crypto/encrypt.php', 'title' => 'Encrypt', 'desc' => 'Turn raw JSON into the encrypted string a service expects.', 'perm' => 'encrypt', 'icon' => 'lock', 'tone' => 'violet'],
    ['href' => 'deploy/', 'title' => 'Deploy Tool', 'desc' => 'Generate backup, deploy and revert commands for server files.', 'perm' => 'deploy', 'icon' => 'rocket', 'tone' => 'amber'],
    ['href' => 'dummyData/', 'title' => 'Axis Addon Dummy Data', 'desc' => 'Browse, search and manage dummy responses used in Axis Addon SRF.', 'perm' => 'dummy_view', 'icon' => 'db', 'tone' => 'green'],
    ['href' => 'users/', 'title' => 'User Management', 'desc' => 'Add users, change roles, reset passwords.', 'perm' => 'manage_users', 'icon' => 'users', 'tone' => 'rose', 'adminOnly' => true],
];
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <title>Tool Hub</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link rel="shortcut icon" href="/srfAddon/images/favicon.ico">
    <link href="common/assets/hub.css?v=<?= @filemtime(__DIR__ . '/common/assets/hub.css') ?>" rel="stylesheet">
</head>

<body class="hub-body">
    <svg width="0" height="0" style="position:absolute" aria-hidden="true">
        <defs>
            <symbol id="i-grid" viewBox="0 0 24 24"><path d="M4 4h7v7H4zM13 4h7v7h-7zM4 13h7v7H4zM13 13h7v7h-7z" fill="none" stroke="currentColor" stroke-width="2" stroke-linejoin="round" /></symbol>
            <symbol id="i-unlock" viewBox="0 0 24 24"><rect x="5" y="11" width="14" height="10" rx="2" fill="none" stroke="currentColor" stroke-width="2" /><path d="M8 11V7a4 4 0 0 1 7.5-2" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" /></symbol>
            <symbol id="i-lock" viewBox="0 0 24 24"><rect x="5" y="11" width="14" height="10" rx="2" fill="none" stroke="currentColor" stroke-width="2" /><path d="M8 11V7a4 4 0 0 1 8 0v4" fill="none" stroke="currentColor" stroke-width="2" /></symbol>
            <symbol id="i-rocket" viewBox="0 0 24 24"><path d="M5 15c-1.5 1-2 4-2 6 2 0 5-.5 6-2M9 15l-3-3c1-4 5-8 13-9-1 8-5 12-9 13z" fill="none" stroke="currentColor" stroke-width="2" stroke-linejoin="round" /><circle cx="14.5" cy="9.5" r="1.5" fill="currentColor" /></symbol>
            <symbol id="i-db" viewBox="0 0 24 24"><ellipse cx="12" cy="5" rx="8" ry="3" fill="none" stroke="currentColor" stroke-width="2" /><path d="M4 5v6c0 1.7 3.6 3 8 3s8-1.3 8-3V5M4 11v6c0 1.7 3.6 3 8 3s8-1.3 8-3v-6" fill="none" stroke="currentColor" stroke-width="2" /></symbol>
            <symbol id="i-users" viewBox="0 0 24 24"><circle cx="9" cy="8" r="3.5" fill="none" stroke="currentColor" stroke-width="2" /><path d="M2.5 20c0-3.6 2.9-5.5 6.5-5.5s6.5 1.9 6.5 5.5M16 4.8a3.5 3.5 0 0 1 0 6.4M18 14.8c2.2.6 3.5 2.4 3.5 5.2" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" /></symbol>
            <symbol id="i-arrow" viewBox="0 0 24 24"><path d="M5 12h14M13 6l6 6-6 6" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" /></symbol>
            <symbol id="i-key" viewBox="0 0 24 24"><circle cx="8" cy="15" r="4" fill="none" stroke="currentColor" stroke-width="2.4" /><path d="M11 12l9-9M17 6l3 3" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" /></symbol>
        </defs>
    </svg>

    <div class="hub-page">
        <header class="hub-hero">
            <div class="hub-brand">
                <div class="hub-logo"><svg><use href="#i-grid" /></svg></div>
                <div>
                    <h1>Tool Hub</h1>
                    <p>SRF Addon developer tools in one place</p>
                </div>
            </div>
            <div class="hub-account">
                <?php if ($me): ?>
                    <span class="hub-chip"><?= h($me['username']) ?> · <?= h(strtoupper($me['role'])) ?></span>
                    <a class="hub-btn" href="common/logout.php">Logout</a>
                <?php else: ?>
                    <a class="hub-btn solid" href="common/login.php">Login</a>
                <?php endif; ?>
            </div>
        </header>

        <h2 class="hub-section-title">Tools</h2>
        <div class="hub-grid">
            <?php foreach ($tools as $t):
                // Admin cards stay hidden unless the user already has access
                if (!empty($t['adminOnly']) && !can($t['perm'])) continue;
                $locked = $me === null;
                $noAccess = $me !== null && !can($t['perm']);
                $tag = $noAccess ? 'div' : 'a';
            ?>
                <<?= $tag ?> class="hub-card tone-<?= h($t['tone']) ?><?= $noAccess ? ' disabled' : '' ?>" <?= $noAccess ? 'aria-disabled="true"' : 'href="' . h($t['href']) . '"' ?>>
                    <div class="hub-card-icon"><svg><use href="#i-<?= h($t['icon']) ?>" /></svg></div>
                    <h2><?= h($t['title']) ?></h2>
                    <p><?= h($t['desc']) ?></p>
                    <div class="hub-card-foot">
                        <?php if ($noAccess): ?>
                            <span>No access for your role</span>
                        <?php else: ?>
                            <span>Open</span>
                        <?php endif; ?>
                        <?php if ($locked): ?>
                            <span class="hub-badge"><svg><use href="#i-key" /></svg>Login required</span>
                        <?php elseif (!empty($t['adminOnly'])): ?>
                            <span class="hub-badge admin">Admin</span>
                        <?php elseif (!$noAccess): ?>
                            <svg><use href="#i-arrow" /></svg>
                        <?php endif; ?>
                    </div>
                </<?= $tag ?>>
            <?php endforeach; ?>
        </div>

        <p class="hub-foot">Opening a tool asks you to log in first if you are not signed in.</p>
    </div>
</body>

</html>
