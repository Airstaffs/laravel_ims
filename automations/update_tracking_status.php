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
define('CACHE_DURATION', 21600);      // 6 hours
define('FORCE_RECHECK', isset($_GET['force']) ? true : false);

// API Keys
$TRACK17_API_KEY = '5EC4C3FCD4929687DC76822C8D154C20';
$SHIP24_API_KEY  = 'apik_0enxLOPgm7vJBt4rAt83XHrvFhwUds';

// ========================================
// HELPER FUNCTIONS
// ========================================

/**
 * Generic cURL helper
 */
function curlPost($url, $payload, $headers, $timeout = 30) {
    $ch = curl_init();
    curl_setopt_array($ch, [
        CURLOPT_URL            => $url,
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => is_string($payload) ? $payload : json_encode($payload),
        CURLOPT_HTTPHEADER     => $headers,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT        => $timeout,
        CURLOPT_SSL_VERIFYPEER => false,
    ]);
    $resp = curl_exec($ch);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $err  = curl_error($ch);
    curl_close($ch);
    return ['body' => $resp, 'http' => $code, 'error' => $err, 'data' => json_decode($resp, true)];
}

/**
 * Map 17track status code → readable status
 */
function map17trackStatus($code) {
    switch ((int)$code) {
        case 40: return 'Delivered';
        case 10: case 20: case 30: return 'In Transit';
        case 35: case 50: return 'Delivery Exception';
        case 0:  return 'Not Found';
        default: return 'Unknown';
    }
}

/**
 * Map Ship24 status → our status
 * Ship24 statuses: delivered, in_transit, out_for_delivery, 
 * pickup, exception, expired, pending, info_received, etc.
 */
function mapShip24Status($status) {
    $s = strtolower(trim($status));
    if ($s === 'delivered') return 'Delivered';
    if (in_array($s, ['in_transit', 'out_for_delivery', 'info_received', 'pickup', 'pending'])) return 'In Transit';
    if (in_array($s, ['exception', 'failed_attempt', 'expired'])) return 'Delivery Exception';
    if ($s === 'unknown' || empty($s)) return 'Not Found';
    return 'In Transit'; // Default
}

/**
 * Try 17track API for a batch of tracking numbers
 * Returns: ['results' => [...], 'failed' => [...]]
 */
function check17track($batch, $apiKey) {
    $results = [];
    $failed = [];

    $headers = ['17token: ' . $apiKey, 'Content-Type: application/json'];

    // Step 1: Register
    $registerData = [];
    foreach ($batch as $tn) {
        $registerData[] = ['number' => (string)$tn];
    }

    echo "  📤 17track: Registering " . count($batch) . " numbers...<br>";

    $regResp = curlPost('https://api.17track.net/track/v2.2/register', $registerData, $headers);

    $registeredOK = [];

    if ($regResp['http'] === 200 && isset($regResp['data']['data'])) {
        $rd = $regResp['data']['data'];

        // Accepted = newly registered
        if (!empty($rd['accepted'])) {
            foreach ($rd['accepted'] as $a) $registeredOK[] = $a['number'];
            echo "  ✅ Registered: " . count($rd['accepted']) . "<br>";
        }

        // Handle rejected
        if (!empty($rd['rejected'])) {
            foreach ($rd['rejected'] as $rej) {
                $errCode = (int)($rej['error']['code'] ?? 0);
                $rejNum = $rej['number'] ?? '';

                if ($errCode == -18019901) {
                    // Already registered — good, can fetch
                    $registeredOK[] = $rejNum;
                } else {
                    // Any other error — mark as failed for fallback
                    echo "  ⚠️ 17track register failed: {$rejNum} (code {$errCode})<br>";
                    $failed[] = $rejNum;
                }
            }
        }
    } else {
        echo "  ❌ 17track register HTTP {$regResp['http']}<br>";
        // All failed
        $failed = $batch;
        return ['results' => $results, 'failed' => $failed];
    }

    if (empty($registeredOK)) {
        echo "  ⚠️ No numbers registered successfully<br>";
        return ['results' => $results, 'failed' => $failed];
    }

    // Step 2: Wait
    sleep(2);

    // Step 3: Get tracking info
    echo "  📥 17track: Fetching info for " . count($registeredOK) . " numbers...<br>";

    $getTrackData = [];
    foreach ($registeredOK as $tn) {
        $getTrackData[] = ['number' => (string)$tn];
    }

    $trackResp = curlPost('https://api.17track.net/track/v2.2/gettrackinfo', $getTrackData, $headers);

    if ($trackResp['http'] !== 200) {
        echo "  ❌ 17track gettrackinfo HTTP {$trackResp['http']}<br>";
        $failed = array_merge($failed, $registeredOK);
        return ['results' => $results, 'failed' => $failed];
    }

    $accepted = $trackResp['data']['data']['accepted'] ?? [];
    $rejected = $trackResp['data']['data']['rejected'] ?? [];

    echo "  ✅ 17track returned: " . count($accepted) . " accepted, " . count($rejected) . " rejected<br>";

    // Add rejected to failed list
    foreach ($rejected as $rej) {
        $rn = $rej['number'] ?? '';
        if ($rn) $failed[] = $rn;
    }

    // Process accepted
    foreach ($accepted as $track) {
        $tn = $track['number'] ?? '';
        if (empty($tn)) continue;

        $trackInfo = $track['track_info'] ?? [];
        $latestEvent = $trackInfo['latest_event'] ?? [];
        $latestStatus = $trackInfo['latest_status'] ?? [];

        $statusCode = $latestStatus['status'] ?? 0;
        $eventTime = $latestEvent['time_iso'] ?? null;
        $description = $latestEvent['description'] ?? 'Unknown';
        $carrierName = $track['provider_name'] ?? 'Unknown';

        $deliveredDate = null;
        if ($eventTime) {
            try { $deliveredDate = (new DateTime($eventTime))->format('Y-m-d H:i:s'); }
            catch (Exception $e) {}
        }

        $status = map17trackStatus($statusCode);

        // If Unknown but has delivered date, mark as Delivered
        if ($status === 'Unknown' && $deliveredDate) {
            $status = 'Delivered';
        }

        $results[$tn] = [
            'status'         => $status,
            'delivered_date' => $deliveredDate,
            'carrier'        => $carrierName,
            'description'    => $description,
            'source'         => '17track',
        ];
    }

    return ['results' => $results, 'failed' => array_unique($failed)];
}

