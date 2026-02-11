<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
date_default_timezone_set('America/Los_Angeles');

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

$API_KEY = '5EC4C3FCD4929687DC76822C8D154C20';

// ========================================
// CARRIER MAPPING: Map your DB carrier names to 17track carrier codes
// Full list: https://api.17track.net/en/doc
// ========================================
$carrierMapping = [
    // US Carriers
    'USPS' => 70019,
    'UPS' => 70002,
    'FedEx' => 70001,
    'FedEx Express' => 70001,
    'FedEx Ground' => 70001,
    'FedEx Home Delivery' => 70001,
    'FedEx SmartPost' => 70157,
    'DHL' => 70003,
    'DHL Express' => 70003,
    'DHL eCommerce' => 70015,
    'Amazon Logistics' => 70172,
    'OnTrac' => 70049,
    'LaserShip' => 70050,
    'LSO' => 70050, // LaserShip alias
    'Central Transport' => null, // Let 17track auto-detect (not a standard international carrier)
    
    // International
    'Canada Post' => 70020,
    'Royal Mail' => 70030,
    'China Post' => 70013,
    'EMS' => 70012,
    'SF Express' => 70015,
    'Yun Express' => 70135,
    'Yanwen' => 70048,
    'DPD' => 70021,
    'TNT' => 70008,
    'Hermes' => 70027,
    'Parcelforce' => 70039,
    
    // Add more carriers as needed
];

/**
 * Get 17track carrier code from carrier name
 */
function get17trackCarrier($carrierName, $carrierMapping) {
    if (empty($carrierName)) {
        return null; // Let 17track auto-detect
    }
    
    // Try exact match first
    if (isset($carrierMapping[$carrierName])) {
        return $carrierMapping[$carrierName];
    }
    
    // Try case-insensitive partial match
    $carrierName = strtolower(trim($carrierName));
    foreach ($carrierMapping as $key => $code) {
        if (stripos($key, $carrierName) !== false || stripos($carrierName, strtolower($key)) !== false) {
            return $code;
        }
    }
    
    // Common abbreviations
    if (strpos($carrierName, 'ups') !== false) return 70002;
    if (strpos($carrierName, 'usps') !== false) return 70019;
    if (strpos($carrierName, 'fedex') !== false || strpos($carrierName, 'fed ex') !== false) return 70001;
    if (strpos($carrierName, 'dhl') !== false) return 70003;
    if (strpos($carrierName, 'amazon') !== false) return 70172;
    
    return null; // Let 17track try auto-detection
}

/**
 * Validate tracking number format for known carriers
 */
function validateTrackingFormat($trackingNumber, $carrierName) {
    $tn = preg_replace('/\s+/', '', $trackingNumber); // Remove spaces
    
    // FedEx validation (12, 15, 20, or 22 digits)
    if (stripos($carrierName, 'fedex') !== false) {
        if (preg_match('/^\d{12}$|^\d{15}$|^\d{20}$|^\d{22}$/', $tn)) {
            return ['valid' => true, 'message' => 'Valid FedEx format'];
        }
        return ['valid' => false, 'message' => 'Invalid FedEx format (expected 12, 15, 20, or 22 digits)'];
    }
    
    // USPS validation (20-22 digits or starts with 9)
    if (stripos($carrierName, 'usps') !== false) {
        if (preg_match('/^(94|93|92|94|95|96|82|[A-Z]{2}\d{9}US|\d{20,22})/', $tn)) {
            return ['valid' => true, 'message' => 'Valid USPS format'];
        }
        return ['valid' => false, 'message' => 'Invalid USPS format'];
    }
    
    // UPS validation (18 characters starting with 1Z)
    if (stripos($carrierName, 'ups') !== false) {
        if (preg_match('/^1Z[A-Z0-9]{16}$/', $tn)) {
            return ['valid' => true, 'message' => 'Valid UPS format'];
        }
        return ['valid' => false, 'message' => 'Invalid UPS format (expected 1Z followed by 16 characters)'];
    }
    
    // DHL validation (10-11 digits)
    if (stripos($carrierName, 'dhl') !== false) {
        if (preg_match('/^\d{10,11}$/', $tn)) {
            return ['valid' => true, 'message' => 'Valid DHL format'];
        }
        return ['valid' => false, 'message' => 'Invalid DHL format (expected 10-11 digits)'];
    }
    
    // Default: assume valid if it has at least 8 characters
    if (strlen($tn) >= 8) {
        return ['valid' => true, 'message' => 'Format check passed'];
    }
    
    return ['valid' => false, 'message' => 'Tracking number too short (minimum 8 characters)'];
}

