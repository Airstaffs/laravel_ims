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
define('CACHE_DURATION', 21600);

$API_KEY = '5EC4C3FCD4929687DC76822C8D154C20';

// === CARRIER DETECTION ===
// Returns carrier code as integer, or 0 for auto-detect
function detectCarrierCode($trackingNumber, $carrierHint = '') {
    $hint = strtolower(trim($carrierHint));
    $tn = strtoupper(trim($trackingNumber));

    // --- Match by carrier hint text first ---
    if ($hint) {
        if (strpos($hint, 'usps') !== false || strpos($hint, 'united states postal') !== false)
            return (int)21051;
        if (strpos($hint, 'ups') !== false)
            return (int)21071;
        if (strpos($hint, 'fedex') !== false || strpos($hint, 'fed ex') !== false)
            return (int)21081;
        if (strpos($hint, 'dhl') !== false)
            return (int)100143;
        if (strpos($hint, 'amazon') !== false)
            return (int)190271;
        if (strpos($hint, 'ontrac') !== false)
            return (int)21121;
        if (strpos($hint, 'lasership') !== false)
            return (int)21131;
        if (strpos($hint, 'canada post') !== false)
            return (int)41031;
        if (strpos($hint, 'royal mail') !== false)
            return (int)51011;
        if (strpos($hint, 'yun express') !== false)
            return (int)190012;
        if (strpos($hint, 'yanwen') !== false)
            return (int)190001;
        if (strpos($hint, 'sf express') !== false)
            return (int)100003;
    }

    // --- Auto-detect by pattern ---

    // UPS: always starts with 1Z
    if (preg_match('/^1Z[A-Z0-9]{16}$/', $tn)) return (int)21071;

    // USPS: 20-22 digit starting with 92, 93, 94, 95
    if (preg_match('/^(92|93|94|95)\d{18,20}$/', $tn)) return (int)21051;

    // USPS: International format XX123456789XX
    if (preg_match('/^[A-Z]{2}\d{9}[A-Z]{2}$/', $tn)) return (int)21051;

    // FedEx: exactly 12 or 15 digits (most common FedEx formats)
    if (preg_match('/^\d{12}$/', $tn)) {
        // 12 digits could be FedEx Express — but DON'T force it, let 17track auto-detect
        // because 12-digit numbers can also be other carriers
        return 0;
    }
    if (preg_match('/^\d{15}$/', $tn)) return (int)21081; // FedEx Ground
    if (preg_match('/^\d{20}$/', $tn)) return (int)21081; // FedEx SmartPost 20-digit
    if (preg_match('/^\d{22}$/', $tn)) return (int)21081; // FedEx 22-digit
    if (preg_match('/^\d{34}$/', $tn)) return (int)21081; // FedEx 34-digit

    // DHL: 10-digit numeric
    if (preg_match('/^\d{10}$/', $tn)) return (int)100143;

    // Let 17track auto-detect everything else
    return 0;
}

// === HELPER: 17track API call ===
function call17trackAPI($url, $data, $apiKey) {
    $headers = [
        '17token: ' . $apiKey,
        'Content-Type: application/json'
    ];

    // Encode JSON — make sure integers stay as integers (no string conversion)
    $jsonPayload = json_encode($data, JSON_UNESCAPED_UNICODE);

    $ch = curl_init();
    curl_setopt_array($ch, [
        CURLOPT_URL            => $url,
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => $jsonPayload,
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
        'json_sent' => $jsonPayload, // for debugging
    ];
}

// === HELPER: Build tracking entry for API ===
function buildTrackEntry($trackingNumber, $carrierHint = '') {
    $entry = ['number' => (string)$trackingNumber];
    $code = detectCarrierCode($trackingNumber, $carrierHint);
    if ($code > 0) {
        $entry['carrier'] = (int)$code; // MUST be integer
    }
    return $entry;
}

// === HELPER: Map status code ===
function mapTrackingStatus($statusCode) {
    switch ((int)$statusCode) {
        case 0:  return 'Not Found';
        case 10: return 'In Transit';       // Info Received
        case 20: return 'In Transit';       // In Transit
        case 30: return 'In Transit';       // Expired
        case 35: return 'Delivery Exception';
        case 40: return 'Delivered';
        case 50: return 'Delivery Exception';
        default: return 'In Transit';
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
if (!$result) die("❌ Query failed: " . $mysqli->error);

echo "Found " . $result->num_rows . " orders with tracking numbers<br><br>";

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
            'product_id'           => $productID,
            'order_id'             => $row['rtid'],
            'item_id'              => $row['itemnumber'],
            'tracking_field_index' => $i,
        ];

        $processedCount++;
    }

    if (count($trackingToCheck) >= MAX_TRACKING_TO_CHECK) {
        echo "⚠️ Reached limit (" . MAX_TRACKING_TO_CHECK . ")<br>";
        break;
    }
}

