<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
date_default_timezone_set('America/Los_Angeles');

echo "<h2>🚚 UNIFIED TRACKING STATUS UPDATE CRON JOB</h2>";
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
define('MAX_TRACKING_TO_CHECK', 200); // Process 200 tracking numbers per run
define('BATCH_SIZE', 40);             // 17track allows 40 per batch
define('CACHE_DURATION', 21600);      // Cache for 6 hours (21600 seconds)
define('OVERDUE_THRESHOLD_DAYS', 14); // Skip tracking if 14+ days past estimated delivery

$API_KEY_17TRACK = '5EC4C3FCD4929687DC76822C8D154C20';

// ========================================
// HELPER FUNCTIONS
// ========================================

// Detect carrier from tracking number format
function detectCarrier($trackingNumber) {
    // USPS: 20-22 digits or 13 chars starting with EA/EC/CP/RA/etc
    if (preg_match('/^(94|92|93|95)\d{20}$/', $trackingNumber) || 
        preg_match('/^(EA|EC|CP|RA|LK|LN)\d{9}US$/', $trackingNumber)) {
        return 100; // USPS
    }
    
    // UPS: 1Z followed by 16 chars
    if (preg_match('/^1Z[A-Z0-9]{16}$/', $trackingNumber)) {
        return 101; // UPS
    }
    
    // FedEx: 12-14 digits
    if (preg_match('/^\d{12,14}$/', $trackingNumber)) {
        return 102; // FedEx
    }
    
    // DHL: 10 digits
    if (preg_match('/^\d{10}$/', $trackingNumber)) {
        return 103; // DHL
    }
    
    return null; // Auto-detect
}

// Check if carrier is Central Transport or other freight carriers
function isFreightCarrier($carrierName) {
    $freightCarriers = [
        'central transport',
        'central transportation',
        'r+l carriers',
        'r+l',
        'old dominion',
        'old dominion freight',
        'xpo logistics',
        'xpo',
        'estes',
        'estes express',
        'yrc freight',
        'yrc',
        'fedex freight',
        'ups freight',
        'saia',
        'averitt',
        'aaa cooper',
        'dayton freight'
    ];
    
    return in_array(strtolower(trim($carrierName)), $freightCarriers);
}

// Check if it's specifically Central Transport
function isCentralTransport($carrierName) {
    $ct = strtolower(trim($carrierName));
    return ($ct === 'central transport' || $ct === 'central transportation');
}

