<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
date_default_timezone_set('America/Los_Angeles');

// Force C locale (safe)
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

// Carrier-list cache (local file)
define('CARRIER_LIST_CACHE_FILE', __DIR__ . '/17track_carriers.json');
define('CARRIER_LIST_CACHE_TTL', 86400); // 24 hours

$API_KEY = '5EC4C3FCD4929687DC76822C8D154C20';

// ========================================
// 17TRACK Carrier List Helpers
// ========================================
function httpGet($url, $timeout = 20) {
    $ch = curl_init();
    curl_setopt_array($ch, [
        CURLOPT_URL => $url,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => $timeout,
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_SSL_VERIFYHOST => false,
        CURLOPT_FOLLOWLOCATION => true,
    ]);
    $res = curl_exec($ch);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    if ($code !== 200 || $res === false) return null;
    return $res;
}

/**
 * Fetch & cache 17TRACK carrier list JSON.
 * IMPORTANT: We rely on 17TRACK's published carrier list, not hardcoded fake codes.
 */
function get17TrackCarrierList() {
    // If cached and fresh, use it.
    if (file_exists(CARRIER_LIST_CACHE_FILE) && (time() - filemtime(CARRIER_LIST_CACHE_FILE)) < CARRIER_LIST_CACHE_TTL) {
        $json = file_get_contents(CARRIER_LIST_CACHE_FILE);
        $data = json_decode($json, true);
        if (is_array($data)) return $data;
    }

    // 17TRACK public carrier list (commonly used endpoint)
    $url = 'https://res.17track.net/asset/carrier/info/apicarrier.all.json';
    $json = httpGet($url, 25);
    if ($json) {
        file_put_contents(CARRIER_LIST_CACHE_FILE, $json);
        $data = json_decode($json, true);
        if (is_array($data)) return $data;
    }

    // Fallback to whatever is on disk (even if old)
    if (file_exists(CARRIER_LIST_CACHE_FILE)) {
        $json = file_get_contents(CARRIER_LIST_CACHE_FILE);
        $data = json_decode($json, true);
        if (is_array($data)) return $data;
    }

    return null;
}

/**
 * Find best carrier "key" (code) for a carrier name.
 * - Attempts exact match, then contains match.
 * - Optionally constrain by country ISO.
 */
function find17TrackCarrierCodeByName($name, $countryIso = null) {
    $list = get17TrackCarrierList();
    if (!is_array($list)) return null;

    $needle = strtolower(trim($name));
    if ($needle === '') return null;

    $countryIso = $countryIso ? strtoupper(trim($countryIso)) : null;

    // 1) Exact match
    foreach ($list as $c) {
        $cname = strtolower(trim($c['_name'] ?? ''));
        $iso   = strtoupper(trim($c['_country_iso'] ?? ''));
        if ($cname === $needle) {
            if ($countryIso && $iso && $iso !== $countryIso) continue;
            $key = isset($c['key']) ? intval($c['key']) : 0;
            return $key > 0 ? $key : null;
        }
    }

    // 2) Contains match
    foreach ($list as $c) {
        $cname = strtolower(trim($c['_name'] ?? ''));
        $iso   = strtoupper(trim($c['_country_iso'] ?? ''));
        if ($cname !== '' && strpos($cname, $needle) !== false) {
            if ($countryIso && $iso && $iso !== $countryIso) continue;
            $key = isset($c['key']) ? intval($c['key']) : 0;
            return $key > 0 ? $key : null;
        }
    }

    return null;
}

/**
 * Normalize DB carrier strings into a name we can search.
 */
function normalizeCarrierName($dbCarrier) {
    $s = strtolower(trim((string)$dbCarrier));
    if ($s === '') return '';

    // Common aliases
    if (strpos($s, 'fedex') !== false || strpos($s, 'fdx') !== false) return 'FedEx';
    if (strpos($s, 'usps') !== false || strpos($s, 'postal') !== false || strpos($s, 'u.s. postal') !== false) return 'USPS';
    if (strpos($s, 'ups') !== false) return 'UPS';
    if (strpos($s, 'dhl') !== false) return 'DHL';

    // If your DB contains exact carrier names like "Yun Express" etc, keep them.
    // Title-case fallback:
    return ucwords($s);
}