// ========================================
// STEP 1: Collect tracking numbers that need checking
// ========================================
echo "<h3>📦 STEP 1: Collecting Tracking Numbers from Orders Module</h3>";

$trackingToCheck = [];
$finalStatuses = ['Delivered', 'Cancelled', 'Refunded'];
$now = time();

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
        tracking_last_checked
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

if (!$result) {
    die("❌ Query failed: " . $mysqli->error);
}

echo "Found " . $result->num_rows . " orders with tracking numbers<br>";
echo "Filtering: Only checking non-empty tracking, skipping final statuses, respecting cache<br><br>";

$processedCount = 0;

while ($row = $result->fetch_assoc()) {
    $productID = $row['ProductID'];
    $lastChecked = $row['tracking_last_checked'] ? strtotime($row['tracking_last_checked']) : 0;
    $timeSinceCheck = $now - $lastChecked;
    
    for ($i = 1; $i <= 4; $i++) {
        $trackingField = $i == 1 ? 'trackingnumber' : "trackingnumber{$i}";
        $statusField = "tracking{$i}_status";
        
        $trackingNumber = trim($row[$trackingField] ?? '');
        $currentStatus = trim($row[$statusField] ?? '');
        
        if (empty($trackingNumber)) {
            continue;
        }
        
        if (in_array($currentStatus, $finalStatuses)) {
            continue;
        }
        
        if ($timeSinceCheck < CACHE_DURATION) {
            continue;
        }
        
        // ✅ NEW: Store carrier info with tracking number
        if (!isset($trackingToCheck[$trackingNumber])) {
            $trackingToCheck[$trackingNumber] = [
                'carrier_name' => $row['carrier'] ?? '',
                'records' => []
            ];
        }
        
        $trackingToCheck[$trackingNumber]['records'][] = [
            'product_id' => $productID,
            'order_id' => $row['rtid'],
            'item_id' => $row['itemnumber'],
            'tracking_field_index' => $i,
        ];
        
        $processedCount++;
    }
    
    if (count($trackingToCheck) >= MAX_TRACKING_TO_CHECK) {
        echo "<br>⚠️ Reached MAX_TRACKING_TO_CHECK limit (" . MAX_TRACKING_TO_CHECK . ")<br>";
        break;
    }
}

echo "<br><div style='background: #e7f3ff; padding: 10px; border-left: 4px solid #007bff;'>";
echo "<strong>📊 COLLECTION SUMMARY</strong><br>";
echo "Total tracking fields checked: {$processedCount}<br>";
echo "Unique tracking numbers to check: <strong>" . count($trackingToCheck) . "</strong><br>";
echo "</div><br>";

if (empty($trackingToCheck)) {
    echo "<div style='background: #d4edda; padding: 15px; border: 2px solid #28a745;'>";
    echo "✅ No tracking numbers need checking at this time<br>";
    echo "</div>";
    echo "<br>Finished: " . date('Y-m-d H:i:s') . "<br>";
    $mysqli->close();
    exit;
}

// ========================================
// STEP 2: Check 17track in batches
// ========================================
echo "<h3>🌐 STEP 2: Checking 17track API</h3>";

$headers = [
    '17token: ' . $API_KEY,
    'Content-Type: application/json'
];

$trackingNumbers = array_keys($trackingToCheck);
$batches = array_chunk($trackingNumbers, BATCH_SIZE);
$trackingResults = [];

echo "Processing " . count($batches) . " batch(es) of 17track API calls...<br><br>";