// Track Central Transport shipment via WEB SCRAPING
function trackCentralTransport($proNumber) {
    echo "🌐 Web Scraping Central Transport PRO: <strong>{$proNumber}</strong><br>";
    
    // Central Transport tracking page URL
    $url = "https://www.centraltransport.com/tracking/?pro=" . urlencode($proNumber);
    
    $ch = curl_init();
    curl_setopt_array($ch, [
        CURLOPT_URL => $url,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_TIMEOUT => 20,
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_SSL_VERIFYHOST => false,
        CURLOPT_USERAGENT => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36',
        CURLOPT_HTTPHEADER => [
            'Accept: text/html,application/xhtml+xml,application/xml;q=0.9,image/webp,*/*;q=0.8',
            'Accept-Language: en-US,en;q=0.9',
            'Accept-Encoding: gzip, deflate, br',
            'Connection: keep-alive',
            'Upgrade-Insecure-Requests: 1',
            'Cache-Control: max-age=0'
        ],
        CURLOPT_ENCODING => '' // Handle gzip/deflate automatically
    ]);
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlError = curl_error($ch);
    curl_close($ch);
    
    // Log the response for debugging
    echo "→ HTTP Code: {$httpCode}<br>";
    
    if ($httpCode !== 200 || empty($response)) {
        echo "⚠️ <span style='color: #dc3545;'>Failed to retrieve tracking page</span>";
        if ($curlError) {
            echo " | Error: {$curlError}";
        }
        echo "<br>";
        
        return [
            'status' => 'Unknown',
            'delivered_date' => null,
            'carrier' => 'Central Transport',
            'description' => 'Unable to retrieve tracking information'
        ];
    }
    
    // Initialize result variables
    $status = 'Unknown';
    $deliveredDate = null;
    $statusText = '';
    $latestUpdate = '';
    
    // === PATTERN MATCHING - Try multiple patterns to find status ===
    
    // Pattern 1: Look for status in table cells or divs with common class names
    if (preg_match('/<(?:td|div|span)[^>]*class="[^"]*(?:status|state|condition)[^"]*"[^>]*>\s*([^<]+)\s*<\/(?:td|div|span)>/i', $response, $matches)) {
        $statusText = trim(strip_tags($matches[1]));
        echo "→ Found status text: '{$statusText}'<br>";
    }
    
    // Pattern 2: Look for "Status:" label followed by value
    if (preg_match('/Status[:\s]*<[^>]*>\s*([^<]+)\s*</i', $response, $matches)) {
        $statusText = trim(strip_tags($matches[1]));
        echo "→ Found status after label: '{$statusText}'<br>";
    }
    
    // Pattern 3: Look for delivery/shipment status in the HTML
    if (preg_match('/(?:Delivery|Shipment)\s+Status[:\s]*([^<>]+?)(?:<|$)/i', $response, $matches)) {
        $statusText = trim(strip_tags($matches[1]));
        echo "→ Found shipment status: '{$statusText}'<br>";
    }
    
    // Pattern 4: Look for latest event/update
    if (preg_match('/<(?:td|div|span)[^>]*class="[^"]*(?:event|update|activity)[^"]*"[^>]*>\s*([^<]+)\s*<\/(?:td|div|span)>/i', $response, $matches)) {
        $latestUpdate = trim(strip_tags($matches[1]));
        echo "→ Found latest update: '{$latestUpdate}'<br>";
    }
    
    // Combine status text from all patterns
    $fullText = strtolower($statusText . ' ' . $latestUpdate . ' ' . $response);
    
    // === DETERMINE STATUS FROM TEXT ===
    
    // Check for DELIVERED status (highest priority)
    if (preg_match('/\b(delivered|delivery complete|completed|received by|signed for|proof of delivery|pod)\b/i', $fullText)) {
        $status = 'Delivered';
        echo "→ ✅ <strong style='color: #28a745;'>Status detected: DELIVERED</strong><br>";
        
        // Try to extract delivery date - Multiple date formats
        $datePatterns = [
            // MM/DD/YYYY HH:MM AM/PM
            '/delivered[^0-9]*([0-9]{1,2}[\/\-][0-9]{1,2}[\/\-][0-9]{2,4}(?:\s+[0-9]{1,2}:[0-9]{2}(?:\s*(?:AM|PM))?)?)/i',
            // YYYY-MM-DD HH:MM:SS
            '/delivered[^0-9]*([0-9]{4}-[0-9]{2}-[0-9]{2}(?:\s+[0-9]{2}:[0-9]{2}:[0-9]{2})?)/i',
            // Any date near "delivered" keyword
            '/delivered[^0-9]{0,20}([0-9]{1,2}[\/\-][0-9]{1,2}[\/\-][0-9]{2,4})/i',
            // Date in table/div after delivered
            '/<td[^>]*>[^<]*delivered[^<]*<\/td>\s*<td[^>]*>\s*([0-9]{1,2}[\/\-][0-9]{1,2}[\/\-][0-9]{2,4})/i'
        ];
        
        foreach ($datePatterns as $pattern) {
            if (preg_match($pattern, $response, $dateMatch)) {
                try {
                    $parsedDate = new DateTime($dateMatch[1]);
                    $deliveredDate = $parsedDate->format('Y-m-d H:i:s');
                    echo "→ 📅 Delivery date found: <strong>{$deliveredDate}</strong><br>";
                    break;
                } catch (Exception $e) {
                    // Try next pattern
                }
            }
        }
    }
    // Check for IN TRANSIT status
    elseif (preg_match('/\b(in transit|out for delivery|picked up|en route|on vehicle|dispatched|departed|in yard|at terminal)\b/i', $fullText)) {
        $status = 'In Transit';
        echo "→ 🚛 <strong style='color: #007bff;'>Status detected: IN TRANSIT</strong><br>";
    }
    // Check for DELIVERY EXCEPTION
    elseif (preg_match('/\b(exception|delayed|hold|issue|problem|unable to deliver|delivery attempt|rescheduled)\b/i', $fullText)) {
        $status = 'Delivery Exception';
        echo "→ ⚠️ <strong style='color: #ffc107;'>Status detected: DELIVERY EXCEPTION</strong><br>";
    }
    // Check for NOT FOUND
    elseif (preg_match('/\b(not found|invalid|no results|no record|does not exist|shipment not available)\b/i', $fullText)) {
        $status = 'Not Found';
        echo "→ ❌ <strong style='color: #dc3545;'>Status detected: NOT FOUND</strong><br>";
    }
    // Check for PENDING/RECEIVED
    elseif (preg_match('/\b(pending|received|booked|scheduled|waiting|processing)\b/i', $fullText)) {
        $status = 'Pending Pickup';
        echo "→ ⏳ <strong style='color: #6c757d;'>Status detected: PENDING PICKUP</strong><br>";
    }
    else {
        echo "→ ❓ <strong style='color: #6c757d;'>Status: UNKNOWN</strong> (no matching patterns)<br>";
    }
    
    // Final output summary
    echo "→ <strong>Final Status:</strong> ";
    $statusColor = '#6c757d';
    if ($status === 'Delivered') $statusColor = '#28a745';
    elseif ($status === 'In Transit') $statusColor = '#007bff';
    elseif ($status === 'Delivery Exception') $statusColor = '#ffc107';
    elseif ($status === 'Not Found') $statusColor = '#dc3545';
    
    echo "<span style='color: {$statusColor}; font-weight: bold;'>{$status}</span>";
    
    if ($deliveredDate) {
        echo " | Delivered: <strong>{$deliveredDate}</strong>";
    }
    if ($statusText) {
        echo " | Description: {$statusText}";
    }
    echo "<br><br>";
    
    return [
        'status' => $status,
        'delivered_date' => $deliveredDate,
        'carrier' => 'Central Transport',
        'description' => $statusText ?: 'Central Transport tracking'
    ];
}

