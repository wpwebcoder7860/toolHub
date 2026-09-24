<?php
require __DIR__ . '/../common/auth.php';

header('Content-Type: application/json');
// Decrypt/Encrypt (and key management) are fully public now — everyone
// gets the raw key material too, needed for the public ADD/EDIT flow.

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

foreach ($dataArray as $name => $key) {
    $result[] = $key;
}

echo json_encode([
    "status" => true,
    "data"   => $result
], JSON_PRETTY_PRINT);