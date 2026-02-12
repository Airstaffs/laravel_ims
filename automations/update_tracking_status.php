<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
date_default_timezone_set('America/Los_Angeles');

// CRITICAL: Force C locale to prevent number formatting issues
setlocale(LC_NUMERIC, 'C');

echo "<h2>🚚 TRACKING STATUS UPDATE CRON JOB</h2>";
echo "Started: " . date('Y-m-d H:i:s') . "<br><br>";

// === DB CONFIG ===
$mysqli = new mysqli("localhost", "imsv2_dbims_user", "Imsv2_dbims_user", "imsv2_dbims");

if ($mysqli->connect_error) {
    die("❌ DB connection failed: " . $mysqli->connect_error);
}

$mysqli->query("SET SESSION wait_timeout = 600");
$mysqli->query("SET SESSION interactive_timeout = 600");

echo "✓ Database connected<br><br>";

// === CONFIGURATION ===
define('MAX_TRACKING_TO_CHECK', 200);
define('BATCH_SIZE', 40);
define('CACHE_DURATION', 21600); // 6 hours
define('OVERDUE_THRESHOLD_DAYS', 14);

$API_KEY = '5EC4C3FCD4929687DC76822C8D154C20';

// === CARRIER CODE MAPPING ===
$carrierMapping = [
    'USPS' => 10001,
    'UPS' => 10002,
    'FedEx' => 10003,
    'DHL' => 10004,
    'China Post' => 10005,
    'TNT' => 10006,
];

/**
 * Detect carrier from tracking number format
 */
function detectCarrier($trackingNumber) {
    $trackingNumber = strtoupper(trim($trackingNumber));
    
    // USPS patterns
    if (preg_match('/^(94|93|92|95)\d{20}$/', $trackingNumber) || 
        preg_match('/^(EA|EC|CP|RA|RB|RC|RR)\d{9}US$/', $trackingNumber)) {
        return 10001;
    }
    
    // UPS patterns
    if (preg_match('/^1Z[A-Z0-9]{16}$/', $trackingNumber) || 
        preg_match('/^\d{26}$/', $trackingNumber)) {
        return 10002;
    }
    
    // FedEx patterns
    if (preg_match('/^\d{15}$/', $trackingNumber) || 
        preg_match('/^96\d{20}$/', $trackingNumber)) {
        return 10003;
    }
    
    // DHL patterns
    if (preg_match('/^\d{10,11}$/', $trackingNumber) ||
        preg_match('/^[A-Z]{3}\d{7}$/', $trackingNumber)) {
        return 10004;
    }
    
    // 12-digit or 9-digit - let API auto-detect
    return 0;
}

/**
 * Validate tracking number format
 */
function isValidTrackingNumber($trackingNumber) {
    $trackingNumber = trim($trackingNumber);
    
    if (empty($trackingNumber)) return false;
    
    $len = strlen($trackingNumber);
    if ($len < 4 || $len > 50) return false;
    
    if (!preg_match('/[A-Za-z0-9]/', $trackingNumber)) return false;
    
    $invalid = ['test', 'pending', 'tba', 'tbd', 'n/a', 'na', 'none', 'null'];
    if (in_array(strtolower($trackingNumber), $invalid)) return false;
    
    return true;
}

// ========================================
// STEP 1: Collect tracking numbers
// ========================================
echo "<h3>📦 STEP 1: Collecting Tracking Numbers</h3>";