foreach ($batches as $batchIdx => $batch) {
    echo "<div style='background: #d1ecf1; padding: 10px; margin: 10px 0; border-left: 4px solid #17a2b8;'>";
    echo "<strong>📦 BATCH " . ($batchIdx + 1) . "/" . count($batches) . "</strong> (" . count($batch) . " tracking numbers)<br><br>";
    
    // ✅ FIXED: Register with carrier codes and validation
    $registerData = [];
    foreach ($batch as $tn) {
        $carrierName = $trackingToCheck[$tn]['carrier_name'] ?? '';
        $carrierCode = get17trackCarrier($carrierName, $carrierMapping);
        
        // Validate tracking format
        $validation = validateTrackingFormat($tn, $carrierName);
        
        if (!$validation['valid']) {
            echo "⚠️ <strong>{$tn}</strong>: {$validation['message']} - <span style='color: red;'>SKIPPING</span><br>";
            continue; // Skip invalid tracking numbers
        }
        
        $trackData = ['number' => $tn];
        
        // Add carrier code if we found one
        if ($carrierCode) {
            $trackData['carrier'] = (int)$carrierCode; // ✅ FORCE INTEGER TYPE
            echo "→ {$tn}: Using carrier '{$carrierName}' (code: {$carrierCode}) - {$validation['message']}<br>";
        } else {
            echo "→ {$tn}: Auto-detect carrier (DB carrier: '{$carrierName}') - {$validation['message']}<br>";
        }
        
        $registerData[] = $trackData;
    }
    
    if (empty($registerData)) {
        echo "<br><span style='color: orange;'>⚠️ No valid tracking numbers in this batch - SKIPPING API CALL</span><br></div>";
        continue;
    }
    
    echo "<br>📤 Registering with 17track...<br>";
    
    // Debug: Show what we're sending
    echo "<details><summary>🔍 Debug: Request payload</summary><pre>" . 
         json_encode($registerData, JSON_PRETTY_PRINT) . "</pre></details><br>";
    
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, 'https://api.17track.net/track/v2.2/register');
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($registerData));
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 30);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    
    $registerResponse = curl_exec($ch);
    $regData = json_decode($registerResponse, true);
    
    // Handle registration errors with better details
    if (isset($regData['data']['rejected'])) {
        foreach ($regData['data']['rejected'] as $rej) {
            $errCode = $rej['error']['code'] ?? 0;
            $errMsg = $rej['error']['message'] ?? 'Unknown error';
            
            if ($errCode == -18019901) {
                echo "ℹ️ {$rej['number']}: Already registered (OK)<br>";
            } else {
                echo "⚠️ {$rej['number']}: ERROR Code {$errCode} - {$errMsg}<br>";
                
                // Provide helpful hints
                if ($errCode == -18019903) {
                    echo "   💡 <strong>Fix:</strong> Invalid tracking format or carrier not detected<br>";
                    echo "   → Check if carrier '{$trackingToCheck[$rej['number']]['carrier_name']}' is mapped correctly<br>";
                    echo "   → Verify tracking number format is valid<br>";
                }
            }
        }
    }
    
    if (isset($regData['data']['accepted'])) {
        echo "✅ Registered: " . count($regData['data']['accepted']) . " tracking numbers<br>";
    }
    
    echo "⏳ Waiting 1 second...<br>";
    sleep(1);
    
    // Get tracking info
    echo "📥 Fetching tracking info...<br>";
    
    $getTrackData = [];
    foreach ($batch as $tn) {
        $getTrackData[] = ['number' => $tn];
    }
    
    curl_setopt($ch, CURLOPT_URL, 'https://api.17track.net/track/v2.2/gettrackinfo');
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($getTrackData));
    
    $trackResponse = curl_exec($ch);
    $trackHttpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    echo "HTTP Response: {$trackHttpCode}<br>";
    
    if ($trackHttpCode !== 200) {
        echo "<span style='color: red;'>❌ 17track API returned HTTP {$trackHttpCode}</span><br>";
        echo "Response: " . substr($trackResponse, 0, 200) . "...<br>";
        echo "</div>";
        continue;
    }
    
    $trackData = json_decode($trackResponse, true);
    
    if (!isset($trackData['data']['accepted'])) {
        echo "<span style='color: red;'>❌ No tracking data in response</span><br>";
        echo "</div>";
        continue;
    }
    
    $acceptedTracks = $trackData['data']['accepted'];
    echo "<br>✅ Received tracking info for " . count($acceptedTracks) . " numbers<br><br>";
    
    foreach ($acceptedTracks as $track) {
        $tn = $track['number'] ?? '';
        
        if (empty($tn)) {
            continue;
        }
        
        $trackInfo = $track['track_info'] ?? [];
        $latestEvent = $trackInfo['latest_event'] ?? [];
        $latestStatus = $trackInfo['latest_status'] ?? [];
        
        $statusCode = $latestStatus['status'] ?? 0;
        $eventTime = $latestEvent['time_iso'] ?? null;
        $description = $latestEvent['description'] ?? 'Unknown';
        $carrierName = $track['provider_name'] ?? 'Unknown';
        
        $deliveredDate = null;
        if ($eventTime) {
            try {
                $deliveredDate = (new DateTime($eventTime))->format('Y-m-d H:i:s');
            } catch (Exception $e) {
                // Ignore
            }
        }
        
        $status = 'Unknown';
        switch ($statusCode) {
            case 40:
                $status = 'Delivered';
                break;
            case 10:
            case 20:
            case 30:
                $status = 'In Transit';
                break;
            case 35:
            case 50:
                $status = 'Delivery Exception';
                break;
            case 0:
                $status = 'Not Found';
                break;
        }
        
        if ($status === 'Unknown' && $deliveredDate) {
            $status = 'Delivered';
        }
        
        $trackingResults[$tn] = [
            'status' => $status,
            'delivered_date' => $deliveredDate,
            'carrier' => $carrierName,
            'description' => $description
        ];
        
        echo "→ <strong>{$tn}</strong>: ";
        
        $statusColor = '#6c757d';
        if ($status === 'Delivered') $statusColor = '#28a745';
        elseif ($status === 'In Transit') $statusColor = '#007bff';
        elseif ($status === 'Delivery Exception') $statusColor = '#ffc107';
        elseif ($status === 'Not Found') $statusColor = '#dc3545';
        
        echo "<span style='color: {$statusColor}; font-weight: bold;'>{$status}</span>";
        
        if ($deliveredDate) {
            echo " | Delivered: {$deliveredDate}";
        }
        
        echo " | Carrier: {$carrierName}<br>";
    }
    
    echo "</div>";
    
    if ($batchIdx < count($batches) - 1) {
        echo "⏳ Waiting 2 seconds before next batch...<br>";
        sleep(2);
    }
}

