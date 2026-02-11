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

// === CARRIER MAPPING (17track carrier codes) ===
// This helps 17track identify the carrier faster and more accurately
function detectCarrierCode($trackingNumber, $carrierHint = '') {
    $carrierHint = strtolower(trim($carrierHint));

    // Known carrier mappings for 17track
    $carrierMap = [
        'usps'      => 21051,
        'ups'       => 21071,
        'fedex'     => 21081,
        'dhl'       => 100143,
        'amazon'    => 190271,
        'ontrac'    => 21121,
        'lasership' => 21131,
        'pitney'    => 21141,
        'purolator' => 41011,
        'canada post' => 41031,
        'royal mail' => 51011,
        'yun express' => 190012,
        'yanwen'    => 190001,
        'cainiao'   => 190271,
        'sf express' => 100003,
        'ems'       => 10001,
    ];

    // Check carrier hint first
    foreach ($carrierMap as $name => $code) {
        if (strpos($carrierHint, $name) !== false) {
            return $code;
        }
    }

    // Auto-detect by tracking number pattern
    $tn = strtoupper(trim($trackingNumber));

    // USPS patterns
    if (preg_match('/^(94|93|92|94|95)\d{18,22}$/', $tn)) return 21051;
    if (preg_match('/^[A-Z]{2}\d{9}[A-Z]{2}$/', $tn)) return 21051; // EMS/international

    // UPS patterns
    if (preg_match('/^1Z[A-Z0-9]{16}$/', $tn)) return 21071;
    if (preg_match('/^(T|K)\d{10}$/', $tn)) return 21071;

    // FedEx patterns
    if (preg_match('/^\d{12,22}$/', $tn) && strlen($tn) >= 12 && strlen($tn) <= 22) {
        if (strlen($tn) == 12 || strlen($tn) == 15 || strlen($tn) == 20 || strlen($tn) == 22) {
            return 21081;
        }
    }
    if (preg_match('/^\d{34}$/', $tn)) return 21081;

    // DHL patterns
    if (preg_match('/^\d{10,11}$/', $tn)) return 100143;
    if (preg_match('/^[A-Z]{3}\d{7}$/', $tn)) return 100143;

    // Return 0 = auto-detect by 17track
    return 0;
}

// === HELPER: Make 17track API call ===
function call17trackAPI($url, $data, $apiKey) {
    $headers = [
        '17token: ' . $apiKey,
        'Content-Type: application/json'
    ];

    $ch = curl_init();
    curl_setopt_array($ch, [
        CURLOPT_URL            => $url,
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => json_encode($data),
        CURLOPT_HTTPHEADER     => $headers,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT        => 30,
        CURLOPT_SSL_VERIFYPEER => false,
    ]);

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlError = curl_error($ch);
    curl_close($ch);

    return [
        'body'      => $response,
        'http_code' => $httpCode,
        'error'     => $curlError,
        'data'      => json_decode($response, true),
    ];
}

// === HELPER: Map 17track status code to readable status ===
function mapTrackingStatus($statusCode, $subStatus = '') {
    switch ($statusCode) {
        case 0:  return 'Not Found';
        case 10: return 'In Transit';       // InfoReceived
        case 20: return 'In Transit';       // In Transit
        case 30: return 'In Transit';       // Expired (still in transit)
        case 35: return 'Delivery Exception'; // Delivery Failure
        case 40: return 'Delivered';
        case 50: return 'Delivery Exception'; // Exception
        default: return 'In Transit';        // Default to In Transit instead of Unknown
    }
}

// ========================================
// STEP 1: Collect tracking numbers
// ========================================
echo "<h3>📦 STEP 1: Collecting Tracking Numbers from Orders Module</h3>";

$trackingToCheck = [];
$finalStatuses = ['Delivered', 'Cancelled', 'Refunded'];
$now = time();

$query = "
    SELECT 
        ProductID, rtid, itemnumber,
        trackingnumber, trackingnumber2, trackingnumber3, trackingnumber4,
        carrier,
        tracking1_status, tracking2_status, tracking3_status, tracking4_status,
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
echo "Filtering: skipping final statuses, respecting cache duration<br><br>";

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

        if (empty($trackingNumber)) continue;
        if (in_array($currentStatus, $finalStatuses)) continue;
        if ($timeSinceCheck < CACHE_DURATION) continue;

        if (!isset($trackingToCheck[$trackingNumber])) {
            $trackingToCheck[$trackingNumber] = [
                'carrier' => $row['carrier'] ?? '',
                'records' => [],
            ];
        }

        $trackingToCheck[$trackingNumber]['records'][] = [
            'product_id'          => $productID,
            'order_id'            => $row['rtid'],
            'item_id'             => $row['itemnumber'],
            'tracking_field_index'=> $i,
        ];

        $processedCount++;
    }

    if (count($trackingToCheck) >= MAX_TRACKING_TO_CHECK) {
        echo "⚠️ Reached MAX_TRACKING_TO_CHECK limit (" . MAX_TRACKING_TO_CHECK . ")<br>";
        break;
    }
}

