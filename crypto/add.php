<?php
require __DIR__ . '/../common/auth.php';

header('Content-Type: application/json');
// Key management is public too, same as Decrypt/Encrypt
checkCsrf();

$projectName   = trim($_POST['project_name'] ?? '');
$decryptionKey = trim($_POST['decryption_key'] ?? '');

if (empty($projectName) || empty($decryptionKey)) {
    echo json_encode(["status" => false, "message" => "Invalid Data"]);
    exit;
}

$file = 'decryptionKey.json';

if (!file_exists($file)) {
    file_put_contents($file, json_encode([], JSON_PRETTY_PRINT));
}

$dataArray = json_decode(file_get_contents($file), true);

if (!is_array($dataArray)) {
    $dataArray = [];
}

/* ---- Duplicate Name Check ---- */
foreach ($dataArray as $row) {
    if (strtolower($row['name']) === strtolower($projectName)) {
        echo json_encode([
            "status" => false,
            "message" => "name already exists"
        ]);
        exit;
    }
}

/* ---- Generate New ID (Last Inserted + 1) ---- */
if (empty($dataArray)) {
    $newId = 1;
} else {
    $lastElement = end($dataArray);   // Last inserted row
    $newId = $lastElement['id'] + 1;
}

/* ---- Add Entry ---- */
$dataArray[] = [
    "id"   => $newId,
    "name" => $projectName,
    "keys" => $decryptionKey
];

/* ---- Save ---- */
file_put_contents(
    $file,
    json_encode($dataArray, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES),
    LOCK_EX
);

echo json_encode([
    "status" => true,
    "message" => "details added successfully",
    "inserted_id" => $newId
]);