/**
 * Ship24 fallback — single tracking number lookup
 * Ship24 uses a simple POST to get tracking results instantly
 */
function checkShip24Single($trackingNumber, $apiKey) {
    if (empty($apiKey) || $apiKey === 'YOUR_SHIP24_API_KEY_HERE') {
        echo "<span style='color:orange;'>[Ship24 API key not set]</span> ";
        return null;
    }

    $headers = [
        'Authorization: Bearer ' . $apiKey,
        'Content-Type: application/json; charset=utf-8',
    ];

    // Use the TRACKING SEARCH endpoint — instant lookup, no pre-registration
    $payload = json_encode([
        'trackingNumber' => (string)$trackingNumber,
    ]);

    $resp = curlPost(
        'https://api.ship24.com/public/v1/tracking/search',
        $payload,
        $headers,
        30
    );

    // Debug output
    echo "[HTTP:{$resp['http']}] ";
    if ($resp['error']) {
        echo "<span style='color:red;'>[cURL: {$resp['error']}]</span> ";
        return null;
    }

    if ($resp['http'] === 401) {
        echo "<span style='color:red;'>[Auth failed — check API key]</span> ";
        return null;
    }

    if ($resp['http'] === 402) {
        echo "<span style='color:red;'>[Payment required — quota exceeded]</span> ";
        return null;
    }

    if ($resp['http'] !== 200) {
        $errMsg = $resp['data']['errors'][0]['message'] ?? ($resp['data']['message'] ?? substr($resp['body'], 0, 200));
        echo "<span style='color:red;'>[Error: {$errMsg}]</span> ";
        return null;
    }

    $data = $resp['data']['data'] ?? $resp['data'] ?? null;

    if (!$data) {
        echo "<span style='color:red;'>[Empty response data]</span> ";
        return null;
    }

    // Ship24 response structure: data.trackings[] and data.shipments[]
    $trackings = $data['trackings'] ?? [];
    $shipments = $data['shipments'] ?? [];

    if (empty($trackings) && empty($shipments)) {
        echo "<span style='color:gray;'>[No tracking data found]</span> ";
        return ['status' => 'Not Found', 'delivered_date' => null, 'carrier' => 'Unknown', 'description' => '', 'source' => 'Ship24'];
    }

    // Get events from trackings
    $events = [];
    $carrier = 'Unknown';

    if (!empty($trackings)) {
        foreach ($trackings as $t) {
            $tEvents = $t['events'] ?? [];
            foreach ($tEvents as $evt) {
                $events[] = $evt;
            }
            if (empty($carrier) || $carrier === 'Unknown') {
                $carrier = $t['tracker']['courierCode'] ?? ($t['courierCode'] ?? 'Unknown');
            }
        }
    }

    // Get status from shipments
    $status = 'Not Found';
    $deliveredDate = null;

    if (!empty($shipments)) {
        $shipment = $shipments[0];
        $statusCode = $shipment['statusCode'] ?? '';
        $statusMilestone = $shipment['statusMilestone'] ?? '';
        $status = mapShip24Status($statusMilestone ?: $statusCode);

        if (isset($shipment['lastEvent']['courierCode'])) {
            $carrier = $shipment['lastEvent']['courierCode'];
        }
    }

    // If no shipment status, try to determine from events
    if ($status === 'Not Found' && !empty($events)) {
        $status = 'In Transit'; // Has events = at least in transit
    }

    // Get description and delivery date from events
    $description = '';
    if (!empty($events)) {
        // Sort by datetime descending (newest first)
        usort($events, function($a, $b) {
            return strcmp($b['datetime'] ?? '', $a['datetime'] ?? '');
        });

        $latestEvent = $events[0];
        $description = $latestEvent['status'] ?? ($latestEvent['statusMilestone'] ?? '');

        if (isset($latestEvent['courierCode']) && $latestEvent['courierCode']) {
            $carrier = $latestEvent['courierCode'];
        }

        // Find delivery date
        if ($status === 'Delivered') {
            foreach ($events as $evt) {
                $evtMilestone = strtolower($evt['statusMilestone'] ?? '');
                $evtStatus = strtolower($evt['status'] ?? '');
                if ($evtMilestone === 'delivered' || strpos($evtStatus, 'deliver') !== false) {
                    try {
                        $deliveredDate = (new DateTime($evt['datetime']))->format('Y-m-d H:i:s');
                    } catch (Exception $e) {}
                    break;
                }
            }
            // Fallback: use latest event date
            if (!$deliveredDate && isset($latestEvent['datetime'])) {
                try {
                    $deliveredDate = (new DateTime($latestEvent['datetime']))->format('Y-m-d H:i:s');
                } catch (Exception $e) {}
            }
        }
    }

    return [
        'status'         => $status,
        'delivered_date' => $deliveredDate,
        'carrier'        => $carrier,
        'description'    => $description,
        'source'         => 'Ship24',
    ];
}

