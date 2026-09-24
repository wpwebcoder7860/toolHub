<?php
require __DIR__ . '/../common/auth.php';

header('Content-Type: application/json');
// Key management is public too, same as Decrypt/Encrypt
checkCsrf();

$id            = $_POST['id'] ?? '';
$projectName   = trim($_POST['project_name'] ?? '');
$decryptionKey = trim($_POST['decryption_key'] ?? '');

if (empty($id) || empty($projectName) || empty($decryptionKey)) {
    echo json_encode(["status" => false, "message" => "Invalid Data"]);
    exit;
}

$file = 'decryptionKey.json';

if (!file_exists($file)) {
    echo json_encode(["status" => false, "message" => "File not found"]);
    exit;
}

$dataArray = json_decode(file_get_contents($file), true);

if (!is_array($dataArray)) {
    $dataArray = [];
}

$found = false;

/* ---- Update by ID ---- */
foreach ($dataArray as &$row) {
    if ($row['id'] == $id) {
        $row['name'] = $projectName;
        $row['keys'] = $decryptionKey;
        $found = true;
        break;
    }
}

if (!$found) {
    echo json_encode(["status" => false, "message" => "ID not found"]);
    exit;
}

/* ---- Save ---- */
file_put_contents(
    $file,
    json_encode($dataArray, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES),
    LOCK_EX
);

echo json_encode([
    "status" => true,
    "message" => "details updated successfully"
]);