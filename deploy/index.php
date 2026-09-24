<?php
declare(strict_types=1);
require __DIR__ . '/../common/auth.php';
// Public tool — no login required

// index.html stays the source; direct URL is denied in .htaccess
readfile(__DIR__ . '/index.html');