/**
 * Ship24 batch — check multiple tracking numbers
 */
function checkShip24Batch($trackingNumbers, $apiKey) {
    $results = [];
    $stillFailed = [];

    foreach ($trackingNumbers as $tn) {
        echo "  🔍 Ship24: Checking {$tn}... ";

        $result = checkShip24Single($tn, $apiKey);

        if ($result && $result['status'] !== 'Not Found') {
            $results[$tn] = $result;

            $colors = [
                'Delivered' => '#28a745', 'In Transit' => '#007bff',
                'Delivery Exception' => '#ffc107',
            ];
            $c = $colors[$result['status']] ?? '#6c757d';

            echo "<span style='color:{$c};font-weight:bold;'>{$result['status']}</span>";
            if ($result['delivered_date']) echo " | {$result['delivered_date']}";
            echo " | {$result['carrier']}";
        } elseif ($result === null) {
            echo "<span style='color:orange;'>Ship24 not configured or API error</span>";
            $stillFailed[] = $tn;
        } else {
            echo "<span style='color:red;'>Not Found</span>";
            $stillFailed[] = $tn;
        }

        echo "<br>";

        // Rate limit
        usleep(500000); // 0.5 second between calls
    }

    return ['results' => $results, 'failed' => $stillFailed];
}


// ========================================
// STEP 1: Collect tracking numbers that need checking
// ========================================
echo "<h3>📦 STEP 1: Collecting Tracking Numbers from Orders Module</h3>";

if (FORCE_RECHECK) {
    echo "<div style='background:#fff3cd;padding:8px;border-left:4px solid #ffc107;margin-bottom:10px;'>";
    echo "⚡ <strong>FORCE RECHECK MODE</strong> — Ignoring cache<br></div>";
}

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
echo "Filtering: skipping final statuses, respecting cache<br><br>";

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
        if (!FORCE_RECHECK && $timeSinceCheck < CACHE_DURATION) continue;

        if (!isset($trackingToCheck[$trackingNumber])) {
            $trackingToCheck[$trackingNumber] = [];
        }

        $trackingToCheck[$trackingNumber][] = [
            'product_id'           => $productID,
            'order_id'             => $row['rtid'],
            'item_id'              => $row['itemnumber'],
            'tracking_field_index' => $i,
            'carrier'              => $row['carrier'] ?? '',
        ];

        $processedCount++;
    }

    if (count($trackingToCheck) >= MAX_TRACKING_TO_CHECK) {
        echo "<br>⚠️ Reached MAX_TRACKING_TO_CHECK limit (" . MAX_TRACKING_TO_CHECK . ")<br>";
        break;
    }
}