// ========================================
// STEP 1: Collect tracking numbers that need checking
// ========================================
echo "<h3>📦 STEP 1: Collecting Tracking Numbers from Orders Module</h3>";

$trackingToCheck17Track = []; // For 17track API
$trackingToCheckFreight = []; // For freight carriers (Central Transport, etc.)
$finalStatuses = ['Delivered', 'Cancelled', 'Refunded'];
$now = time();

// Counters for skip reasons
$skipReasons = [
    'empty' => 0,
    'final_status' => 0,
    'cache' => 0,
    'overdue' => 0
];

// Query to get all orders with tracking numbers
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

if (!$result) {
    die("❌ Query failed: " . $mysqli->error);
}

echo "Found " . $result->num_rows . " orders with tracking numbers<br>";
echo "Separating into 17track carriers and freight carriers...<br><br>";

$processedCount = 0;

while ($row = $result->fetch_assoc()) {
    $productID = $row['ProductID'];
    $carrier = trim($row['carrier'] ?? '');
    $lastChecked = $row['tracking_last_checked'] ? strtotime($row['tracking_last_checked']) : 0;
    $timeSinceCheck = $now - $lastChecked;
    
    // Parse estimated delivery date
    $estimatedDelivery = null;
    $isOverdue = false;
    
    if (!empty($row['estimated_deliverydate']) && $row['estimated_deliverydate'] !== '0000-00-00' && $row['estimated_deliverydate'] !== '0000-00-00 00:00:00') {
        try {
            $estimatedDelivery = strtotime($row['estimated_deliverydate']);
            if ($estimatedDelivery && $estimatedDelivery > 0) {
                $daysPastEstimate = ($now - $estimatedDelivery) / 86400;
                if ($daysPastEstimate > OVERDUE_THRESHOLD_DAYS) {
                    $isOverdue = true;
                }
            }
        } catch (Exception $e) {
            // Ignore date parse errors
        }
    }
    
    // Determine if this is a freight carrier
    $isFreight = isFreightCarrier($carrier);
    
    // Check each tracking field (1-4)
    for ($i = 1; $i <= 4; $i++) {
        $trackingField = $i == 1 ? 'trackingnumber' : "trackingnumber{$i}";
        $statusField = "tracking{$i}_status";
        
        $trackingNumber = trim($row[$trackingField] ?? '');
        $currentStatus = trim($row[$statusField] ?? '');
        
        // === SKIP CONDITIONS (Save API Quota) ===
        
        // 1. SKIP if tracking number is NULL or empty
        if (empty($trackingNumber)) {
            $skipReasons['empty']++;
            continue;
        }
        
        // 2. SKIP if already in final status
        if (in_array($currentStatus, $finalStatuses)) {
            $skipReasons['final_status']++;
            continue;
        }
        
        // 3. SKIP if checked recently (within cache duration)
        if ($timeSinceCheck < CACHE_DURATION) {
            $skipReasons['cache']++;
            continue;
        }
        
        // 4. SKIP if overdue (more than 14 days past estimated delivery)
        if ($isOverdue) {
            $skipReasons['overdue']++;
            continue;
        }
        
        // Add to appropriate list
        $trackingInfo = [
            'product_id' => $productID,
            'order_id' => $row['rtid'],
            'item_id' => $row['itemnumber'],
            'tracking_field_index' => $i,
            'carrier' => $carrier
        ];
        
        if ($isFreight) {
            // Add to freight tracking list
            if (!isset($trackingToCheckFreight[$trackingNumber])) {
                $trackingToCheckFreight[$trackingNumber] = [];
            }
            $trackingToCheckFreight[$trackingNumber][] = $trackingInfo;
        } else {
            // Add to 17track list
            if (!isset($trackingToCheck17Track[$trackingNumber])) {
                $trackingToCheck17Track[$trackingNumber] = [];
            }
            $trackingToCheck17Track[$trackingNumber][] = $trackingInfo;
        }
        
        $processedCount++;
    }
    
    // Limit total tracking numbers to check
    if ((count($trackingToCheck17Track) + count($trackingToCheckFreight)) >= MAX_TRACKING_TO_CHECK) {
        echo "<br>⚠️ Reached MAX_TRACKING_TO_CHECK limit (" . MAX_TRACKING_TO_CHECK . ")<br>";
        break;
    }
}