echo "<br><div style='background: #d4edda; padding: 10px; border-left: 4px solid #28a745;'>";
echo "<strong>✅ 17TRACK API COMPLETE</strong><br>";
echo "Results received: <strong>" . count($trackingResults) . "</strong> tracking numbers<br>";
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
        echo "⚠️ No result for {$trackingNumber}<br>";
        $skippedCount++;
        continue;
    }
    
    $result = $trackingResults[$trackingNumber];
    $status = $result['status'];
    $deliveredDate = $result['delivered_date'];
    
    if (($status === 'Unknown' && !$deliveredDate) || $status === 'Not Found') {
        echo "→ Skipping {$trackingNumber} (status: {$status})<br>";
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
            echo "<span style='color: red;'>❌ Prepare failed for ProductID {$productID}</span><br>";
            $errorCount++;
            continue;
        }
        
        $stmt->bind_param($updateTypes, ...$updateValues);
        
        if ($stmt->execute()) {
            echo "→ <strong>ProductID {$productID}</strong> | tracking{$trackingIndex}_status = '<strong>{$status}</strong>'";
            if ($deliveredDate) {
                echo " | Date: {$deliveredDate}";
            }
            echo "<br>";
            $updatedCount++;
        } else {
            echo "<span style='color: red;'>❌ Update failed for ProductID {$productID}</span><br>";
            $errorCount++;
        }
        
        $stmt->close();
    }
}

echo "<br><div style='background: #e7f3ff; padding: 10px; border-left: 4px solid #007bff;'>";
echo "<strong>📊 DATABASE UPDATE SUMMARY</strong><br>";
echo "Records updated: <strong>{$updatedCount}</strong><br>";
echo "Skipped: <strong>{$skippedCount}</strong><br>";
echo "Errors: <strong>{$errorCount}</strong><br>";
echo "</div><br>";