// First, let's see what carrier values we have
echo "<strong>🔍 Checking carrier field values:</strong><br>";
$carrierCheck = $mysqli->query("
    SELECT DISTINCT carrier, COUNT(*) as cnt 
    FROM tblproduct 
    WHERE ProductModuleLoc = 'Orders' 
    AND carrier IS NOT NULL AND carrier != ''
    GROUP BY carrier
    ORDER BY cnt DESC
    LIMIT 20
");
if ($carrierCheck) {
    echo "<table style='border-collapse: collapse; margin: 10px 0;'>";
    echo "<tr style='background: #f5f5f5;'><th style='padding: 5px;'>Carrier Value</th><th style='padding: 5px;'>Count</th></tr>";
    while ($cr = $carrierCheck->fetch_assoc()) {
        echo "<tr><td style='padding: 5px;'>" . htmlspecialchars($cr['carrier']) . "</td><td style='padding: 5px;'>{$cr['cnt']}</td></tr>";
    }
    echo "</table><br>";
}

$trackingToCheck = [];
$finalStatuses = ['Delivered', 'Cancelled', 'Refunded'];
$now = time();

$skipReasons = [
    'empty' => 0,
    'invalid_format' => 0,
    'final_status' => 0,
    'cache' => 0,
    'overdue' => 0
];

$query = "
    SELECT 
        ProductID, rtid, itemnumber,
        trackingnumber, trackingnumber2, trackingnumber3, trackingnumber4,
        carrier,
        tracking1_status, tracking2_status, tracking3_status, tracking4_status,
        tracking_last_checked, estimated_deliverydate
    FROM tblproduct
    WHERE ProductModuleLoc = 'Orders'
    AND (
        (trackingnumber IS NOT NULL AND trackingnumber != '')
        OR (trackingnumber2 IS NOT NULL AND trackingnumber2 != '')
        OR (trackingnumber3 IS NOT NULL AND trackingnumber3 != '')
        OR (trackingnumber4 IS NOT NULL AND trackingnumber4 != '')
    )
    ORDER BY ProductID DESC
    LIMIT 500
";

$result = $mysqli->query($query);
echo "Found " . $result->num_rows . " orders with tracking numbers<br><br>";

while ($row = $result->fetch_assoc()) {
    $productID = $row['ProductID'];
    $lastChecked = $row['tracking_last_checked'] ? strtotime($row['tracking_last_checked']) : 0;
    $timeSinceCheck = $now - $lastChecked;
    
    $isOverdue = false;
    if (!empty($row['estimated_deliverydate']) && $row['estimated_deliverydate'] !== '0000-00-00') {
        $estimatedDelivery = strtotime($row['estimated_deliverydate']);
        if ($estimatedDelivery && $estimatedDelivery > 0) {
            $daysPastEstimate = ($now - $estimatedDelivery) / 86400;
            if ($daysPastEstimate > OVERDUE_THRESHOLD_DAYS) {
                $isOverdue = true;
            }
        }
    }
    
    for ($i = 1; $i <= 4; $i++) {
        $trackingField = $i == 1 ? 'trackingnumber' : "trackingnumber{$i}";
        $statusField = "tracking{$i}_status";
        
        $trackingNumber = trim($row[$trackingField] ?? '');
        $currentStatus = trim($row[$statusField] ?? '');
        
        if (empty($trackingNumber)) {
            $skipReasons['empty']++;
            continue;
        }
        
        if (!isValidTrackingNumber($trackingNumber)) {
            $skipReasons['invalid_format']++;
            continue;
        }
        
        if (in_array($currentStatus, $finalStatuses)) {
            $skipReasons['final_status']++;
            continue;
        }
        
        if ($timeSinceCheck < CACHE_DURATION) {
            $skipReasons['cache']++;
            continue;
        }
        
        if ($isOverdue) {
            $skipReasons['overdue']++;
            continue;
        }
        
        if (!isset($trackingToCheck[$trackingNumber])) {
            $trackingToCheck[$trackingNumber] = [
                'carrier' => $row['carrier'] ?? '',
                'records' => []
            ];
        }
        
        $trackingToCheck[$trackingNumber]['records'][] = [
            'product_id' => $productID,
            'order_id' => $row['rtid'],
            'item_id' => $row['itemnumber'],
            'tracking_field_index' => $i
        ];
    }
    
    if (count($trackingToCheck) >= MAX_TRACKING_TO_CHECK) {
        break;
    }
}

echo "<div style='background: #e7f3ff; padding: 10px; border-left: 4px solid #007bff;'>";
echo "<strong>📊 COLLECTION SUMMARY</strong><br>";
echo "Unique tracking numbers to check: <strong>" . count($trackingToCheck) . "</strong><br>";
echo "</div><br>";

echo "<div style='background: #fff3cd; padding: 10px; border-left: 4px solid #ffc107;'>";
echo "<strong>🚫 SKIPPED TRACKING</strong><br>";
echo "Empty/NULL: {$skipReasons['empty']}<br>";
echo "Invalid format: {$skipReasons['invalid_format']}<br>";
echo "Final status: {$skipReasons['final_status']}<br>";
echo "Recently checked: {$skipReasons['cache']}<br>";
echo "Overdue (>" . OVERDUE_THRESHOLD_DAYS . " days): {$skipReasons['overdue']}<br>";
echo "</div><br>";

if (empty($trackingToCheck)) {
    echo "<div style='background: #d4edda; padding: 15px; border: 2px solid #28a745;'>";
    echo "✅ No tracking numbers need checking";
    echo "</div>";
    $mysqli->close();
    exit;
}

// ========================================
// STEP 2: Check 17track API v2.2
// ========================================
echo "<h3>🌐 STEP 2: Checking 17track API v2.2</h3>";

$headers = [
    '17token: ' . $API_KEY,
    'Content-Type: application/json'
];

$trackingNumbers = array_keys($trackingToCheck);
$batches = array_chunk($trackingNumbers, BATCH_SIZE);
$trackingResults = [];

echo "Processing " . count($batches) . " batch(es)...<br><br>";

foreach ($batches as $batchIdx => $batch) {
    echo "<div style='background: #d1ecf1; padding: 10px; margin: 10px 0; border-left: 4px solid #17a2b8;'>";
    echo "<strong>📦 BATCH " . ($batchIdx + 1) . "/" . count($batches) . "</strong><br><br>";
    
    // Build tracking request with carrier hints
    $trackingData = [];
    echo "<table style='width: 100%; border-collapse: collapse;'>";
    echo "<tr style='background: #f5f5f5;'><th>Tracking Number</th><th>Length</th><th>Pattern</th><th>Carrier Hint</th></tr>";
    
    foreach ($batch as $tn) {
        $len = strlen($tn);
        $carrierInfo = $trackingToCheck[$tn];
        $dbCarrier = $carrierInfo['carrier'];
        
        // Analyze pattern
        $pattern = "Unknown";
        $carrierHint = null;
        
        if (preg_match('/^\d{12}$/', $tn)) {
            $pattern = "12 digits";
            // 12-digit could be FedEx, try with carrier code
            if (stripos($dbCarrier, 'fed') !== false || stripos($dbCarrier, 'fdx') !== false) {
                $carrierHint = 10003; // FedEx
            }
        } elseif (preg_match('/^\d{9}$/', $tn)) {
            $pattern = "9 digits";
            // 9-digit is unusual - might be USPS or internal number
        } elseif (preg_match('/^\d{15}$/', $tn)) {
            $pattern = "15 digits (FedEx)";
            $carrierHint = 10003;
        } elseif (preg_match('/^1Z/', $tn)) {
            $pattern = "UPS format";
            $carrierHint = 10002;
        } elseif (preg_match('/^94\d{20}$/', $tn)) {
            $pattern = "USPS format";
            $carrierHint = 10001;
        }
        
        $trackItem = ['number' => $tn];
        
        // Add carrier hint if we have one
        if ($carrierHint !== null) {
            $trackItem['carrier'] = $carrierHint;
        }
        
        $trackingData[] = $trackItem;
        
        echo "<tr>";
        echo "<td><strong>{$tn}</strong></td>";
        echo "<td>{$len}</td>";
        echo "<td>{$pattern}</td>";
        echo "<td>" . ($carrierHint ? "Code {$carrierHint}" : "Auto-detect") . "</td>";
        echo "</tr>";
    }
    echo "</table><br>";
    
    // Manual JSON construction to avoid ANY PHP formatting issues
    $jsonItems = [];
    foreach ($trackingData as $item) {
        if (isset($item['carrier'])) {
            // Manually construct JSON to guarantee integer format
            $jsonItems[] = '{"number":"' . $item['number'] . '","carrier":' . intval($item['carrier']) . '}';
        } else {
            $jsonItems[] = '{"number":"' . $item['number'] . '"}';
        }
    }
    $requestPayload = '[' . implode(',', $jsonItems) . ']';
    
    echo "<details><summary>📋 Request JSON (manual build)</summary><pre style='background: #f5f5f5; padding: 10px;'>";
    echo htmlspecialchars($requestPayload);
    echo "</pre></details><br>";
    
    echo "📤 Step 1: Register with 17track...<br>";
    
    // Step 1: Register
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, 'https://api.17track.net/track/v2.2/register');
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $requestPayload);
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 30);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    
    $registerResponse = curl_exec($ch);
    $registerData = json_decode($registerResponse, true);
    
    if (isset($registerData['data']['accepted'])) {
        echo "✅ Accepted: " . count($registerData['data']['accepted']) . "<br>";
    }
    if (isset($registerData['data']['rejected'])) {
        echo "⚠️ Rejected: " . count($registerData['data']['rejected']) . "<br>";
        foreach ($registerData['data']['rejected'] as $rej) {
            $rejNum = $rej['number'] ?? 'unknown';
            $errCode = $rej['error']['code'] ?? 'unknown';
            $errMsg = $rej['error']['message'] ?? '';
            // -18019901 = already registered (OK)
            if ($errCode != -18019901) {
                echo "  → {$rejNum}: Error {$errCode} - {$errMsg}<br>";
            }
        }
    }
    
    echo "<br>⏳ Waiting 2 seconds...<br><br>";
    sleep(2);
    
    // Step 2: Get tracking info
    echo "📥 Step 2: Getting tracking info...<br>";
    
    curl_setopt($ch, CURLOPT_URL, 'https://api.17track.net/track/v2.2/gettrackinfo');
    curl_setopt($ch, CURLOPT_POSTFIELDS, $requestPayload);
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    echo "HTTP: {$httpCode}<br>";
    
    if ($httpCode !== 200) {
        echo "<span style='color: red;'>❌ API Error</span><br>";
        echo "</div>";
        continue;
    }
    
    $data = json_decode($response, true);
    
    if (!isset($data['data'])) {
        echo "<span style='color: red;'>❌ Invalid response</span><br>";
        echo "</div>";
        continue;
    }
    
    $acceptedTracks = $data['data']['accepted'] ?? [];
    $rejectedTracks = $data['data']['rejected'] ?? [];
    
    if (!empty($rejectedTracks)) {
        echo "<div style='background: #fff3cd; padding: 10px; margin: 10px 0;'>";
        echo "⚠️ <strong>Rejected:</strong><br>";
        foreach ($rejectedTracks as $rejected) {
            $rejNum = $rejected['number'] ?? 'unknown';
            $errCode = $rejected['error']['code'] ?? 'unknown';
            $errMsg = $rejected['error']['message'] ?? '';
            echo "→ {$rejNum}: {$errCode} - {$errMsg}<br>";
        }
        echo "</div>";
    }
    
    echo "<br>✅ Received info for " . count($acceptedTracks) . " tracking numbers<br><br>";
    
    foreach ($acceptedTracks as $track) {
        $tn = $track['number'] ?? '';
        if (empty($tn)) continue;
        
        $trackInfo = $track['track_info'] ?? [];
        $latestStatus = $trackInfo['latest_status'] ?? [];
        $latestEvent = $trackInfo['latest_event'] ?? [];
        
        $statusCode = $latestStatus['status'] ?? 0;
        
        // Map status codes
        $statusText = 'Unknown';
        switch ($statusCode) {
            case 40:
                $statusText = 'Delivered';
                break;
            case 10:
            case 20:
            case 30:
                $statusText = 'In Transit';
                break;
            case 35:
            case 50:
                $statusText = 'Delivery Exception';
                break;
            case 0:
                $statusText = 'Not Found';
                break;
        }
        
        $deliveredDate = null;
        if ($latestEvent && isset($latestEvent['time_iso'])) {
            try {
                $deliveredDate = (new DateTime($latestEvent['time_iso']))->format('Y-m-d H:i:s');
            } catch (Exception $e) {
                // Ignore
            }
        }
        
        $carrierName = $trackInfo['carrier']['name'] ?? 'Unknown';
        
        $trackingResults[$tn] = [
            'status' => $statusText,
            'delivered_date' => $deliveredDate,
            'carrier' => $carrierName
        ];
        
        $statusColor = '#6c757d';
        if ($statusText === 'Delivered') $statusColor = '#28a745';
        elseif ($statusText === 'In Transit') $statusColor = '#007bff';
        elseif ($statusText === 'Delivery Exception') $statusColor = '#ffc107';
        elseif ($statusText === 'Not Found') $statusColor = '#dc3545';
        
        echo "→ <strong>{$tn}</strong>: ";
        echo "<span style='color: {$statusColor}; font-weight: bold;'>{$statusText}</span>";
        if ($deliveredDate) echo " | {$deliveredDate}";
        echo " | {$carrierName}<br>";
    }
    
    echo "</div>";
    
    if ($batchIdx < count($batches) - 1) {
        sleep(2);
    }
}