echo "<br><div style='background:#e7f3ff;padding:10px;border-left:4px solid #007bff;'>";
echo "<strong>📊 COLLECTION SUMMARY</strong><br>";
echo "Total tracking fields checked: {$processedCount}<br>";
echo "Unique tracking numbers to check: <strong>" . count($trackingToCheck) . "</strong><br>";
echo "</div><br>";

if (empty($trackingToCheck)) {
    echo "<div style='background:#d4edda;padding:15px;border:2px solid #28a745;'>";
    echo "✅ No tracking numbers need checking at this time<br>";
    echo "</div>";
    echo "<br>Finished: " . date('Y-m-d H:i:s') . "<br>";
    $mysqli->close();
    exit;
}

// ========================================
// STEP 2: Check tracking via 17track (primary) + Ship24 (fallback)
// ========================================
echo "<h3>🌐 STEP 2: Checking Tracking APIs</h3>";

$trackingNumbers = array_keys($trackingToCheck);
$batches = array_chunk($trackingNumbers, BATCH_SIZE);
$trackingResults = [];

echo "Processing " . count($batches) . " batch(es)...<br><br>";

foreach ($batches as $batchIdx => $batch) {
    echo "<div style='background:#d1ecf1;padding:10px;margin:10px 0;border-left:4px solid #17a2b8;'>";
    echo "<strong>📦 BATCH " . ($batchIdx + 1) . "/" . count($batches) . "</strong> (" . count($batch) . " tracking numbers)<br><br>";

    // === PRIMARY: Try 17track ===
    echo "<strong>🔵 PRIMARY: 17track API</strong><br>";
    $track17Result = check17track($batch, $TRACK17_API_KEY);

    // Merge successful results
    foreach ($track17Result['results'] as $tn => $data) {
        $trackingResults[$tn] = $data;
    }

    $failedNumbers = $track17Result['failed'];

    // Show 17track results
    foreach ($track17Result['results'] as $tn => $data) {
        $colors = [
            'Delivered' => '#28a745', 'In Transit' => '#007bff',
            'Delivery Exception' => '#ffc107', 'Not Found' => '#dc3545',
        ];
        $c = $colors[$data['status']] ?? '#6c757d';

        echo "  → <strong>{$tn}</strong>: ";
        echo "<span style='color:{$c};font-weight:bold;'>{$data['status']}</span>";
        if ($data['delivered_date']) echo " | {$data['delivered_date']}";
        echo " | {$data['carrier']} (via 17track)<br>";
    }

    // === FALLBACK: Ship24 for failed numbers ===
    if (!empty($failedNumbers)) {
        echo "<br><strong>🟠 FALLBACK: Ship24 API</strong> (" . count($failedNumbers) . " numbers failed 17track)<br>";

        $ship24Result = checkShip24Batch($failedNumbers, $SHIP24_API_KEY);

        // Merge Ship24 results
        foreach ($ship24Result['results'] as $tn => $data) {
            $trackingResults[$tn] = $data;
        }

        if (!empty($ship24Result['failed'])) {
            echo "<br>  ❌ Still failed after both APIs: " . implode(', ', $ship24Result['failed']) . "<br>";
        }
    }

    // Batch summary
    $batchResolved = 0;
    foreach ($batch as $tn) {
        if (isset($trackingResults[$tn])) $batchResolved++;
    }
    echo "<br>📊 Batch: {$batchResolved}/" . count($batch) . " resolved<br>";

    echo "</div>";

    if ($batchIdx < count($batches) - 1) {
        echo "⏳ Waiting 2 seconds...<br>";
        sleep(2);
    }
}

echo "<br><div style='background:#d4edda;padding:10px;border-left:4px solid #28a745;'>";
echo "<strong>✅ API TRACKING COMPLETE</strong><br>";
echo "Total results: <strong>" . count($trackingResults) . "</strong><br>";

// Show source breakdown
$sources = [];
foreach ($trackingResults as $r) {
    $src = $r['source'] ?? 'unknown';
    $sources[$src] = ($sources[$src] ?? 0) + 1;
}
foreach ($sources as $src => $cnt) {
    echo "  Via {$src}: {$cnt}<br>";
}
echo "</div><br>";