echo "<br><div style='background: #e7f3ff; padding: 10px; border-left: 4px solid #007bff;'>";
echo "<strong>📊 COLLECTION SUMMARY</strong><br>";
echo "Total tracking fields processed: {$processedCount}<br>";
echo "17track carriers to check: <strong>" . count($trackingToCheck17Track) . "</strong><br>";
echo "🚛 Freight carriers to check: <strong>" . count($trackingToCheckFreight) . "</strong><br>";
echo "</div><br>";

// Display skip reasons summary
echo "<div style='background: #fff3cd; padding: 10px; border-left: 4px solid #ffc107;'>";
echo "<strong>🚫 SKIPPED TRACKING (API Quota Protection)</strong><br>";
echo "Empty/NULL tracking: <strong>{$skipReasons['empty']}</strong><br>";
echo "Final status (Delivered/Cancelled/Refunded): <strong>{$skipReasons['final_status']}</strong><br>";
echo "Recently checked (within 6 hours): <strong>{$skipReasons['cache']}</strong><br>";
echo "⏰ Overdue (>" . OVERDUE_THRESHOLD_DAYS . " days past estimate): <strong>{$skipReasons['overdue']}</strong><br>";
echo "</div><br>";

if (empty($trackingToCheck17Track) && empty($trackingToCheckFreight)) {
    echo "<div style='background: #d4edda; padding: 15px; border: 2px solid #28a745;'>";
    echo "✅ No tracking numbers need checking at this time<br>";
    echo "</div>";
    echo "<br>Finished: " . date('Y-m-d H:i:s') . "<br>";
    $mysqli->close();
    exit;
}