echo "<br><div style='background: #d4edda; padding: 10px; border-left: 4px solid #28a745;'>";
echo "✅ API calls complete: " . count($trackingResults) . " results<br>";
echo "</div><br>";

// ========================================
// STEP 3: Update database
// ========================================
echo "<h3>💾 STEP 3: Updating Database</h3>";

$updatedCount = 0;
$errorCount = 0;
$skippedCount = 0;

foreach ($trackingToCheck as $trackingNumber => $data) {
    if (!isset($trackingResults[$trackingNumber])) {
        $skippedCount++;
        continue;
    }
    
    $result = $trackingResults[$trackingNumber];
    $status = $result['status'];
    $deliveredDate = $result['delivered_date'];
    
    if (($status === 'Unknown' && !$deliveredDate) || $status === 'Not Found') {
        $skippedCount++;
        continue;
    }
    
    foreach ($data['records'] as $record) {
        $productID = $record['product_id'];
        $trackingIndex = $record['tracking_field_index'];
        
        $statusField = "tracking{$trackingIndex}_status";
        $dateField = "tracking{$trackingIndex}_delivered_date";
        
        $updateFields = [];
        $updateValues = [];
        $updateTypes = "";
        
        $updateFields[] = "{$statusField} = ?";
        $updateValues[] = $status;
        $updateTypes .= "s";
        
        if ($deliveredDate) {
            $updateFields[] = "{$dateField} = ?";
            $updateValues[] = $deliveredDate;
            $updateTypes .= "s";
        }
        
        $updateFields[] = "tracking_last_checked = NOW()";
        
        $updateSQL = "UPDATE tblproduct SET " . implode(", ", $updateFields) . " WHERE ProductID = ?";
        $updateValues[] = $productID;
        $updateTypes .= "i";
        
        $stmt = $mysqli->prepare($updateSQL);
        if (!$stmt) {
            $errorCount++;
            continue;
        }
        
        $stmt->bind_param($updateTypes, ...$updateValues);
        
        if ($stmt->execute()) {
            echo "→ ProductID {$productID} | tracking{$trackingIndex} = '{$status}'";
            if ($deliveredDate) echo " | {$deliveredDate}";
            echo "<br>";
            $updatedCount++;
        } else {
            $errorCount++;
        }
        
        $stmt->close();
    }
}