/**
 * Tracking format validation
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

/**
 * OPTIONAL: treat 9-digit as invalid (often internal refs).
 * If you actually have real 9-digit carrier tracking, remove this.
 */
function isLikelyBadTracking($trackingNumber) {
    $tn = trim($trackingNumber);
    if (preg_match('/^\d{9}$/', $tn)) return true;
    return false;
}

// ========================================
// STEP 1: Collect tracking numbers
// ========================================
echo "<h3>📦 STEP 1: Collecting Tracking Numbers</h3>";

// Carrier field stats
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
    'overdue' => 0,
    'likely_bad' => 0
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
        $currentStatus  = trim($row[$statusField] ?? '');

        if (empty($trackingNumber)) {
            $skipReasons['empty']++;
            continue;
        }

        if (!isValidTrackingNumber($trackingNumber)) {
            $skipReasons['invalid_format']++;
            continue;
        }

        if (isLikelyBadTracking($trackingNumber)) {
            $skipReasons['likely_bad']++;
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
echo "Likely bad (9-digit): {$skipReasons['likely_bad']}<br>";
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

// Warm up carrier list (so first request isn’t slow)
$carrierList = get17TrackCarrierList();
if (!is_array($carrierList)) {
    echo "<div style='background:#f8d7da;padding:10px;border-left:4px solid #dc3545;'>";
    echo "⚠️ Could not load 17TRACK carrier list. Carrier hints will be omitted and auto-detect used.";
    echo "</div><br>";
}

foreach ($batches as $batchIdx => $batch) {
    echo "<div style='background: #d1ecf1; padding: 10px; margin: 10px 0; border-left: 4px solid #17a2b8;'>";
    echo "<strong>📦 BATCH " . ($batchIdx + 1) . "/" . count($batches) . "</strong><br><br>";

    // Build tracking request
    $trackingData = [];
    echo "<table style='width: 100%; border-collapse: collapse;'>";
    echo "<tr style='background: #f5f5f5;'><th>Tracking Number</th><th>Length</th><th>DB Carrier</th><th>Resolved 17TRACK Carrier</th></tr>";

    foreach ($batch as $tn) {
        $len = strlen($tn);
        $dbCarrierRaw = $trackingToCheck[$tn]['carrier'] ?? '';
        $normalizedCarrier = normalizeCarrierName($dbCarrierRaw);

        // Only attach carrier hint if we can resolve a VALID 17TRACK code
        $carrierHint = null;

        if ($normalizedCarrier !== '' && is_array($carrierList)) {
            // If your shipments are mostly US-based, US filter helps avoid weird matches
            $countryIso = 'US';
            $carrierHint = find17TrackCarrierCodeByName($normalizedCarrier, $countryIso);

            // If no hit under US, try without country constraint
            if (!$carrierHint) {
                $carrierHint = find17TrackCarrierCodeByName($normalizedCarrier, null);
            }
        }

        $trackItem = ['number' => $tn];
        if ($carrierHint) {
            $trackItem['carrier'] = (int)$carrierHint; // REAL 17TRACK carrier key
        }
        $trackingData[] = $trackItem;

        echo "<tr>";
        echo "<td><strong>" . htmlspecialchars($tn) . "</strong></td>";
        echo "<td>{$len}</td>";
        echo "<td>" . htmlspecialchars($dbCarrierRaw) . "</td>";
        echo "<td>" . ($carrierHint ? ("Code " . htmlspecialchars((string)$carrierHint) . " (" . htmlspecialchars($normalizedCarrier) . ")") : "Auto-detect") . "</td>";
        echo "</tr>";
    }
    echo "</table><br>";

    // Build JSON safely (no locale issues)
    $requestPayload = json_encode($trackingData, JSON_UNESCAPED_SLASHES);

    echo "<details open><summary>📋 Request Payload DEBUG</summary>";
    echo "<pre style='background: #f5f5f5; padding: 10px; font-family: monospace;'>";
    echo "Raw string length: " . strlen($requestPayload) . "\n";
    echo "First 500 chars:\n";
    echo htmlspecialchars(substr($requestPayload, 0, 500)) . "\n\n";
    echo "Full payload:\n";
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
            $errMsg  = $rej['error']['message'] ?? '';
            // -18019901 = already registered (OK)
            if ($errCode != -18019901) {
                echo "  → " . htmlspecialchars($rejNum) . ": Error " . htmlspecialchars((string)$errCode) . " - " . htmlspecialchars($errMsg) . "<br>";
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
            echo "→ " . htmlspecialchars($rejNum) . ": " . htmlspecialchars((string)$errCode) . " - " . htmlspecialchars($errMsg) . "<br>";
        }
        echo "</div>";
    }

    echo "<br>✅ Received info for " . count($acceptedTracks) . " tracking numbers<br><br>";

    foreach ($acceptedTracks as $track) {
        $tn = $track['number'] ?? '';
        if (empty($tn)) continue;

        $trackInfo    = $track['track_info'] ?? [];
        $latestStatus = $trackInfo['latest_status'] ?? [];
        $latestEvent  = $trackInfo['latest_event'] ?? [];

        $statusCode = $latestStatus['status'] ?? 0;

        // Map status codes
        $statusText = 'Unknown';
        switch ($statusCode) {
            case 40: $statusText = 'Delivered'; break;
            case 10:
            case 20:
            case 30: $statusText = 'In Transit'; break;
            case 35:
            case 50: $statusText = 'Delivery Exception'; break;
            case 0:  $statusText = 'Not Found'; break;
        }

        $deliveredDate = null;
        if ($latestEvent && isset($latestEvent['time_iso'])) {
            try {
                $deliveredDate = (new DateTime($latestEvent['time_iso']))->format('Y-m-d H:i:s');
            } catch (Exception $e) { /* ignore */ }
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

        echo "→ <strong>" . htmlspecialchars($tn) . "</strong>: ";
        echo "<span style='color: {$statusColor}; font-weight: bold;'>" . htmlspecialchars($statusText) . "</span>";
        if ($deliveredDate) echo " | " . htmlspecialchars($deliveredDate);
        echo " | " . htmlspecialchars($carrierName) . "<br>";
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
$errorCount   = 0;
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
        $dateField   = "tracking{$trackingIndex}_delivered_date";

        $updateFields = [];
        $updateValues = [];
        $updateTypes  = "";

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
            echo "→ ProductID {$productID} | tracking{$trackingIndex} = '" . htmlspecialchars($status) . "'";
            if ($deliveredDate) echo " | " . htmlspecialchars($deliveredDate);
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
        $updateSQL = "UPDATE tblproduct SET " . implode(", ", $updateParts) . " WHERE ProductID = ?";
        $updateValues[] = $productID;
        $updateTypes .= "i";

        $stmt = $mysqli->prepare($updateSQL);
        $stmt->bind_param($updateTypes, ...$updateValues);

        if ($stmt->execute()) {
            echo "→ ProductID {$productID}: '" . htmlspecialchars($newMainStatus) . "'<br>";
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
echo "Likely bad (9-digit): {$skipReasons['likely_bad']}<br>";
echo "Final status: {$skipReasons['final_status']}<br>";
echo "Cached: {$skipReasons['cache']}<br>";
echo "Overdue: {$skipReasons['overdue']}<br>";
echo "<hr style='border-color: rgba(255,255,255,0.3);'>";
echo "Finished: " . date('Y-m-d H:i:s') . "<br>";
echo "</div>";

$mysqli->close();
?>
