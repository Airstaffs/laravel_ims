<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

date_default_timezone_set('America/Los_Angeles');

ini_set('max_execution_time', 1200);
ini_set('memory_limit', '512M');

// === Step 0: Database connection === //
$servertype = "hostinger";
$Connect = connectDatabase($servertype);
if ($Connect->connect_error) {
    die("❌ Connection failed: " . $Connect->connect_error);
}

// === Step 1: 17TRACK Setup === //
$url = 'https://api.17track.net/track/v2.2/gettrackinfo';
$apiKey = '5EC4C3FCD4929687DC76822C8D154C20';


// === Step 2: Fetch pending trackings === //
$query = "
    SELECT trackingnumber
    FROM tblinboundorders
    WHERE tracking_status != 'Delivered' OR tracking_status IS NULL OR tracking_status = ''
";
$result = mysqli_query($Connect, $query);

if (!$result) {
    echo "❌ Database query error: " . mysqli_error($Connect) . "<br>";
    exit;
}

$trackingNumbers = [];
if (mysqli_num_rows($result) > 0) {
    echo "📦 Found " . mysqli_num_rows($result) . " tracking numbers to update...<br><br>";
    while ($row = mysqli_fetch_assoc($result)) {
        $trackingNumbers[] = ["number" => $row['trackingnumber']];
    }
} else {
    echo "✅ No tracking numbers found to update.<br>";
    exit;
}

// === Step 3: Batch processing === //
$batchSize = 40;
$totalTracking = count($trackingNumbers);
$processed = 0;

while ($processed < $totalTracking) {
    $batchTracking = array_slice($trackingNumbers, $processed, $batchSize);
    $batchCount = count($batchTracking);

    echo "🔄 Processing batch " . ($processed + 1) . "-" . ($processed + $batchCount) . " of $totalTracking<br>";

    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($batchTracking));
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        "17token: $apiKey",
        "Content-Type: application/json"
    ]);

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($httpCode != 200) {
        echo "❌ API failed: HTTP $httpCode<br>Response: $response<br>";
        sleep(10);
        continue;
    }

    $responseData = json_decode($response, true);

    if (empty($responseData['data']['accepted'])) {
        echo "⚠️ No tracking data returned for this batch.<br>";
        $processed += $batchCount;
        continue;
    }

    $updatedCount = 0;
    foreach ($responseData['data']['accepted'] as $trackingInfo) {
        $trackingNumber = $trackingInfo['number'] ?? '';
        $trackInfo = $trackingInfo['track_info'] ?? [];
        $latest = $trackInfo['latest_event'] ?? [];

        if (empty($trackingNumber) || empty($trackInfo)) {
            echo "⚠️ Incomplete tracking info for entry.<br>";
            continue;
        }

        $tracking_status = $trackInfo['latest_status']['status'] ?? 'Unknown';
        $last_update = $latest['time_iso'] ?? null;
        $location = $latest['location'] ?? 'Unknown';
        $tracking_event = $latest['description'] ?? 'N/A';
        $final_carrier = $trackInfo['tracking']['providers'][0]['provider']['name'] ?? 'Unknown';
        $estimated_from = $trackInfo['time_metrics']['estimated_delivery_date']['from'] ?? null;


        // Escape values
        $trackingNumberSafe = mysqli_real_escape_string($Connect, $trackingNumber);
        $tracking_statusSafe = mysqli_real_escape_string($Connect, $tracking_status);
        $last_updateSafe = mysqli_real_escape_string($Connect, (string) $last_update);
        $locationSafe = mysqli_real_escape_string($Connect, $location);
        $tracking_eventSafe = mysqli_real_escape_string($Connect, $tracking_event);
        $final_carrierSafe = mysqli_real_escape_string($Connect, $final_carrier);
        $estimated_from_safe = mysqli_real_escape_string($Connect, (string) $estimated_from);

        // Update tblinboundorders
        $updateQuery = "
            UPDATE tblinboundorders
            SET 
                tracking_status = '$tracking_statusSafe',
                last_update = '$last_updateSafe',
                location = '$locationSafe',
                tracking_event = '$tracking_eventSafe',
                final_carrier = '$final_carrierSafe',
                estimated_delivery = '$estimated_from_safe'
            WHERE trackingnumber = '$trackingNumberSafe'
        ";

        if (mysqli_query($Connect, $updateQuery)) {
            $updatedCount++;

            // Update tblproduct too
            $updateProductQuery = "
                UPDATE tblproduct
                SET tracking_status = '$tracking_statusSafe',
                 estimated_delivery_api = '$estimated_from_safe'
                WHERE trackingnumber = '$trackingNumberSafe'
            ";
            mysqli_query($Connect, $updateProductQuery);

            echo "✅ Updated $trackingNumber to status: $tracking_statusSafe<br>";
        } else {
            echo "❌ Failed to update $trackingNumber: " . mysqli_error($Connect) . "<br>";
        }
    }

    echo "✅ Batch update complete: $updatedCount updated<br><br>";

    $processed += $batchCount;

    if ($processed < $totalTracking) {
        echo "⏳ Pausing 3 seconds before next batch...<br><br>";
        sleep(3);
    }
}

echo "<br>🎉 All tracking updates complete. Total processed: $processed<br>";

// === Database connection helper === //
function connectDatabase($servertype)
{
    if ($servertype === "hostinger") {
        $hostname = 'localhost';
        $username = 'u298641722_dbims_user';
        $password = '?cIk=|zRk3T';
        $database = 'u298641722_dbims';
    } else {
        exit("❌ Invalid server type. Check your configuration.");
    }

    $db = new mysqli($hostname, $username, $password, $database);
    if ($db->connect_error) {
        die("❌ Connection failed: " . $db->connect_error);
    }

    return $db;
}
?>