echo "<div style='background:#e7f3ff;padding:10px;border-left:4px solid #007bff;'>";
echo "<strong>📊 COLLECTION SUMMARY</strong><br>";
echo "Tracking fields scanned: {$processedCount}<br>";
echo "Unique tracking numbers: <strong>" . count($trackingToCheck) . "</strong><br>";
echo "</div><br>";

if (empty($trackingToCheck)) {
    echo "<div style='background:#d4edda;padding:15px;border:2px solid #28a745;'>";
    echo "✅ No tracking numbers need checking.<br></div>";
    echo "<br>Finished: " . date('Y-m-d H:i:s');
    $mysqli->close();
    exit;
}

// ========================================
// STEP 2: 17track API — Register → Retrack → GetTrackInfo
// ========================================
echo "<h3>🌐 STEP 2: Checking 17track API</h3>";

$trackingNumbers = array_keys($trackingToCheck);
$batches = array_chunk($trackingNumbers, BATCH_SIZE);
$trackingResults = [];

echo "Processing " . count($batches) . " batch(es)...<br><br>";

foreach ($batches as $batchIdx => $batch) {
    echo "<div style='background:#d1ecf1;padding:10px;margin:10px 0;border-left:4px solid #17a2b8;'>";
    echo "<strong>📦 BATCH " . ($batchIdx + 1) . "/" . count($batches) . "</strong> (" . count($batch) . " numbers)<br><br>";

    // Build payload — WITHOUT carrier first (let 17track auto-detect)
    // This avoids the -18010011 "invalid carrier" error
    $registerData = [];
    foreach ($batch as $tn) {
        $registerData[] = ['number' => (string)$tn];
    }

    // --- DEBUG: Show what we're sending ---
    $jsonPreview = json_encode($registerData, JSON_UNESCAPED_UNICODE);
    echo "📋 Register payload: <code>" . htmlspecialchars(substr($jsonPreview, 0, 300)) . "</code><br><br>";

    // ========== STEP 2a: REGISTER ==========
    echo "📤 <strong>Registering</strong>...<br>";

    $regResult = call17trackAPI(
        'https://api.17track.net/track/v2.2/register',
        $registerData,
        $API_KEY
    );

    $needsRetrack = [];
    $registeredOK = [];
    $failedNumbers = [];

    if ($regResult['http_code'] !== 200) {
        echo "<span style='color:red;'>❌ Register HTTP {$regResult['http_code']}</span><br>";
        echo "Response: " . htmlspecialchars(substr($regResult['body'], 0, 500)) . "<br>";
        // Still try gettrackinfo for all
        $registeredOK = $batch;
    } else {
        $regData = $regResult['data'];

        if (isset($regData['data']['accepted'])) {
            foreach ($regData['data']['accepted'] as $acc) {
                $registeredOK[] = $acc['number'];
            }
            echo "✅ Newly registered: " . count($regData['data']['accepted']) . "<br>";
        }

        if (isset($regData['data']['rejected'])) {
            foreach ($regData['data']['rejected'] as $rej) {
                $errCode = (int)($rej['error']['code'] ?? 0);
                $errMsg = $rej['error']['message'] ?? '';
                $rejNum = $rej['number'] ?? '';

                switch ($errCode) {
                    case -18019901:
                        // Already registered — this is fine
                        echo "ℹ️ {$rejNum}: Already registered ✓<br>";
                        $registeredOK[] = $rejNum;
                        break;

                    case -18019903:
                        // Expired/stopped — needs retrack
                        echo "🔄 {$rejNum}: Needs re-tracking<br>";
                        $needsRetrack[] = $rejNum;
                        break;

                    case -18019902:
                        // Not registered — shouldn't happen on register, but try again
                        echo "⚠️ {$rejNum}: Not registered ({$errMsg})<br>";
                        $needsRetrack[] = $rejNum;
                        break;

                    case -18010011:
                        // Invalid carrier value — re-register WITHOUT carrier
                        echo "⚠️ {$rejNum}: Invalid carrier — will retry without carrier<br>";
                        $failedNumbers[] = $rejNum;
                        break;

                    default:
                        echo "⚠️ {$rejNum}: Error {$errCode} ({$errMsg})<br>";
                        $failedNumbers[] = $rejNum;
                        break;
                }
            }
        }
    }

    // ========== STEP 2a-RETRY: Re-register failed numbers WITHOUT carrier ==========
    if (!empty($failedNumbers)) {
        echo "<br>🔁 <strong>Retrying registration</strong> for " . count($failedNumbers) . " numbers (without carrier)...<br>";

        $retryData = [];
        foreach ($failedNumbers as $tn) {
            $retryData[] = ['number' => (string)$tn];
        }

        $retryResult = call17trackAPI(
            'https://api.17track.net/track/v2.2/register',
            $retryData,
            $API_KEY
        );

        if ($retryResult['http_code'] === 200 && isset($retryResult['data']['data'])) {
            $rd = $retryResult['data']['data'];

            if (isset($rd['accepted'])) {
                foreach ($rd['accepted'] as $acc) {
                    $registeredOK[] = $acc['number'];
                }
                echo "✅ Retry registered: " . count($rd['accepted']) . "<br>";
            }
            if (isset($rd['rejected'])) {
                foreach ($rd['rejected'] as $rej) {
                    $ec = (int)($rej['error']['code'] ?? 0);
                    $em = $rej['error']['message'] ?? '';
                    $rn = $rej['number'] ?? '';

                    if ($ec == -18019901) {
                        echo "ℹ️ {$rn}: Already registered ✓<br>";
                        $registeredOK[] = $rn;
                    } elseif ($ec == -18019903) {
                        echo "🔄 {$rn}: Needs retrack<br>";
                        $needsRetrack[] = $rn;
                    } else {
                        echo "❌ {$rn}: Still failing — Code {$ec} ({$em})<br>";
                        // Still add to registered list to TRY gettrackinfo anyway
                        $registeredOK[] = $rn;
                    }
                }
            }
        } else {
            echo "⚠️ Retry HTTP {$retryResult['http_code']}<br>";
            // Add them anyway, we'll try gettrackinfo
            foreach ($failedNumbers as $fn) {
                $registeredOK[] = $fn;
            }
        }
    }

    // ========== STEP 2b: RETRACK ==========
    if (!empty($needsRetrack)) {
        echo "<br>🔄 <strong>Re-tracking</strong> " . count($needsRetrack) . " numbers...<br>";

        $retrackData = [];
        foreach ($needsRetrack as $tn) {
            $retrackData[] = ['number' => (string)$tn];
        }

        $retrackResult = call17trackAPI(
            'https://api.17track.net/track/v2.2/retrack',
            $retrackData,
            $API_KEY
        );

        if ($retrackResult['http_code'] === 200 && isset($retrackResult['data']['data'])) {
            $rtData = $retrackResult['data']['data'];

            if (isset($rtData['accepted'])) {
                echo "✅ Re-tracked: " . count($rtData['accepted']) . "<br>";
                foreach ($rtData['accepted'] as $acc) {
                    $registeredOK[] = $acc['number'];
                }
            }
            if (isset($rtData['rejected'])) {
                foreach ($rtData['rejected'] as $rej) {
                    $ec = (int)($rej['error']['code'] ?? 0);
                    $em = $rej['error']['message'] ?? '';
                    $rn = $rej['number'] ?? '';
                    echo "⚠️ Retrack rejected: {$rn} — Code {$ec} ({$em})<br>";

                    // If retrack says "not registered", try fresh register
                    if ($ec == -18019902) {
                        echo "  → Attempting fresh register for {$rn}...<br>";
                        $freshResult = call17trackAPI(
                            'https://api.17track.net/track/v2.2/register',
                            [['number' => (string)$rn]],
                            $API_KEY
                        );
                        if ($freshResult['http_code'] === 200) {
                            $fd = $freshResult['data']['data'] ?? [];
                            if (!empty($fd['accepted'])) {
                                echo "  ✅ Fresh registered: {$rn}<br>";
                                $registeredOK[] = $rn;
                            } elseif (!empty($fd['rejected'])) {
                                $fe = $fd['rejected'][0]['error'] ?? [];
                                echo "  ❌ Fresh register failed: Code " . ($fe['code'] ?? '?') . "<br>";
                                // Still add to try gettrackinfo
                                $registeredOK[] = $rn;
                            }
                        }
                    } else {
                        $registeredOK[] = $rn;
                    }
                }
            }
        } else {
            echo "⚠️ Retrack HTTP {$retrackResult['http_code']}<br>";
            foreach ($needsRetrack as $tn) {
                $registeredOK[] = $tn;
            }
        }
    }

    // Remove duplicates
    $allNumbersToFetch = array_unique(array_merge($registeredOK, $needsRetrack));

    // ========== STEP 2c: WAIT ==========
    echo "<br>⏳ Waiting 5 seconds for 17track to process...<br><br>";
    sleep(5);

    // ========== STEP 2d: GET TRACK INFO ==========
    echo "📥 <strong>Fetching tracking info</strong> for " . count($allNumbersToFetch) . " numbers...<br>";

    // Send WITHOUT carrier to avoid any carrier-related errors
    $getTrackData = [];
    foreach ($allNumbersToFetch as $tn) {
        $getTrackData[] = ['number' => (string)$tn];
    }

    echo "📋 GetTrackInfo payload: <code>" . htmlspecialchars(substr(json_encode($getTrackData), 0, 300)) . "</code><br>";

    $trackResult = call17trackAPI(
        'https://api.17track.net/track/v2.2/gettrackinfo',
        $getTrackData,
        $API_KEY
    );

    echo "HTTP Response: {$trackResult['http_code']}<br>";

    if ($trackResult['http_code'] !== 200) {
        echo "<span style='color:red;'>❌ gettrackinfo failed: HTTP {$trackResult['http_code']}</span><br>";
        echo "Response: " . htmlspecialchars(substr($trackResult['body'] ?? '', 0, 500)) . "<br>";
        echo "</div>";
        continue;
    }

    $trackData = $trackResult['data'];
    $acceptedTracks = $trackData['data']['accepted'] ?? [];
    $rejectedTracks = $trackData['data']['rejected'] ?? [];

    echo "✅ Accepted: " . count($acceptedTracks) . " | Rejected: " . count($rejectedTracks) . "<br><br>";

    // Log rejected
    foreach ($rejectedTracks as $rej) {
        $ec = (int)($rej['error']['code'] ?? 0);
        $em = $rej['error']['message'] ?? '';
        $rn = $rej['number'] ?? '';
        echo "❌ Rejected: {$rn} — Code {$ec} ({$em})<br>";
    }

    // Process accepted
    foreach ($acceptedTracks as $track) {
        $tn = $track['number'] ?? '';
        if (empty($tn)) continue;

        $trackInfo = $track['track_info'] ?? [];
        $latestEvent = $trackInfo['latest_event'] ?? [];
        $latestStatus = $trackInfo['latest_status'] ?? [];

        $statusCode = (int)($latestStatus['status'] ?? 0);
        $eventTime = $latestEvent['time_iso'] ?? null;
        $description = $latestEvent['description'] ?? '';
        $providerName = $track['provider_name'] ?? 'Unknown';

        // Try to get description from tracking providers if empty
        if (empty($description)) {
            $providers = $trackInfo['tracking']['providers'] ?? [];
            foreach ($providers as $provider) {
                $events = $provider['events'] ?? [];
                if (!empty($events)) {
                    $description = $events[0]['description'] ?? '';
                    if (empty($eventTime)) {
                        $eventTime = $events[0]['time_iso'] ?? null;
                    }
                    break;
                }
            }
        }

        // Parse date
        $deliveredDate = null;
        if ($eventTime) {
            try {
                $deliveredDate = (new DateTime($eventTime))->format('Y-m-d H:i:s');
            } catch (Exception $e) {}
        }

        // Map status
        $status = mapTrackingStatus($statusCode);

        // Not Found but has events → In Transit
        if ($status === 'Not Found' && !empty($description)) {
            $status = 'In Transit';
        }

        $trackingResults[$tn] = [
            'status'         => $status,
            'delivered_date' => $deliveredDate,
            'carrier'        => $providerName,
            'description'    => $description,
            'status_code'    => $statusCode,
        ];

        // Output
        $colors = [
            'Delivered' => '#28a745', 'In Transit' => '#007bff',
            'Delivery Exception' => '#ffc107', 'Not Found' => '#dc3545',
        ];
        $c = $colors[$status] ?? '#6c757d';

        echo "→ <strong>{$tn}</strong>: ";
        echo "<span style='color:{$c};font-weight:bold;'>{$status}</span>";
        echo " (code:{$statusCode})";
        if ($deliveredDate) echo " | Date: {$deliveredDate}";
        echo " | Carrier: {$providerName}";
        if ($description) echo " | " . htmlspecialchars(substr($description, 0, 80));
        echo "<br>";
    }

    echo "</div>";

    if ($batchIdx < count($batches) - 1) {
        echo "⏳ Waiting 2 seconds...<br>";
        sleep(2);
    }
}

