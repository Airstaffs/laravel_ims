<?php
file_put_contents('debug_log.txt', "Ran PHP at: " . date('c') . "\n", FILE_APPEND);

$allowedOrigins = ['https://tecniquality.com', 'http://localhost:8000'];
$origin = $_SERVER['HTTP_ORIGIN'] ?? '';

// Handle preflight requests
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    if (in_array($origin, $allowedOrigins)) {
        header("Access-Control-Allow-Origin: $origin");
        header("Access-Control-Allow-Methods: POST, OPTIONS");
        header("Access-Control-Allow-Headers: Content-Type");
        http_response_code(200);
        exit();
    } else {
        http_response_code(403);
        exit("Forbidden");
    }
}

// Validate origin before continuing
if (!in_array($origin, $allowedOrigins)) {
    http_response_code(403);
    exit("Forbidden");
}

// Set CORS headers for actual request
header("Access-Control-Allow-Origin: $origin");
header("Access-Control-Allow-Methods: POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type");

// Get JSON input
$input = json_decode(file_get_contents("php://input"), true);

// Forward to Zebra printer endpoint
$printerUrl = "http://99.0.87.190:1450/ims/Admin/modules/PRD-RPN-PCN/print.php";

$ch = curl_init($printerUrl);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($input));
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    'Content-Type: application/x-www-form-urlencoded'
]);
$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

http_response_code($httpCode);
echo $response;
