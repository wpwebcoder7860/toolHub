<?php
require __DIR__ . '/../common/auth.php';
if (isset($_GET['getFinal'])) {
    requireLogin(($_GET['mode'] ?? '') === 'encrypt' ? 'encrypt' : 'decrypt', true);
} elseif (!can('decrypt') && !can('encrypt')) {
    requireLogin('decrypt', true);
}

class DataDecryptor
{
    private string $jarPath = 'aes_enc_omini.jar';
    private string $tempFilePath = "_tmp_chunks.txt";   // Chunk stored here
    private string $keyFile = "decryptionKey.json";
    private string $password = "";
    public function __construct()
    {
        if (!file_exists($this->tempFilePath)) {
            file_put_contents($this->tempFilePath, ""); // create empty file
        }
    }
    public function handleRequest()
    {
        // CHECK IF CHUNK DATA IS COMING
        if (isset($_POST['chunk']) && isset($_POST['index'])) {
            return $this->saveChunk();
        }

        // CHECK IF FINAL DECRYPT REQUEST
        if (isset($_GET['getFinal'])) {
            return $this->processFinalData();
        }
        return json_encode([
            "status" => false,
            "error" => "Invalid request"
        ]);
    }

    /** ---------------------------------------------------------
     *  SAVE EACH CHUNK IN FILE (APPEND MODE)
     * --------------------------------------------------------- */
    private function saveChunk(): string
    {
        $chunk = $_POST['chunk'];
        $index = intval($_POST['index']);
        // Write (append) chunk to file
        file_put_contents($this->tempFilePath, $chunk, FILE_APPEND);
        return "OK";
    }
    /** ---------------------------------------------------------
     *   WHEN ALL CHUNKS ARE SENT — FINAL DECRYPT/ENCRYPT EXECUTE
     * --------------------------------------------------------- */
    private function processFinalData(): string
    {
        // Ensure full file path
        $encryptedFilePath = $this->tempFilePath;
        $decryptedFilePath = $this->keyFile;

        if (!file_exists($encryptedFilePath)) {
            mkdir($encryptedFilePath, 775);
        }

        if (!file_exists($decryptedFilePath)) {
            mkdir($decryptedFilePath, 775);
        }


        $jsonData = file_get_contents($this->keyFile);
        $data = json_decode($jsonData, true);
        $output = [];
        foreach ($data as $k => $v) {
            $output[$v['name']] = $v['keys'];
        }

        if (isset($output[$_GET['project_name']])) {
            $this->password = $output[$_GET['project_name']];
        }

        // 'encrypt' mode = jar flag 1, 'decrypt' (default) = jar flag 0
        $mode = ($_GET['mode'] ?? 'decrypt') === 'encrypt' ? 'encrypt' : 'decrypt';
        $result = $this->runJar($encryptedFilePath, $mode === 'encrypt' ? '1' : '0');

        // Delete temp chunk-file
        unlink($encryptedFilePath);

        // Decrypted output is JSON; encrypted output is a raw ciphertext string
        $responseData = $mode === 'encrypt' ? trim($result) : json_decode(trim($result), true);

        return json_encode([
            "status" => true,
            "data"   => $responseData
        ], JSON_PRETTY_PRINT);
    }
    /** ---------------------------------------------------------
     *   EXECUTE JAVA JAR ENCRYPT/DECRYPT COMMAND
     * --------------------------------------------------------- */
    private function runJar(string $data, string $modeFlag): string
    {
        $command = escapeshellcmd("java -jar {$this->jarPath} {$modeFlag} \"{$data}\" {$this->password}");
        return shell_exec($command);
    }
}
header("Content-Type: application/json");
$decryptor = new DataDecryptor();
echo $decryptor->handleRequest();
