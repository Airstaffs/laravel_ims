<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
date_default_timezone_set('America/Los_Angeles');

echo "<h2>🚚 TRACKING STATUS UPDATE CRON JOB (17track API v4)</h2>";
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
// Map your internal carrier names to 17track carrier codes (as integers)
$carrierMapping = [
    'USPS' => 10001,
    'UPS' => 10002,
    'FedEx' => 10003,
    'DHL' => 10004,
    'China Post' => 10005,
    'TNT' => 10006,
    // Add more carriers as needed
    // See: https://api.17track.net/en/doc/carriers
];

/**
 * Detect carrier from tracking number format
 */
function detectCarrier($trackingNumber) {
    $trackingNumber = strtoupper(trim($trackingNumber));
    
    // USPS patterns
    if (preg_match('/^(94|93|92|94|95)\d{20}$/', $trackingNumber) || // USPS Tracking
        preg_match('/^(EA|EC|CP|RA|RB|RC|RR)\d{9}US$/', $trackingNumber)) { // USPS International
        return 10001;
    }
    
    // UPS patterns
    if (preg_match('/^1Z[A-Z0-9]{16}$/', $trackingNumber) || // UPS standard
        preg_match('/^\d{26}$/', $trackingNumber)) { // UPS alternative
        return 10002;
    }
    
    // FedEx patterns - MORE SPECIFIC
    if (preg_match('/^\d{12}$/', $trackingNumber)) { // FedEx 12-digit (most common)
        // 12-digit can also be other carriers, so let auto-detect handle it
        return 0;
    }
    if (preg_match('/^\d{15}$/', $trackingNumber)) { // FedEx 15-digit
        return 10003;
    }
    if (preg_match('/^\d{20}$/', $trackingNumber)) { // FedEx 20-digit
        return 10003;
    }
    if (preg_match('/^96\d{20}$/', $trackingNumber)) { // FedEx SmartPost
        return 10003;
    }
    
    // DHL patterns
    if (preg_match('/^\d{10,11}$/', $trackingNumber) ||
        preg_match('/^[A-Z]{3}\d{7}$/', $trackingNumber)) {
        return 10004;
    }
    
    // If 9 digits, likely not FedEx - let API auto-detect
    if (preg_match('/^\d{9}$/', $trackingNumber)) {
        return 0;
    }
    
    // Auto-detect (carrier code 0)
    return 0;
}

/**
 * Validate tracking number format
 */
function isValidTrackingNumber($trackingNumber) {
    $trackingNumber = trim($trackingNumber);
    
    // Must not be empty
    if (empty($trackingNumber)) return false;
    
    // Must be between 4 and 50 characters
    $len = strlen($trackingNumber);
    if ($len < 4 || $len > 50) return false;
    
    // Must contain alphanumeric characters
    if (!preg_match('/[A-Za-z0-9]/', $trackingNumber)) return false;
    
    // Should not contain common placeholder text
    $invalid = ['test', 'pending', 'tba', 'tbd', 'n/a', 'na', 'none', 'null'];
    if (in_array(strtolower($trackingNumber), $invalid)) return false;
    
    return true;
}

// ========================================
// STEP 1: Collect tracking numbers
// ========================================
echo "<h3>📦 STEP 1: Collecting Tracking Numbers</h3>";

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
        ProductID,
        rtid,
        itemnumber,
        trackingnumber,
        trackingnumber2,
        trackingnumber3,
        trackingnumber4,
        carrier,
        tracking1_status,
        tracking2_status,
        tracking3_status,
        tracking4_status,
        tracking_last_checked,
        estimated_deliverydate
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

$processedCount = 0;