// ========================================
// STEP 3: Update database with individual tracking statuses
// ========================================
echo "<h3>💾 STEP 3: Updating Database</h3>";

$updatedCount = 0;
$errorCount = 0;
$skippedCount = 0;

foreach ($trackingToCheck as $trackingNumber => $records) {
    if (!isset($trackingResults[$trackingNumber])) {
        echo "⚠️ No result for {$trackingNumber} — will retry next run<br>";
        $skippedCount++;
        continue;
    }

    $result = $trackingResults[$trackingNumber];
    $status = $result['status'];
    $deliveredDate = $result['delivered_date'];
    $source = $result['source'] ?? '';

    // Skip if Unknown with no date, or Not Found
    if (($status === 'Unknown' && !$deliveredDate) || $status === 'Not Found') {
        echo "→ Skipping {$trackingNumber} ({$status})<br>";
        $skippedCount++;
        continue;
    }

    foreach ($records as $record) {
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
            echo "<span style='color:red;'>❌ Prepare failed for ProductID {$productID}: " . $mysqli->error . "</span><br>";
            $errorCount++;
            continue;
        }

        $stmt->bind_param($updateTypes, ...$updateValues);

        if ($stmt->execute()) {
            echo "→ <strong>ProductID {$productID}</strong> | {$statusField} = '<strong>{$status}</strong>'";
            if ($deliveredDate) echo " | Date: {$deliveredDate}";
            echo " | via {$source}<br>";
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
        $row['tracking1_status'], $row['tracking2_status'],
        $row['tracking3_status'], $row['tracking4_status']
    ]);

    $deliveredDates = array_filter([
        $row['tracking1_delivered_date'], $row['tracking2_delivered_date'],
        $row['tracking3_delivered_date'], $row['tracking4_delivered_date']
    ], function($d) { return $d && $d !== '0000-00-00 00:00:00'; });

    if (empty($statuses)) continue;

    $currentStatus = $row['delivery_status'] ?? '';
    if (in_array($currentStatus, $finalStatuses)) continue;

    $newMainStatus = null;
    $newDeliveredDate = null;

    if (in_array('Delivered', $statuses)) {
        $newMainStatus = 'Delivered';
        if (!empty($deliveredDates)) $newDeliveredDate = min($deliveredDates);
    } elseif (in_array('In Transit', $statuses)) {
        $newMainStatus = 'In Transit';
    } elseif (in_array('Delivery Exception', $statuses)) {
        $newMainStatus = 'Delivery Exception';
    } else {
        $newMainStatus = reset($statuses);
    }

    $currentDeliveredDate = $row['datedelivered'] ?? '';

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
        if (empty($currentDeliveredDate) || $currentDeliveredDate === '0000-00-00 00:00:00' || $newDeliveredDate < $currentDeliveredDate) {
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
            echo "→ ProductID {$productID}: delivery_status = '<strong>{$newMainStatus}</strong>'";
            if ($newDeliveredDate) echo " | Date: {$newDeliveredDate}";
            echo "<br>";
            $mainStatusUpdated++;
        }

        $stmt->close();
    }
}

echo "<br><div style='background:#d4edda;padding:10px;border-left:4px solid #28a745;'>";
echo "<strong>✅ Main delivery status updated for {$mainStatusUpdated} records</strong><br>";
echo "</div><br>";

// ========================================
// FINAL SUMMARY
// ========================================
echo "<div style='background:#007bff;color:white;padding:20px;border-radius:8px;margin-top:20px;'>";
echo "<h3 style='margin:0 0 15px 0;'>📊 FINAL SUMMARY</h3>";
echo "<hr style='border-color:rgba(255,255,255,0.3);margin:15px 0;'>";
echo "Unique tracking numbers checked: <strong>" . count($trackingResults) . "</strong><br>";
foreach ($sources as $src => $cnt) {
    echo "  → Via {$src}: <strong>{$cnt}</strong><br>";
}
echo "Individual tracking statuses updated: <strong>{$updatedCount}</strong><br>";
echo "Main delivery statuses updated: <strong>{$mainStatusUpdated}</strong><br>";
echo "Skipped: <strong>{$skippedCount}</strong><br>";
echo "Errors: <strong>{$errorCount}</strong><br>";
echo "<hr style='border-color:rgba(255,255,255,0.3);margin:15px 0;'>";
echo "Finished: <strong>" . date('Y-m-d H:i:s') . "</strong><br>";
echo "</div>";

$mysqli->close();
?>