// ========================================
// STEP 4: Update main delivery status
// ========================================
echo "<h3>🔄 STEP 4: Updating Main Delivery Status</h3>";

$mainStatusUpdated = 0;

$query = "
    SELECT 
        ProductID,
        delivery_status,
        datedelivered,
        tracking1_status,
        tracking2_status,
        tracking3_status,
        tracking4_status,
        tracking1_delivered_date,
        tracking2_delivered_date,
        tracking3_delivered_date,
        tracking4_delivered_date
    FROM tblproduct
    WHERE ProductModuleLoc = 'Orders'
    AND (
        tracking1_status IS NOT NULL
        OR tracking2_status IS NOT NULL
        OR tracking3_status IS NOT NULL
        OR tracking4_status IS NOT NULL
    )
";

$result = $mysqli->query($query);

while ($row = $result->fetch_assoc()) {
    $productID = $row['ProductID'];
    
    $statuses = [
        $row['tracking1_status'],
        $row['tracking2_status'],
        $row['tracking3_status'],
        $row['tracking4_status']
    ];
    
    $deliveredDates = [
        $row['tracking1_delivered_date'],
        $row['tracking2_delivered_date'],
        $row['tracking3_delivered_date'],
        $row['tracking4_delivered_date']
    ];
    
    $statuses = array_filter($statuses);
    $deliveredDates = array_filter($deliveredDates, function($date) {
        return $date && $date !== '0000-00-00 00:00:00';
    });
    
    if (empty($statuses)) {
        continue;
    }
    
    $newMainStatus = null;
    $newDeliveredDate = null;
    
    if (in_array('Delivered', $statuses)) {
        $newMainStatus = 'Delivered';
        if (!empty($deliveredDates)) {
            $newDeliveredDate = min($deliveredDates);
        }
    }
    elseif (in_array('In Transit', $statuses)) {
        $newMainStatus = 'In Transit';
    }
    elseif (in_array('Delivery Exception', $statuses)) {
        $newMainStatus = 'Delivery Exception';
    }
    else {
        $newMainStatus = reset($statuses);
    }
    
    $currentStatus = $row['delivery_status'] ?? '';
    $currentDeliveredDate = $row['datedelivered'] ?? '';
    
    if (in_array($currentStatus, $finalStatuses)) {
        continue;
    }
    
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
        if (empty($currentDeliveredDate) || $currentDeliveredDate === '0000-00-00 00:00:00' || $newDeliveredDate < $currentDeliveredDate) {
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
            echo "→ ProductID {$productID}: delivery_status = '<strong>{$newMainStatus}</strong>'";
            if ($newDeliveredDate) {
                echo " | Date: {$newDeliveredDate}";
            }
            echo "<br>";
            $mainStatusUpdated++;
        }
        
        $stmt->close();
    }
}

echo "<br><div style='background: #d4edda; padding: 10px; border-left: 4px solid #28a745;'>";
echo "<strong>✅ Main delivery status updated for {$mainStatusUpdated} records</strong><br>";
echo "</div><br>";

// ========================================
// FINAL SUMMARY
// ========================================
echo "<div style='background: #007bff; color: white; padding: 20px; border-radius: 8px; margin-top: 20px;'>";
echo "<h3 style='margin: 0 0 15px 0;'>📊 FINAL SUMMARY</h3>";
echo "<hr style='border-color: rgba(255,255,255,0.3); margin: 15px 0;'>";
echo "Unique tracking numbers checked: <strong>" . count($trackingResults) . "</strong><br>";
echo "Individual tracking statuses updated: <strong>{$updatedCount}</strong><br>";
echo "Main delivery statuses updated: <strong>{$mainStatusUpdated}</strong><br>";
echo "Skipped: <strong>{$skippedCount}</strong><br>";
echo "Errors: <strong>{$errorCount}</strong><br>";
echo "<hr style='border-color: rgba(255,255,255,0.3); margin: 15px 0;'>";
echo "Finished: <strong>" . date('Y-m-d H:i:s') . "</strong><br>";
echo "</div>";

$mysqli->close();
?>