<?php
require __DIR__ . '/../common/auth.php';

header('Content-Type: application/json');
requireLogin(null, true);

$file = 'decryptionKey.json';

if (!file_exists($file)) {
    echo json_encode([
        "status" => true,
        "data"   => []
    ]);
    exit;
}

$jsonData  = file_get_contents($file);
$dataArray = json_decode($jsonData, true);

if (!is_array($dataArray)) {
    $dataArray = [];
}

$result = [];
$id = 1;

$showKeys = can('manage_keys');
foreach ($dataArray as $name => $key) {
    // Non-admins only need names for the dropdown
    if (!$showKeys) {
        unset($key['keys']);
    }
    $result[] = $key;
}

echo json_encode([
    "status" => true,
    "data"   => $result
], JSON_PRETTY_PRINT);