echo "<br><div style='background: #e7f3ff; padding: 10px; border-left: 4px solid #007bff;'>";
echo "Records updated: {$updatedCount} | Skipped: {$skippedCount} | Errors: {$errorCount}<br>";
echo "</div><br>";

// ========================================
// STEP 4: Update main delivery status
// ========================================
echo "<h3>🔄 STEP 4: Updating Main Delivery Status</h3>";

$mainStatusUpdated = 0;

$query = "
    SELECT 
        ProductID, delivery_status, datedelivered,
        tracking1_status, tracking2_status, tracking3_status, tracking4_status,
        tracking1_delivered_date, tracking2_delivered_date, tracking3_delivered_date, tracking4_delivered_date
    FROM tblproduct
    WHERE ProductModuleLoc = 'Orders'
    AND (tracking1_status IS NOT NULL OR tracking2_status IS NOT NULL 
         OR tracking3_status IS NOT NULL OR tracking4_status IS NOT NULL)
";

$result = $mysqli->query($query);

while ($row = $result->fetch_assoc()) {
    $productID = $row['ProductID'];
    
    $statuses = array_filter([
        $row['tracking1_status'],
        $row['tracking2_status'],
        $row['tracking3_status'],
        $row['tracking4_status']
    ]);
    
    $deliveredDates = array_filter([
        $row['tracking1_delivered_date'],
        $row['tracking2_delivered_date'],
        $row['tracking3_delivered_date'],
        $row['tracking4_delivered_date']
    ], function($date) {
        return $date && $date !== '0000-00-00 00:00:00';
    });
    
    if (empty($statuses)) continue;
    
    $newMainStatus = null;
    $newDeliveredDate = null;
    
    if (in_array('Delivered', $statuses)) {
        $newMainStatus = 'Delivered';
        if (!empty($deliveredDates)) {
            $newDeliveredDate = min($deliveredDates);
        }
    } elseif (in_array('In Transit', $statuses)) {
        $newMainStatus = 'In Transit';
    } elseif (in_array('Delivery Exception', $statuses)) {
        $newMainStatus = 'Delivery Exception';
    } else {
        $newMainStatus = reset($statuses);
    }
    
    $currentStatus = $row['delivery_status'] ?? '';
    
    if (in_array($currentStatus, $finalStatuses)) continue;
    
    $needsUpdate = false;
    $updateSQL = "UPDATE tblproduct SET ";
    $updateParts = [];
    $updateValues = [];
    $updateTypes = "";
    
    if ($newMainStatus && $newMainStatus !== $currentStatus) {
        $updateParts[] = "delivery_status = ?";
        $updateValues[] = $newMainStatus;
        $updateTypes .= "s";
        $needsUpdate = true;
    }
    
    if ($newDeliveredDate && $newMainStatus === 'Delivered') {
        $currentDeliveredDate = $row['datedelivered'] ?? '';
        if (empty($currentDeliveredDate) || $currentDeliveredDate === '0000-00-00 00:00:00') {
            $updateParts[] = "datedelivered = ?";
            $updateValues[] = $newDeliveredDate;
            $updateTypes .= "s";
            $needsUpdate = true;
        }
    }
    
    if ($needsUpdate) {
        $updateSQL .= implode(", ", $updateParts) . " WHERE ProductID = ?";
        $updateValues[] = $productID;
        $updateTypes .= "i";
        
        $stmt = $mysqli->prepare($updateSQL);
        $stmt->bind_param($updateTypes, ...$updateValues);
        
        if ($stmt->execute()) {
            echo "→ ProductID {$productID}: '{$newMainStatus}'<br>";
            $mainStatusUpdated++;
        }
        
        $stmt->close();
    }
}

