<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

date_default_timezone_set('America/Los_Angeles');

ini_set('max_execution_time', 1200);  // 20 mins
ini_set('memory_limit', '512M');

// === Step 0: Connect to Hostinger DB === //
$servertype = "hostinger";
$conn = connectDatabase($servertype);
if ($conn->connect_error) {
    die("❌ Connection failed: " . $conn->connect_error);
}

// === Step 1: 17TRACK API setup === //
$currentDate = date('Y-m-d');
$registerUrl = 'https://api.17track.net/track/v2.2/register';
$apiKey = '5EC4C3FCD4929687DC76822C8D154C20';

// === Step 2: Fetch tracking numbers to register === //
$query = "
    SELECT trackingnumber, datedelivered, rtid AS orderno, orderdate
    FROM tblproduct
    WHERE ProductModuleLoc = 'Orders'
      AND (trackingnumber IS NOT NULL AND TRIM(trackingnumber) != '')
      AND datedelivered >= CURDATE() - INTERVAL 7 DAY
    ORDER BY datedelivered ASC
";

$result = mysqli_query($conn, $query);

if (!$result) {
    echo "❌ Database error: " . mysqli_error($conn) . "<br>";
    exit;
}

$totalFetched = mysqli_num_rows($result);
if ($totalFetched === 0) {
    echo "✅ No new tracking numbers to register.<br>";
    exit;
}

echo "📦 Total tracking numbers fetched: $totalFetched<br><br>";

// === Counters === //
$totalProcessed = 0;
$totalInserted = 0;
$totalSkipped = 0;
$totalFailed = 0;

while ($row = mysqli_fetch_assoc($result)) {
    $trackingnumber = $row['trackingnumber'];
    $orderdate = $row['orderdate'];
    $orderno = $row['orderno'];
    $totalProcessed++;

    // Step 3: Skip if already registered
    $checkQuery = "
        SELECT id FROM tblinboundorders 
        WHERE trackingnumber = '$trackingnumber' AND registered = 'yes'
        LIMIT 1
    ";
    $checkResult = mysqli_query($conn, $checkQuery);

    if (mysqli_num_rows($checkResult) > 0) {
        $totalSkipped++;
        echo "🔄 Skipping $trackingnumber — already registered.<br>";
        continue;
    }

    echo "🟢 Registering $trackingnumber...<br>";

    // Step 4: Call API
    $trackingData = [
        [
            "number" => $trackingnumber,
            "lang" => "",
            "email" => "",
            "param" => "",
            "order_no" => $orderno,
            "order_time" => $orderdate,
            "carrier" => 0,
            "final_carrier" => 0,
            "auto_detection" => true,
            "tag" => "MyOrderId",
            "remark" => "My Remarks"
        ]
    ];

    $ch = curl_init($registerUrl);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($trackingData));
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        "17token: $apiKey",
        "Content-Type: application/json"
    ]);

    $registerResponse = curl_exec($ch);
    curl_close($ch);

    $registerResult = json_decode($registerResponse, true);
    $accepted = $registerResult['data']['accepted'] ?? [];

    if (!empty($accepted)) {
        $registered = 'yes';
        $insert = "
            INSERT INTO tblinboundorders 
            (trackingnumber, tracking_status, last_update, location, tracking_event, final_carrier, registered)
            VALUES 
            ('$trackingnumber', NULL, NULL, NULL, NULL, NULL, '$registered')
        ";

        if (mysqli_query($conn, $insert)) {
            $totalInserted++;
            echo "✅ $trackingnumber inserted into tblinboundorders.<br>";
        } else {
            $totalFailed++;
            echo "❌ Error inserting $trackingnumber: " . mysqli_error($conn) . "<br>";
        }
    } else {
        $totalFailed++;
        echo "⚠️ Registration failed for $trackingnumber. Possibly already exists or invalid.<br>";
    }

    //ob_flush();
    flush();
    sleep(1); // Optional pause between requests
}

// === Final Report === //
echo "<br>🎯 Processing complete.<br>";
echo "🔢 Total Fetched: $totalFetched<br>";
echo "🔁 Total Processed: $totalProcessed<br>";
echo "✅ Inserted: $totalInserted<br>";
echo "🔄 Skipped (already registered): $totalSkipped<br>";
echo "❌ Failed: $totalFailed<br>";

// === Step 5: DB connection helper === //
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