// ========================================
// STEP 2A: Check 17track carriers in batches
// ========================================
$trackingResults = [];

if (!empty($trackingToCheck17Track)) {
    echo "<h3>🌐 STEP 2A: Checking 17track API (Parcel Carriers)</h3>";
    
    $headers = [
        '17token: ' . $API_KEY_17TRACK,
        'Content-Type: application/json'
    ];
    
    $trackingNumbers = array_keys($trackingToCheck17Track);
    $batches = array_chunk($trackingNumbers, BATCH_SIZE);
    
    echo "Processing " . count($batches) . " batch(es) of 17track API calls...<br><br>";
    
    foreach ($batches as $batchIdx => $batch) {
        echo "<div style='background: #d1ecf1; padding: 10px; margin: 10px 0; border-left: 4px solid #17a2b8;'>";
        echo "<strong>📦 BATCH " . ($batchIdx + 1) . "/" . count($batches) . "</strong> (" . count($batch) . " tracking numbers)<br><br>";
        
        // Step 1: Register with 17track (with carrier detection)
        $registerData = [];
        foreach ($batch as $tn) {
            $carrier = detectCarrier($tn);
            $item = ['number' => $tn];
            if ($carrier) {
                $item['carrier'] = $carrier;
                $carrierNames = [100 => 'USPS', 101 => 'UPS', 102 => 'FedEx', 103 => 'DHL'];
                echo "🔍 Detected carrier for {$tn}: <strong>{$carrierNames[$carrier]}</strong><br>";
            }
            $registerData[] = $item;
        }
        
        echo "<br>📤 Registering with 17track...<br>";
        
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
        
        // Handle registration errors
        if (isset($regData['data']['rejected'])) {
            foreach ($regData['data']['rejected'] as $rej) {
                $errCode = $rej['error']['code'] ?? 0;
                $trackingNum = $rej['number'] ?? '';
                
                if ($errCode == -18019903) {
                    echo "⚠️ Invalid/Unrecognized tracking: {$trackingNum} (skipping)<br>";
                    $batch = array_diff($batch, [$trackingNum]);
                } elseif ($errCode != -18019901) {
                    echo "⚠️ Registration issue: {$trackingNum} - Code {$errCode}<br>";
                }
            }
        }
        
        if (isset($regData['data']['accepted'])) {
            echo "✅ Registered: " . count($regData['data']['accepted']) . " tracking numbers<br>";
        }
        
        if (empty($batch)) {
            echo "⚠️ No valid tracking numbers in this batch<br>";
            echo "</div>";
            continue;
        }
        
        echo "⏳ Waiting 1 second...<br>";
        sleep(1);
        
        // Step 2: Get tracking info
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
            echo "</div>";
            continue;
        }
        
        $trackData = json_decode($trackResponse, true);
        
        if (!isset($trackData['data']['accepted'])) {
            echo "<span style='color: red;'>❌ No tracking data in response</span><br>";
            echo "</div>";
            continue;
        }
        
        // Process results
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
            
            // Parse delivered date
            $deliveredDate = null;
            if ($eventTime) {
                try {
                    $deliveredDate = (new DateTime($eventTime))->format('Y-m-d H:i:s');
                } catch (Exception $e) {
                    // Ignore
                }
            }
            
            // Map status code to text
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
}