while ($row = $result->fetch_assoc()) {
    $productID = $row['ProductID'];
    $lastChecked = $row['tracking_last_checked'] ? strtotime($row['tracking_last_checked']) : 0;
    $timeSinceCheck = $now - $lastChecked;
    
    // Check if overdue
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
    
    // Check each tracking field
    for ($i = 1; $i <= 4; $i++) {
        $trackingField = $i == 1 ? 'trackingnumber' : "trackingnumber{$i}";
        $statusField = "tracking{$i}_status";
        
        $trackingNumber = trim($row[$trackingField] ?? '');
        $currentStatus = trim($row[$statusField] ?? '');
        
        // Skip empty
        if (empty($trackingNumber)) {
            $skipReasons['empty']++;
            continue;
        }
        
        // *** NEW: Validate tracking number format ***
        if (!isValidTrackingNumber($trackingNumber)) {
            $skipReasons['invalid_format']++;
            echo "⚠️ Invalid format: {$trackingNumber} (ProductID {$productID})<br>";
            continue;
        }
        
        // Skip final status
        if (in_array($currentStatus, $finalStatuses)) {
            $skipReasons['final_status']++;
            continue;
        }
        
        // Skip recently checked
        if ($timeSinceCheck < CACHE_DURATION) {
            $skipReasons['cache']++;
            continue;
        }
        
        // Skip overdue
        if ($isOverdue) {
            $skipReasons['overdue']++;
            continue;
        }
        
        // Add to check list
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
        
        $processedCount++;
    }
    
    if (count($trackingToCheck) >= MAX_TRACKING_TO_CHECK) {
        echo "⚠️ Reached MAX_TRACKING_TO_CHECK limit<br>";
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
echo "Invalid format: <strong style='color: #dc3545;'>{$skipReasons['invalid_format']}</strong><br>";
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
// STEP 2: Check 17track API v4
// ========================================
echo "<h3>🌐 STEP 2: Checking 17track API v4</h3>";

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
    
    // Build tracking request with carrier detection
    $trackingData = [];
    echo "<strong>Building request:</strong><br>";
    foreach ($batch as $tn) {
        $carrierInfo = $trackingToCheck[$tn];
        $carrierName = $carrierInfo['carrier'];
        
        // Try to get carrier code from mapping, or detect from format
        $carrierCode = 0; // Auto-detect
        if (!empty($carrierName) && isset($carrierMapping[$carrierName])) {
            $carrierCode = $carrierMapping[$carrierName];
        } else {
            $carrierCode = detectCarrier($tn);
        }
        
        $trackItem = ['number' => $tn];
        
        // Only add carrier if we detected one (not auto-detect)
        // IMPORTANT: Cast to integer to prevent JSON encoding as string
        if ($carrierCode > 0) {
            $trackItem['carrier'] = (int)$carrierCode;
        }
        
        $trackingData[] = $trackItem;
        
        $carrierDisplay = $carrierCode == 0 ? 'auto-detect' : $carrierCode;
        echo "→ {$tn} (length: " . strlen($tn) . ", carrier: {$carrierDisplay})<br>";
    }
    
    echo "<br><details><summary>📋 Request JSON</summary><pre style='background: #f5f5f5; padding: 10px;'>";
    echo htmlspecialchars(json_encode($trackingData, JSON_PRETTY_PRINT));
    echo "</pre></details><br>";
    
    echo "<br>📤 Calling 17track API v4...<br>";
    
    // API v4: First register, then get track info (still 2-step process in v4)
    // Step 1: Register tracking numbers
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, 'https://api.17track.net/track/v2.2/register');
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($trackingData));
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 30);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    
    $registerResponse = curl_exec($ch);
    $registerData = json_decode($registerResponse, true);
    
    echo "📋 Registration response:<br>";
    if (isset($registerData['data']['accepted'])) {
        echo "✅ Accepted: " . count($registerData['data']['accepted']) . "<br>";
    }
    if (isset($registerData['data']['rejected'])) {
        echo "⚠️ Rejected: " . count($registerData['data']['rejected']) . "<br>";
        foreach ($registerData['data']['rejected'] as $rej) {
            $rejNum = $rej['number'] ?? 'unknown';
            $errCode = $rej['error']['code'] ?? 'unknown';
            $errMsg = $rej['error']['message'] ?? '';
            echo "  → {$rejNum}: Error {$errCode} - {$errMsg}<br>";
        }
    }
    
    echo "<br>⏳ Waiting 2 seconds...<br><br>";
    sleep(2);
    
    // Step 2: Get tracking info
    curl_setopt($ch, CURLOPT_URL, 'https://api.17track.net/track/v2.2/gettrackinfo');
    curl_setopt($ch, CURLOPT_POSTFIELDS, $jsonPayload); // Use same pre-encoded JSON
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    echo "📥 Tracking info response - HTTP: {$httpCode}<br>";
    
    if ($httpCode !== 200) {
        echo "<span style='color: red;'>❌ API Error</span><br>";
        echo "Response: " . substr($response, 0, 300) . "<br>";
        echo "</div>";
        continue;
    }
    
    $data = json_decode($response, true);
    
    // Debug: Show raw response structure
    echo "<details style='margin: 10px 0;'><summary>🔍 Debug: API Response</summary>";
    echo "<pre style='background: #f5f5f5; padding: 10px; overflow: auto; max-height: 300px;'>";
    echo htmlspecialchars(json_encode($data, JSON_PRETTY_PRINT));
    echo "</pre></details>";
    
    if (!isset($data['data'])) {
        echo "<span style='color: red;'>❌ Invalid response format</span><br>";
        echo "</div>";
        continue;
    }
    
    // v4 API structure: data.accepted[] or data.rejected[]
    $acceptedTracks = $data['data']['accepted'] ?? [];
    $rejectedTracks = $data['data']['rejected'] ?? [];
    
    if (!empty($rejectedTracks)) {
        echo "<div style='background: #fff3cd; padding: 10px; margin: 10px 0;'>";
        echo "⚠️ <strong>Rejected tracking numbers:</strong><br>";
        foreach ($rejectedTracks as $rejected) {
            $rejNum = $rejected['number'] ?? 'unknown';
            $errCode = $rejected['error']['code'] ?? 'unknown';
            $errMsg = $rejected['error']['message'] ?? 'No message';
            echo "→ {$rejNum}: Error {$errCode} - {$errMsg}<br>";
        }
        echo "</div>";
    }
    
    echo "<br>✅ Received info for " . count($acceptedTracks) . " tracking numbers<br><br>";
    
    foreach ($acceptedTracks as $track) {
        $tn = $track['number'] ?? '';
        if (empty($tn)) continue;
        
        // v4 uses 'track_info' object with 'latest_status' and 'latest_event'
        $trackInfo = $track['track_info'] ?? [];
        $latestStatus = $trackInfo['latest_status'] ?? [];
        $latestEvent = $trackInfo['latest_event'] ?? [];
        
        $status = $latestStatus['status'] ?? 0;
        $substatus = $latestStatus['substatus'] ?? 0;
        
        // Map v4 status codes
        $statusText = 'Unknown';
        $deliveredDate = null;
        
        // Status codes in v4:
        // 0 = Not Found
        // 10 = Info Received
        // 20 = In Transit
        // 30 = Pick Up
        // 35 = Undelivered
        // 40 = Delivered
        // 50 = Expired/Exception
        
        switch ($status) {
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
        
        // Get delivered date from latest event
        if ($latestEvent && isset($latestEvent['time_iso'])) {
            try {
                $deliveredDate = (new DateTime($latestEvent['time_iso']))->format('Y-m-d H:i:s');
            } catch (Exception $e) {
                // Ignore
            }
        }
        
        // If delivered but no date from latest_event, try tracking.data array
        if ($statusText === 'Delivered' && !$deliveredDate) {
            $trackingEvents = $trackInfo['tracking']['data'] ?? [];
            foreach ($trackingEvents as $event) {
                if (isset($event['time_iso'])) {
                    try {
                        $deliveredDate = (new DateTime($event['time_iso']))->format('Y-m-d H:i:s');
                        break;
                    } catch (Exception $e) {
                        continue;
                    }
                }
            }
        }
        
        $carrierName = $trackInfo['carrier']['name'] ?? 'Unknown';
        $description = $latestEvent['description'] ?? 'No description';
        
        $trackingResults[$tn] = [
            'status' => $statusText,
            'delivered_date' => $deliveredDate,
            'carrier' => $carrierName,
            'description' => $description
        ];
        
        // Display
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
    
    // Delay between batches
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
    
    // Skip if no useful status
    if (($status === 'Unknown' && !$deliveredDate) || $status === 'Not Found') {
        $skippedCount++;
        continue;
    }
    
    // Update each record
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