echo "<br><div style='background:#d4edda;padding:10px;border-left:4px solid #28a745;'>";
echo "<strong>✅ 17TRACK API COMPLETE</strong> — Results: <strong>" . count($trackingResults) . "</strong><br>";
echo "</div><br>";

// ========================================
// STEP 3: Update database
// ========================================
echo "<h3>💾 STEP 3: Updating Database</h3>";

$updatedCount = 0;
$errorCount = 0;
$skippedCount = 0;

foreach ($trackingToCheck as $trackingNumber => $info) {
    $records = $info['records'];

    if (!isset($trackingResults[$trackingNumber])) {
        echo "⚠️ No result for {$trackingNumber} — updating last_checked<br>";
        foreach ($records as $rec) {
            $stmt = $mysqli->prepare("UPDATE tblproduct SET tracking_last_checked = NOW() WHERE ProductID = ?");
            $stmt->bind_param("i", $rec['product_id']);
            $stmt->execute();
            $stmt->close();
        }
        $skippedCount++;
        continue;
    }

    $res = $trackingResults[$trackingNumber];
    $status = $res['status'];
    $deliveredDate = $res['delivered_date'];

    if ($status === 'Not Found') {
        echo "→ Skipping {$trackingNumber} (Not Found) — updating last_checked<br>";
        foreach ($records as $rec) {
            $stmt = $mysqli->prepare("UPDATE tblproduct SET tracking_last_checked = NOW() WHERE ProductID = ?");
            $stmt->bind_param("i", $rec['product_id']);
            $stmt->execute();
            $stmt->close();
        }
        $skippedCount++;
        continue;
    }

    foreach ($records as $rec) {
        $productID = $rec['product_id'];
        $idx = $rec['tracking_field_index'];

        $statusField = "tracking{$idx}_status";
        $dateField = "tracking{$idx}_delivered_date";

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

        $sql = "UPDATE tblproduct SET " . implode(", ", $updateFields) . " WHERE ProductID = ?";
        $updateValues[] = $productID;
        $updateTypes .= "i";

        $stmt = $mysqli->prepare($sql);
        if (!$stmt) {
            echo "<span style='color:red;'>❌ Prepare failed: " . $mysqli->error . "</span><br>";
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
            echo "<span style='color:red;'>❌ " . $stmt->error . "</span><br>";
            $errorCount++;
        }
        $stmt->close();
    }
}