// ========================================
// STEP 2B: Check Freight Carriers (Central Transport via WEB SCRAPING)
// ========================================

if (!empty($trackingToCheckFreight)) {
    echo "<h3>🚛 STEP 2B: Checking Freight Carriers (Web Scraping)</h3>";
    
    $freightProcessed = 0;
    
    foreach ($trackingToCheckFreight as $trackingNumber => $records) {
        $carrier = $records[0]['carrier'] ?? '';
        
        echo "<div style='background: #fff3cd; padding: 10px; margin: 10px 0; border-left: 4px solid #ffc107;'>";
        echo "<strong>PRO Number: {$trackingNumber}</strong> | Carrier: <strong>{$carrier}</strong><br><br>";
        
        // Check if it's Central Transport
        if (isCentralTransport($carrier)) {
            $result = trackCentralTransport($trackingNumber);
            $trackingResults[$trackingNumber] = $result;
            $freightProcessed++;
        } else {
            // Other freight carriers - mark as manual tracking needed
            echo "⚠️ {$carrier} web scraping not yet implemented - requires manual check<br>";
            echo "<small>Add scraping function for this carrier if needed</small><br><br>";
            $trackingResults[$trackingNumber] = [
                'status' => 'Manual Tracking Required',
                'delivered_date' => null,
                'carrier' => $carrier,
                'description' => 'Freight carrier - manual tracking required'
            ];
        }
        
        echo "</div>";
        
        // Delay between freight checks to avoid being blocked
        if ($freightProcessed < count($trackingToCheckFreight)) {
            echo "⏳ Waiting 2 seconds before next scrape...<br>";
            sleep(2);
        }
    }
    
    echo "<br><div style='background: #d4edda; padding: 10px; border-left: 4px solid #28a745;'>";
    echo "<strong>✅ FREIGHT TRACKING COMPLETE</strong><br>";
    echo "Freight tracking checked: <strong>{$freightProcessed}</strong><br>";
    echo "</div><br>";
}

// ========================================
// STEP 3: Update database with tracking statuses
// ========================================
echo "<h3>💾 STEP 3: Updating Database</h3>";

$updatedCount = 0;
$errorCount = 0;
$skippedCount = 0;

// Merge both tracking lists
$allTrackingToCheck = array_merge($trackingToCheck17Track, $trackingToCheckFreight);

foreach ($allTrackingToCheck as $trackingNumber => $records) {
    if (!isset($trackingResults[$trackingNumber])) {
        echo "⚠️ No result for {$trackingNumber} (skipping update)<br>";
        $skippedCount++;
        continue;
    }
    
    $result = $trackingResults[$trackingNumber];
    $status = $result['status'];
    $deliveredDate = $result['delivered_date'];
    
    // Skip if no useful status
    if (($status === 'Unknown' && !$deliveredDate) || $status === 'Not Found') {
        echo "→ Skipping {$trackingNumber} (status: {$status})<br>";
        $skippedCount++;
        continue;
    }
    
    // Update EACH record that uses this tracking number
    foreach ($records as $record) {
        $productID = $record['product_id'];
        $trackingIndex = $record['tracking_field_index'];
        
        $statusField = "tracking{$trackingIndex}_status";
        $dateField = "tracking{$trackingIndex}_delivered_date";
        
        // Build update query
        $updateFields = [];
        $updateValues = [];
        $updateTypes = "";
        
        // Update status
        $updateFields[] = "{$statusField} = ?";
        $updateValues[] = $status;
        $updateTypes .= "s";
        
        // Update delivered date if we have one
        if ($deliveredDate) {
            $updateFields[] = "{$dateField} = ?";
            $updateValues[] = $deliveredDate;
            $updateTypes .= "s";
        }
        
        // Update last checked timestamp
        $updateFields[] = "tracking_last_checked = NOW()";
        
        // Execute update
        $updateSQL = "UPDATE tblproduct SET " . implode(", ", $updateFields) . " WHERE ProductID = ?";
        $updateValues[] = $productID;
        $updateTypes .= "i";
        
        $stmt = $mysqli->prepare($updateSQL);
        
        if (!$stmt) {
            echo "<span style='color: red;'>❌ Prepare failed for ProductID {$productID}: " . $mysqli->error . "</span><br>";
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
            echo "<span style='color: red;'>❌ Update failed for ProductID {$productID}: " . $stmt->error . "</span><br>";
            $errorCount++;
        }
        
        $stmt->close();
    }
}