echo "<div style='background:#e7f3ff;padding:10px;border-left:4px solid #007bff;'>";
echo "<strong>📊 COLLECTION SUMMARY</strong><br>";
echo "Tracking fields scanned: {$processedCount}<br>";
echo "Unique tracking numbers to check: <strong>" . count($trackingToCheck) . "</strong><br>";
echo "</div><br>";

if (empty($trackingToCheck)) {
    echo "<div style='background:#d4edda;padding:15px;border:2px solid #28a745;'>";
    echo "✅ No tracking numbers need checking at this time.<br>";
    echo "</div>";
    echo "<br>Finished: " . date('Y-m-d H:i:s') . "<br>";
    $mysqli->close();
    exit;
}

// ========================================
// STEP 2: Register + Retrack + GetTrackInfo via 17track
// ========================================
echo "<h3>🌐 STEP 2: Checking 17track API (Register → Retrack → GetTrackInfo)</h3>";

$trackingNumbers = array_keys($trackingToCheck);
$batches = array_chunk($trackingNumbers, BATCH_SIZE);
$trackingResults = [];

echo "Processing " . count($batches) . " batch(es)...<br><br>";

foreach ($batches as $batchIdx => $batch) {
    echo "<div style='background:#d1ecf1;padding:10px;margin:10px 0;border-left:4px solid #17a2b8;'>";
    echo "<strong>📦 BATCH " . ($batchIdx + 1) . "/" . count($batches) . "</strong> (" . count($batch) . " tracking numbers)<br><br>";

    // --- Build register payload with carrier detection ---
    $registerData = [];
    foreach ($batch as $tn) {
        $carrierHint = $trackingToCheck[$tn]['carrier'] ?? '';
        $carrierCode = detectCarrierCode($tn, $carrierHint);

        $entry = ['number' => $tn];
        if ($carrierCode > 0) {
            $entry['carrier'] = $carrierCode;
        }
        $registerData[] = $entry;
    }

    // --- STEP 2a: Register tracking numbers ---
    echo "📤 <strong>Registering</strong> with 17track...<br>";

    $regResult = call17trackAPI(
        'https://api.17track.net/track/v2.2/register',
        $registerData,
        $API_KEY
    );

    $needsRetrack = []; // Track numbers that need re-tracking

    if ($regResult['http_code'] !== 200) {
        echo "<span style='color:red;'>❌ Register API HTTP {$regResult['http_code']}</span><br>";
        // Even if register fails, still try gettrackinfo below
    } else {
        $regData = $regResult['data'];

        // Count accepted
        $acceptedCount = 0;
        if (isset($regData['data']['accepted'])) {
            $acceptedCount = count($regData['data']['accepted']);
            echo "✅ Newly registered: {$acceptedCount}<br>";
        }

        // Handle rejected — collect those needing retrack
        if (isset($regData['data']['rejected'])) {
            foreach ($regData['data']['rejected'] as $rej) {
                $errCode = $rej['error']['code'] ?? 0;
                $rejNumber = $rej['number'] ?? '';

                if ($errCode == -18019901) {
                    // Already registered — OK, gettrackinfo will work
                    echo "ℹ️ {$rejNumber}: Already registered (OK)<br>";
                } elseif ($errCode == -18019903) {
                    // Registered but expired/stopped — needs retrack
                    echo "🔄 {$rejNumber}: Needs re-tracking (code -18019903)<br>";
                    $needsRetrack[] = $rejNumber;
                } elseif ($errCode == -18019902) {
                    // Invalid tracking number
                    echo "⚠️ {$rejNumber}: Invalid tracking number format<br>";
                } else {
                    echo "⚠️ {$rejNumber}: Registration error code {$errCode}<br>";
                    // Still try retrack for unknown errors
                    $needsRetrack[] = $rejNumber;
                }
            }
        }
    }

    // --- STEP 2b: Retrack numbers that need it ---
    if (!empty($needsRetrack)) {
        echo "<br>🔄 <strong>Re-tracking</strong> " . count($needsRetrack) . " numbers...<br>";

        $retrackData = [];
        foreach ($needsRetrack as $tn) {
            $carrierHint = $trackingToCheck[$tn]['carrier'] ?? '';
            $carrierCode = detectCarrierCode($tn, $carrierHint);

            $entry = ['number' => $tn];
            if ($carrierCode > 0) {
                $entry['carrier'] = $carrierCode;
            }
            $retrackData[] = $entry;
        }

        $retrackResult = call17trackAPI(
            'https://api.17track.net/track/v2.2/retrack',
            $retrackData,
            $API_KEY
        );

        if ($retrackResult['http_code'] === 200 && isset($retrackResult['data']['data'])) {
            $rtData = $retrackResult['data']['data'];

            if (isset($rtData['accepted'])) {
                echo "✅ Re-tracked: " . count($rtData['accepted']) . " numbers<br>";
            }
            if (isset($rtData['rejected'])) {
                foreach ($rtData['rejected'] as $rej) {
                    $errCode = $rej['error']['code'] ?? 0;
                    $errMsg = $rej['error']['message'] ?? 'Unknown';
                    echo "⚠️ Retrack rejected: {$rej['number']} — Code {$errCode} ({$errMsg})<br>";
                }
            }
        } else {
            echo "⚠️ Retrack API returned HTTP {$retrackResult['http_code']}<br>";
            if ($retrackResult['error']) {
                echo "cURL Error: {$retrackResult['error']}<br>";
            }
        }
    }

    // --- STEP 2c: Wait for 17track to process ---
    echo "<br>⏳ Waiting 3 seconds for 17track to process...<br><br>";
    sleep(3);

    // --- STEP 2d: Get tracking info for ALL numbers in this batch ---
    echo "📥 <strong>Fetching tracking info</strong> for all " . count($batch) . " numbers...<br>";

    $getTrackData = [];
    foreach ($batch as $tn) {
        $carrierHint = $trackingToCheck[$tn]['carrier'] ?? '';
        $carrierCode = detectCarrierCode($tn, $carrierHint);

        $entry = ['number' => $tn];
        if ($carrierCode > 0) {
            $entry['carrier'] = $carrierCode;
        }
        $getTrackData[] = $entry;
    }

    $trackResult = call17trackAPI(
        'https://api.17track.net/track/v2.2/gettrackinfo',
        $getTrackData,
        $API_KEY
    );

    echo "HTTP Response: {$trackResult['http_code']}<br>";

    if ($trackResult['http_code'] !== 200) {
        echo "<span style='color:red;'>❌ gettrackinfo failed: HTTP {$trackResult['http_code']}</span><br>";
        if ($trackResult['error']) {
            echo "cURL Error: {$trackResult['error']}<br>";
        }
        echo "Response: " . substr($trackResult['body'] ?? '', 0, 500) . "<br>";
        echo "</div>";
        continue;
    }

    $trackData = $trackResult['data'];

    // Handle accepted results
    $acceptedTracks = $trackData['data']['accepted'] ?? [];
    $rejectedTracks = $trackData['data']['rejected'] ?? [];

    echo "✅ Track info received: " . count($acceptedTracks) . " accepted, " . count($rejectedTracks) . " rejected<br><br>";

    // Log rejected gettrackinfo
    foreach ($rejectedTracks as $rej) {
        $errCode = $rej['error']['code'] ?? 0;
        $errMsg = $rej['error']['message'] ?? 'Unknown';
        echo "❌ gettrackinfo rejected: {$rej['number']} — Code {$errCode} ({$errMsg})<br>";
    }

    // Process accepted track info
    foreach ($acceptedTracks as $track) {
        $tn = $track['number'] ?? '';
        if (empty($tn)) continue;

        $trackInfo = $track['track_info'] ?? [];
        $latestEvent = $trackInfo['latest_event'] ?? [];
        $latestStatus = $trackInfo['latest_status'] ?? [];
        $tracking = $trackInfo['tracking'] ?? [];

        $statusCode = $latestStatus['status'] ?? 0;
        $subStatus = $latestStatus['sub_status'] ?? '';
        $eventTime = $latestEvent['time_iso'] ?? null;
        $description = $latestEvent['description'] ?? '';
        $carrierCode = $track['carrier'] ?? 0;
        $providerName = $track['provider_name'] ?? 'Unknown';

        // Try to get description from tracking events if latest_event is empty
        if (empty($description) && !empty($tracking)) {
            // tracking may have 'providers' array
            $providers = $tracking['providers'] ?? [];
            foreach ($providers as $provider) {
                $events = $provider['events'] ?? [];
                if (!empty($events)) {
                    // Events are usually newest first
                    $description = $events[0]['description'] ?? '';
                    if (empty($eventTime)) {
                        $eventTime = $events[0]['time_iso'] ?? null;
                    }
                    break;
                }
            }
        }

        // Parse delivered date
        $deliveredDate = null;
        if ($eventTime) {
            try {
                $deliveredDate = (new DateTime($eventTime))->format('Y-m-d H:i:s');
            } catch (Exception $e) {
                // ignore
            }
        }

        // Map status
        $status = mapTrackingStatus($statusCode, $subStatus);

        // If status is Not Found but we have events/description, treat as In Transit
        if ($status === 'Not Found' && !empty($description)) {
            $status = 'In Transit';
        }

        // If we have a delivered date and status code is 40, confirm Delivered
        if ($statusCode == 40 && $deliveredDate) {
            $status = 'Delivered';
        }

        $trackingResults[$tn] = [
            'status'         => $status,
            'delivered_date' => $deliveredDate,
            'carrier'        => $providerName,
            'carrier_code'   => $carrierCode,
            'description'    => $description,
            'status_code'    => $statusCode,
        ];

        // Color-coded output
        $colors = [
            'Delivered'          => '#28a745',
            'In Transit'         => '#007bff',
            'Delivery Exception' => '#ffc107',
            'Not Found'          => '#dc3545',
        ];
        $c = $colors[$status] ?? '#6c757d';

        echo "→ <strong>{$tn}</strong>: ";
        echo "<span style='color:{$c};font-weight:bold;'>{$status}</span>";
        echo " (code: {$statusCode})";
        if ($deliveredDate) echo " | Date: {$deliveredDate}";
        echo " | Carrier: {$providerName}";
        if (!empty($description)) echo " | Event: " . htmlspecialchars(substr($description, 0, 80));
        echo "<br>";
    }

    echo "</div>";

    // Delay between batches
    if ($batchIdx < count($batches) - 1) {
        echo "⏳ Waiting 2 seconds before next batch...<br>";
        sleep(2);
    }
}