echo "<br><div style='background:#e7f3ff;padding:10px;border-left:4px solid #007bff;'>";
echo "<strong>📊 DB SUMMARY</strong> — Updated: <strong>{$updatedCount}</strong> | Skipped: <strong>{$skippedCount}</strong> | Errors: <strong>{$errorCount}</strong><br>";
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
    AND (tracking1_status IS NOT NULL OR tracking2_status IS NOT NULL
        OR tracking3_status IS NOT NULL OR tracking4_status IS NOT NULL)
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
            echo "→ ProductID {$productID}: '<strong>{$newMainStatus}</strong>'";
            if ($newDeliveredDate) echo " | {$newDeliveredDate}";
            echo "<br>";
            $mainStatusUpdated++;
        }
        $stmt->close();
    }
}

echo "<br><div style='background:#d4edda;padding:10px;border-left:4px solid #28a745;'>";
echo "<strong>✅ Main status updated: {$mainStatusUpdated} records</strong><br>";
echo "</div><br>";

// ========================================
// FINAL SUMMARY
// ========================================
echo "<div style='background:#007bff;color:white;padding:20px;border-radius:8px;margin-top:20px;'>";
echo "<h3 style='margin:0 0 15px 0;'>📊 FINAL SUMMARY</h3>";
echo "<hr style='border-color:rgba(255,255,255,0.3);margin:15px 0;'>";
echo "Tracking numbers checked: <strong>" . count($trackingResults) . "</strong><br>";
echo "Individual statuses updated: <strong>{$updatedCount}</strong><br>";
echo "Main statuses updated: <strong>{$mainStatusUpdated}</strong><br>";
echo "Skipped: <strong>{$skippedCount}</strong><br>";
echo "Errors: <strong>{$errorCount}</strong><br>";
echo "<hr style='border-color:rgba(255,255,255,0.3);margin:15px 0;'>";
echo "Finished: <strong>" . date('Y-m-d H:i:s') . "</strong><br>";
echo "</div>";

$mysqli->close();
?>