echo "<br><div style='background: #e7f3ff; padding: 10px; border-left: 4px solid #007bff;'>";
echo "<strong>📊 DATABASE UPDATE SUMMARY</strong><br>";
echo "Records updated: <strong>{$updatedCount}</strong><br>";
echo "Skipped (Unknown/Not Found): <strong>{$skippedCount}</strong><br>";
echo "Errors: <strong>{$errorCount}</strong><br>";
echo "</div><br>";

// ========================================
// STEP 4: Update main delivery_status based on individual tracking statuses
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
    
    // Remove null/empty values
    $statuses = array_filter($statuses);
    $deliveredDates = array_filter($deliveredDates, function($date) {
        return $date && $date !== '0000-00-00 00:00:00';
    });
    
    if (empty($statuses)) {
        continue;
    }
    
    $newMainStatus = null;
    $newDeliveredDate = null;
    
    // Priority 1: Check if ANY tracking is Delivered
    if (in_array('Delivered', $statuses)) {
        $newMainStatus = 'Delivered';
        
        if (!empty($deliveredDates)) {
            $newDeliveredDate = min($deliveredDates);
        }
    }
    // Priority 2: Check if ANY tracking is In Transit
    elseif (in_array('In Transit', $statuses)) {
        $newMainStatus = 'In Transit';
    }
    // Priority 3: Check if ANY tracking has Delivery Exception
    elseif (in_array('Delivery Exception', $statuses)) {
        $newMainStatus = 'Delivery Exception';
    }
    // Otherwise use first available status
    else {
        $newMainStatus = reset($statuses);
    }
    
    $currentStatus = $row['delivery_status'] ?? '';
    $currentDeliveredDate = $row['datedelivered'] ?? '';
    
    // Skip if already in final status
    if (in_array($currentStatus, $finalStatuses)) {
        continue;
    }
    
    // Build update if needed
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
echo "17track carriers checked: <strong>" . count($trackingToCheck17Track) . "</strong><br>";
echo "🚛 Freight carriers checked (web scraping): <strong>" . count($trackingToCheckFreight) . "</strong><br>";
echo "Total tracking results: <strong>" . count($trackingResults) . "</strong><br>";
echo "Individual tracking statuses updated: <strong>{$updatedCount}</strong><br>";
echo "Main delivery statuses updated: <strong>{$mainStatusUpdated}</strong><br>";
echo "Skipped (Unknown/Not Found): <strong>{$skippedCount}</strong><br>";
echo "Errors: <strong>{$errorCount}</strong><br>";
echo "<hr style='border-color: rgba(255,255,255,0.3); margin: 15px 0;'>";
echo "<strong>🚫 API QUOTA SAVED BY SKIPPING:</strong><br>";
echo "Final status packages: <strong>{$skipReasons['final_status']}</strong><br>";
echo "Cached (recent checks): <strong>{$skipReasons['cache']}</strong><br>";
echo "⏰ Overdue (>" . OVERDUE_THRESHOLD_DAYS . " days): <strong style='color: #ffc107;'>{$skipReasons['overdue']}</strong><br>";
echo "<hr style='border-color: rgba(255,255,255,0.3); margin: 15px 0;'>";
echo "Finished: <strong>" . date('Y-m-d H:i:s') . "</strong><br>";
echo "</div>";

$mysqli->close();
?>