echo "<br><div style='background:#d4edda;padding:10px;border-left:4px solid #28a745;'>";
echo "<strong>✅ 17TRACK API COMPLETE</strong><br>";
echo "Results received: <strong>" . count($trackingResults) . "</strong> tracking numbers<br>";
echo "</div><br>";

// ========================================
// STEP 3: Update database with individual tracking statuses
// ========================================
echo "<h3>💾 STEP 3: Updating Database</h3>";

$updatedCount = 0;
$errorCount = 0;
$skippedCount = 0;

foreach ($trackingToCheck as $trackingNumber => $info) {
    $records = $info['records'];

    if (!isset($trackingResults[$trackingNumber])) {
        echo "⚠️ No API result for {$trackingNumber} — updating last_checked only<br>";

        // Still update last_checked so we don't keep retrying immediately
        foreach ($records as $record) {
            $stmt = $mysqli->prepare("UPDATE tblproduct SET tracking_last_checked = NOW() WHERE ProductID = ?");
            $stmt->bind_param("i", $record['product_id']);
            $stmt->execute();
            $stmt->close();
        }

        $skippedCount++;
        continue;
    }

    $res = $trackingResults[$trackingNumber];
    $status = $res['status'];
    $deliveredDate = $res['delivered_date'];

    // Skip Not Found — but still update last_checked
    if ($status === 'Not Found') {
        echo "→ Skipping {$trackingNumber} (Not Found) — updating last_checked<br>";
        foreach ($records as $record) {
            $stmt = $mysqli->prepare("UPDATE tblproduct SET tracking_last_checked = NOW() WHERE ProductID = ?");
            $stmt->bind_param("i", $record['product_id']);
            $stmt->execute();
            $stmt->close();
        }
        $skippedCount++;
        continue;
    }

    // Update each record using this tracking number
    foreach ($records as $record) {
        $productID = $record['product_id'];
        $idx = $record['tracking_field_index'];

        $statusField = "tracking{$idx}_status";
        $dateField = "tracking{$idx}_delivered_date";

        $updateFields = [];
        $updateValues = [];
        $updateTypes = "";

        // Status
        $updateFields[] = "{$statusField} = ?";
        $updateValues[] = $status;
        $updateTypes .= "s";

        // Delivered date
        if ($deliveredDate) {
            $updateFields[] = "{$dateField} = ?";
            $updateValues[] = $deliveredDate;
            $updateTypes .= "s";
        }

        // Last checked
        $updateFields[] = "tracking_last_checked = NOW()";

        $updateSQL = "UPDATE tblproduct SET " . implode(", ", $updateFields) . " WHERE ProductID = ?";
        $updateValues[] = $productID;
        $updateTypes .= "i";

        $stmt = $mysqli->prepare($updateSQL);
        if (!$stmt) {
            echo "<span style='color:red;'>❌ Prepare failed for ProductID {$productID}: " . $mysqli->error . "</span><br>";
            $errorCount++;
            continue;
        }

        $stmt->bind_param($updateTypes, ...$updateValues);

        if ($stmt->execute()) {
            echo "→ <strong>ProductID {$productID}</strong> | {$statusField} = '<strong>{$status}</strong>'";
            if ($deliveredDate) echo " | Date: {$deliveredDate}";
            echo "<br>";
            $updatedCount++;
        } else {
            echo "<span style='color:red;'>❌ Update failed: " . $stmt->error . "</span><br>";
            $errorCount++;
        }
        $stmt->close();
    }
}