echo "<br><div style='background: #d4edda; padding: 10px; border-left: 4px solid #28a745;'>";
echo "✅ Main status updated: {$mainStatusUpdated}<br>";
echo "</div><br>";

// ========================================
// FINAL SUMMARY
// ========================================
echo "<div style='background: #007bff; color: white; padding: 20px; border-radius: 8px;'>";
echo "<h3>📊 FINAL SUMMARY</h3>";
echo "<hr style='border-color: rgba(255,255,255,0.3);'>";
echo "Tracking checked: " . count($trackingResults) . "<br>";
echo "Individual statuses updated: {$updatedCount}<br>";
echo "Main statuses updated: {$mainStatusUpdated}<br>";
echo "Skipped: {$skippedCount} | Errors: {$errorCount}<br>";
echo "<hr style='border-color: rgba(255,255,255,0.3);'>";
echo "<strong>🚫 API QUOTA SAVED:</strong><br>";
echo "Invalid format: {$skipReasons['invalid_format']}<br>";
echo "Final status: {$skipReasons['final_status']}<br>";
echo "Cached: {$skipReasons['cache']}<br>";
echo "Overdue: {$skipReasons['overdue']}<br>";
echo "<hr style='border-color: rgba(255,255,255,0.3);'>";
echo "Finished: " . date('Y-m-d H:i:s') . "<br>";
echo "</div>";

$mysqli->close();
?>