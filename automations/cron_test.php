<?php

// Path to your Laravel root (adjust if needed)
$basePath = dirname(__DIR__);

// Log file location
$logFile = $basePath . '/automations/cron_db_test.log';

// Helper: write log
function writeLog($message, $logFile) {
    $date = date('Y-m-d H:i:s');
    file_put_contents($logFile, "[$date] $message" . PHP_EOL, FILE_APPEND);
}

try {
    require_once $basePath . '/vendor/autoload.php';

    // Load .env
    $dotenv = Dotenv\Dotenv::createImmutable($basePath);
    $dotenv->safeLoad();

    $host = $_ENV['DB_HOST'] ?? 'localhost';
    $db   = $_ENV['DB_DATABASE'] ?? '';
    $user = $_ENV['DB_USERNAME'] ?? '';
    $pass = $_ENV['DB_PASSWORD'] ?? '';

    // Attempt connection
    $Connect = new mysqli($host, $user, $pass, $db);

    if ($Connect->connect_error) {
        writeLog("❌ DB CONNECTION FAILED: " . $Connect->connect_error, $logFile);
        exit;
    }

    // Optional: simple test query
    $result = $Connect->query("SELECT 1 as test");

    if ($result) {
        writeLog("✅ DB CONNECTED SUCCESSFULLY (Query OK)", $logFile);
    } else {
        writeLog("⚠️ Connected but query failed: " . $Connect->error, $logFile);
    }

    $Connect->close();

} catch (Exception $e) {
    writeLog("🔥 EXCEPTION: " . $e->getMessage(), $logFile);
}