echo "<br><div style='background:#e7f3ff;padding:10px;border-left:4px solid #007bff;'>";
echo "<strong>📊 DATABASE UPDATE SUMMARY</strong><br>";
echo "Records updated: <strong>{$updatedCount}</strong><br>";
echo "Skipped: <strong>{$skippedCount}</strong><br>";
echo "Errors: <strong>{$errorCount}</strong><br>";
echo "</div><br>";

// ========================================
// STEP 4: Update main delivery_status
// ========================================
echo "<h3>🔄 STEP 4: Updating Main Delivery Status</h3>";

$mainStatusUpdated = 0;

$query = "
    SELECT 
        ProductID, delivery_status, datedelivered,
        tracking1_status, tracking2_status, tracking3_status, tracking4_status,
        tracking1_delivered_date, tracking2_delivered_date,
        tracking3_delivered_date, tracking4_delivered_date
    FROM tblproduct
    WHERE ProductModuleLoc = 'Orders'
    AND (
        tracking1_status IS NOT NULL OR tracking2_status IS NOT NULL
        OR tracking3_status IS NOT NULL OR tracking4_status IS NOT NULL
    )
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
    ], function($d) {
        return $d && $d !== '0000-00-00 00:00:00';
    });

    if (empty($statuses)) continue;

    $currentStatus = $row['delivery_status'] ?? '';
    if (in_array($currentStatus, $finalStatuses)) continue;

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

    // Build update
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
        $curDate = $row['datedelivered'] ?? '';
        if (empty($curDate) || $curDate === '0000-00-00 00:00:00' || $newDeliveredDate < $curDate) {
            $updateParts[] = "datedelivered = ?";
            $updateValues[] = $newDeliveredDate;
            $updateTypes .= "s";
            $needsUpdate = true;
        }
    }

    if ($needsUpdate) {
        $sql = "UPDATE tblproduct SET " . implode(", ", $updateParts) . " WHERE ProductID = ?";
        $updateValues[] = $productID;
        $updateTypes .= "i";

        $stmt = $mysqli->prepare($sql);
        $stmt->bind_param($updateTypes, ...$updateValues);

        if ($stmt->execute()) {
            echo "→ ProductID {$productID}: delivery_status = '<strong>{$newMainStatus}</strong>'";
            if ($newDeliveredDate) echo " | Date: {$newDeliveredDate}";
            echo "<br>";
            $mainStatusUpdated++;
        }
        $stmt->close();
    }
}

echo "<br><div style='background:#d4edda;padding:10px;border-left:4px solid #28a745;'>";
echo "<strong>✅ Main delivery status updated: {$mainStatusUpdated} records</strong><br>";
echo "</div><br>";

// ========================================
// FINAL SUMMARY
// ========================================
echo "<div style='background:#007bff;color:white;padding:20px;border-radius:8px;margin-top:20px;'>";
echo "<h3 style='margin:0 0 15px 0;'>📊 FINAL SUMMARY</h3>";
echo "<hr style='border-color:rgba(255,255,255,0.3);margin:15px 0;'>";
echo "Unique tracking numbers checked: <strong>" . count($trackingResults) . "</strong><br>";
echo "Individual tracking statuses updated: <strong>{$updatedCount}</strong><br>";
echo "Main delivery statuses updated: <strong>{$mainStatusUpdated}</strong><br>";
echo "Skipped (Not Found / No Result): <strong>{$skippedCount}</strong><br>";
echo "Errors: <strong>{$errorCount}</strong><br>";
echo "<hr style='border-color:rgba(255,255,255,0.3);margin:15px 0;'>";
echo "Finished: <strong>" . date('Y-m-d H:i:s') . "</strong><br>";
echo "</div>";

$mysqli->close();
?>