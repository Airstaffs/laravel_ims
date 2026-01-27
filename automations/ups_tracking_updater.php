<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

/**
 * IMPORTANT:
 * - Do NOT echo credentials.
 * - Rotate the secrets you pasted earlier (DB + UPS client_secret + refresh/access tokens).
 */

// $imsv1_connect = dbDatabase("hostinger");
$imsv2_connect = dbDatabase("laravel_ims");

// Fetch UPS credentials (and refresh if needed)
$credentials = fetchUpsCredentialsFromMainApp();

if (!empty($credentials['error'])) {
    die("UPS credential fetch failed: " . ($credentials['message'] ?? 'Unknown') . "\n");
}
if (!$credentials) {
    die("UPS API credentials not found.\n");
}

// Toggle test queue
$USE_TEST_QUEUE = false;

$queue = $USE_TEST_QUEUE
    ? [
        [
            'trackingnumber' => '1ZK5083X0318006920',
            'outboundorderitemid' => 999001,
            'productid' => 888001,
            'fnskuviewer' => 'X00ABC12345', // put a real fnsku for testing
        ],
        [
            'trackingnumber' => '1Z12345E0205271688',
            'outboundorderitemid' => 999002,
            'productid' => 888002,
            'fnskuviewer' => 'X00DEF67890',
        ],
    ]
    : getUpsTrackingQueueLatestDispense($imsv2_connect);

// Handle queue error shape
if (isset($queue['error']) && $queue['error'] === true) {
    die("Queue error: " . ($queue['message'] ?? 'Unknown') . "\n");
}

foreach ($queue as $row) {

    // ✅ Skip incomplete rows
    $trackingNumber = trim((string) ($row['trackingnumber'] ?? ''));
    $outboundId = (int) ($row['outboundorderitemid'] ?? 0);
    $productId = (int) ($row['productid'] ?? 0);

    if ($trackingNumber === '' || $outboundId <= 0 || $productId <= 0) {
        continue;
    }

    // 1) Fetch UPS details
    $resp = UPS_fetchDetails($trackingNumber, $credentials);

    // If token expired/invalid, refetch from main app and retry once
    if (!empty($resp['http_code']) && (int) $resp['http_code'] === 401) {
        $credentials = fetchUpsCredentialsFromMainApp();
        if (empty($credentials['error'])) {
            $resp = UPS_fetchDetails($trackingNumber, $credentials);
        }
    }

    if (!empty($resp['error'])) {
        echo "<pre>";
        print_r([
            'trackingnumber' => $trackingNumber,
            'outboundorderitemid' => $outboundId,
            'productid' => $productId,
            'error' => $resp,
        ]);
        echo "</pre>";
        continue;
    }

    // 2) Extract package status (use old logic first)
    $package_status = ups_extract_package_status_like_old($resp);

    if ($package_status === '') {
        echo "<pre>";
        print_r([
            'trackingnumber' => $trackingNumber,
            'outboundorderitemid' => $outboundId,
            'productid' => $productId,
            'error' => 'Could not extract UPS package status',
            'ups' => $resp,
        ]);
        echo "</pre>";
        continue;
    }

    // 3) Delivered-ish statuses are complex → skip for later
    if ($package_status === 'Delivered' || $package_status === 'Delivered by Local Post Office') {

        $fnsku = trim((string) ($row['fnskuviewer'] ?? ''));

        $done = handleDeliveredTransaction(
            $imsv2_connect,
            (int) $row['outboundorderitemid'],
            (int) $row['productid'],
            $fnsku
        );

        echo "<pre>";
        print_r([
            'trackingnumber' => $trackingNumber,
            'package_status' => $package_status,
            'delivered_update' => $done,
        ]);
        echo "</pre>";

        continue; // done
    }

    // 4) Simple update only (tbloutboundordersitem.trackingstatus)
    $upd = update_outbound_trackingstatus_by_outboundorderitemid(
        $imsv2_connect,
        $outboundId,
        $package_status
    );

    echo "<pre>";
    print_r([
        'trackingnumber' => $trackingNumber,
        'outboundorderitemid' => $outboundId,
        'productid' => $productId,
        'package_status' => $package_status,
        'update' => $upd,
    ]);
    echo "</pre>";

    // optional: avoid rate limits
    // sleep(1);
}

/* =========================
   Status extraction (OLD-first)
========================= */

function ups_extract_package_status_like_old(array $resp): string
{
    $ups = $resp['ups'] ?? null;
    if (!is_array($ups))
        return '';

    // OLD script main path
    $status =
        $ups['trackResponse']['shipment'][0]['package'][0]['currentStatus']['description']
        ?? null;

    // OLD script fallback warning path
    if (!$status) {
        $status =
            $ups['trackResponse']['Shipment'][0]['warnings'][0]['message']
            ?? null;
    }

    // Other common shapes (backup)
    if (!$status) {
        $status =
            $ups['trackResponse']['shipment'][0]['package'][0]['activity'][0]['status']['description']
            ?? null;
    }

    if (!$status) {
        $status =
            $ups['TrackResponse']['Shipment'][0]['Package'][0]['Activity'][0]['Status']['StatusType']['Description']
            ?? null;
    }

    return trim((string) $status);
}

/* =========================
   DB update
========================= */

function update_outbound_trackingstatus_by_outboundorderitemid(mysqli $db, int $outboundorderitemid, string $trackingstatus): array
{
    $sql = "UPDATE tbloutboundordersitem
            SET trackingstatus = ?
            WHERE outboundorderitemid = ?";

    $stmt = $db->prepare($sql);
    if (!$stmt)
        return ['ok' => false, 'error' => $db->error];

    $stmt->bind_param("si", $trackingstatus, $outboundorderitemid);
    $stmt->execute();

    $affected = $stmt->affected_rows;
    $err = $stmt->error;
    $stmt->close();

    if ($err)
        return ['ok' => false, 'error' => $err];

    return ['ok' => true, 'rowsAffected' => $affected];
}

/* =========================
   Existing functions you already have
========================= */

function dbDatabase($servertype)
{
    switch ($servertype) {
        case "ims":
            $hostname = 'localhost';
            $username = 'root';
            $password = '';
            $database = 'ims';
            break;

        case "hostinger":
            $hostname = 'localhost';
            $username = 'u298641722_web_ims';
            $password = 'ImsHosting!11923';
            $database = 'u298641722_ims';
            break;

        case "test":
            $hostname = 'localhost';
            $username = 'u298641722_testing_user';
            $password = 'Watdahek1234!';
            $database = 'u298641722_test';
            break;

        case "laravel_ims":
            $hostname = 'localhost';
            $username = 'imsv2_dbims_user';
            $password = 'Imsv2_dbims_user';
            $database = 'imsv2_dbims';
            break;

        default:
            die("❌ Invalid server type: {$servertype}");
    }

    $db = new mysqli($hostname, $username, $password, $database);
    if ($db->connect_errno) {
        die("❌ DB connect failed ({$servertype}): " . $db->connect_error);
    }

    $db->set_charset('utf8mb4');
    return $db;
}

function UPS_fetchDetails($trackingnumber, array $credentials)
{
    $inquiry = trim((string) $trackingnumber);

    $query = [
        "locale" => "en_US",
        "returnSignature" => "false",
        "returnMilestones" => "false"
    ];

    $token = (string) ($credentials['access_token'] ?? '');
    if ($token === '') {
        return ['error' => true, 'message' => 'Missing UPS access_token.'];
    }

    $curl = curl_init();

    curl_setopt_array($curl, [
        CURLOPT_URL => "https://onlinetools.ups.com/api/track/v1/details/" . rawurlencode($inquiry) . "?" . http_build_query($query),
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_CUSTOMREQUEST => "GET",
        CURLOPT_HTTPHEADER => [
            "Authorization: Bearer {$token}",
            "transId: " . uniqid("ims_", true),
            "transactionSrc: CustomerServicePortal",
            "Accept: application/json",
        ],
    ]);

    $raw = curl_exec($curl);
    $err = curl_error($curl);
    $info = curl_getinfo($curl);
    curl_close($curl);

    if ($err) {
        return ['error' => true, 'message' => "cURL error: {$err}", 'http' => $info];
    }

    $data = json_decode($raw, true);
    $httpCode = (int) ($info['http_code'] ?? 0);

    if (!is_array($data)) {
        return [
            'error' => true,
            'message' => 'Invalid JSON response from UPS Track API.',
            'http_code' => $httpCode,
            'raw' => $raw,
        ];
    }

    if ($httpCode < 200 || $httpCode >= 300) {
        return [
            'error' => true,
            'message' => 'UPS Track API returned non-2xx.',
            'http_code' => $httpCode,
            'ups' => $data,
        ];
    }

    return [
        'http_code' => $httpCode,
        'ups' => $data,
    ];
}

function getUpsTrackingQueueLatestDispense(mysqli $db): array
{
    $sql = "
        SELECT
            oi.trackingnumber,
            oi.outboundorderitemid,
            d2.productid,
            p.FNSKUviewer AS fnskuviewer
        FROM tbloutboundordersitem oi
        INNER JOIN (
            SELECT d.orderitemid, d.productid
            FROM tblorderitemdispense d
            INNER JOIN (
                SELECT orderitemid, MAX(id) AS max_id
                FROM tblorderitemdispense
                GROUP BY orderitemid
            ) x ON x.orderitemid = d.orderitemid AND x.max_id = d.id
        ) d2 ON d2.orderitemid = oi.outboundorderitemid
        INNER JOIN tblproduct p ON p.ProductID = d2.productid
        WHERE oi.carrier = 'UPS'
          AND oi.trackingnumber IS NOT NULL
          AND oi.trackingnumber <> ''
          AND oi.order_status = 'Shipped'
          AND d2.productid IS NOT NULL
          AND p.FNSKUviewer IS NOT NULL
          AND p.FNSKUviewer <> ''
        ORDER BY oi.outboundorderitemid DESC
    ";

    $res = $db->query($sql);
    if (!$res)
        return ['error' => true, 'message' => 'Query failed: ' . $db->error];

    $items = [];
    while ($row = $res->fetch_assoc()) {
        $items[] = [
            'trackingnumber' => (string) $row['trackingnumber'],
            'outboundorderitemid' => (int) $row['outboundorderitemid'],
            'productid' => (int) $row['productid'],
            'fnskuviewer' => (string) $row['fnskuviewer'],
        ];
    }
    return $items;
}

function handleDeliveredTransaction(mysqli $db, int $outboundId, int $productId, string $fnskuviewer): array
{
    if ($outboundId <= 0 || $productId <= 0 || trim($fnskuviewer) === '') {
        return ['ok' => false, 'error' => 'Missing outboundId/productId/fnsku'];
    }

    try {
        $db->begin_transaction();

        /* =========================
           1) Lock + update outbound item
        ========================= */

        $stmt1 = $db->prepare("
            UPDATE tbloutboundordersitem
            SET order_status = 'Delivered'
            WHERE outboundorderitemid = ? AND order_status = 'Shipped'
        ");
        if (!$stmt1)
            throw new Exception($db->error);
        $stmt1->bind_param("i", $outboundId);
        $stmt1->execute();
        if ($stmt1->affected_rows <= 0) {
            throw new Exception("Outbound item already processed or not Shipped");
        }
        $stmt1->close();

        /* =========================
           2) Move ONLY dispensed product
        ========================= */

        $stmt2 = $db->prepare("
            UPDATE tblproduct
            SET ProductModuleLoc = 'SoldList'
            WHERE ProductID = ?
        ");
        if (!$stmt2)
            throw new Exception($db->error);
        $stmt2->bind_param("i", $productId);
        $stmt2->execute();
        if ($stmt2->affected_rows <= 0) {
            throw new Exception("Product not moved to SoldList");
        }
        $stmt2->close();

        /* =========================
           3) Resolve pack / FNSKU increments
        ========================= */

        // Fetch mergeId + rtcounter of dispensed product
        $stmt = $db->prepare("
            SELECT mergeId, rtcounter
            FROM tblproduct
            WHERE ProductID = ?
        ");
        if (!$stmt)
            throw new Exception($db->error);
        $stmt->bind_param("i", $productId);
        $stmt->execute();
        $prod = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        $fnskuCounts = [];

        // CASE A: Single item (no mergeId)
        if (empty($prod['mergeId'])) {
            $baseFnsku = extractBaseFnsku($fnskuviewer);
            $fnskuCounts[$baseFnsku] = 1;
        }
        // CASE B: Pack parent → expand children
        else {
            $rtcounter = (int) $prod['rtcounter'];

            $stmt = $db->prepare("
                SELECT FNSKUviewer
                FROM tblproduct
                WHERE mergeTO = ?
            ");
            if (!$stmt)
                throw new Exception($db->error);
            $stmt->bind_param("i", $rtcounter);
            $stmt->execute();
            $res = $stmt->get_result();

            if ($res->num_rows === 0) {
                throw new Exception("Pack parent found but no children for rtcounter={$rtcounter}");
            }

            while ($row = $res->fetch_assoc()) {
                $baseFnsku = extractBaseFnsku($row['FNSKUviewer']);
                $fnskuCounts[$baseFnsku] = ($fnskuCounts[$baseFnsku] ?? 0) + 1;
            }

            $stmt->close();
        }

        /* =========================
           4) Apply FNSKU unit increments
        ========================= */

        $stmt = $db->prepare("
            UPDATE tblfnsku
            SET units = COALESCE(units,0) + ?
            WHERE FNSKU = ?
        ");
        if (!$stmt)
            throw new Exception($db->error);

        foreach ($fnskuCounts as $fnsku => $qty) {
            $stmt->bind_param("is", $qty, $fnsku);
            $stmt->execute();
            if ($stmt->affected_rows <= 0) {
                throw new Exception("Failed updating FNSKU units for {$fnsku}");
            }
        }
        $stmt->close();

        $db->commit();

        return [
            'ok' => true,
            'fnsku_updates' => $fnskuCounts,
        ];

    } catch (Throwable $e) {
        $db->rollback();
        return ['ok' => false, 'error' => $e->getMessage()];
    }
}

function extractBaseFnsku(string $fnsku): string
{
    $fnsku = strtoupper(trim($fnsku));
    if ($fnsku === '') {
        return $fnsku;
    }

    // Strip exactly 1 letter + 1 digit prefix if remainder is FNSKU
    if (preg_match('/^[A-Z][0-9](X[0-9A-Z]+)$/', $fnsku, $m)) {
        return $m[1];
    }

    return $fnsku;
}

function fetchUpsCredentialsFromMainApp(): array
{
    $url = 'https://ims.tecniquality.com/dbserver/ups/ups_credentials.php';

    // ✅ Must match EXPECTED_KEY on the main app endpoint
    $secret = 'REPLACE_WITH_LONG_RANDOM_SECRET';

    $ch = curl_init();
    curl_setopt_array($ch, [
        CURLOPT_URL => $url,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_CUSTOMREQUEST => 'POST',
        CURLOPT_TIMEOUT => 20,
        CURLOPT_HTTPHEADER => [
            'Accept: application/json',
            'X-IMS-KEY: ' . $secret,
        ],
        // optional body if you want to log caller identity
        CURLOPT_POSTFIELDS => http_build_query([
            'caller' => 'ups_tracker_second_app',
        ]),
    ]);

    $raw = curl_exec($ch);
    $err = curl_error($ch);
    $info = curl_getinfo($ch);
    curl_close($ch);

    if ($err) {
        return ['error' => true, 'message' => "cURL error: {$err}", 'http' => $info];
    }

    $data = json_decode($raw, true);

    if (!is_array($data) || empty($data['ok'])) {
        return ['error' => true, 'message' => 'Invalid response from main app', 'raw' => $raw, 'http' => $info];
    }

    $token = (string) ($data['access_token'] ?? '');
    $exp = (int) ($data['expires_unix'] ?? 0);

    if ($token === '' || $exp <= 0) {
        return ['error' => true, 'message' => 'Main app returned missing token/expiry', 'raw' => $data];
    }

    return [
        'access_token' => $token,
        'expires_in' => $exp, // keep same key name your script expects (absolute unix)
    ];
}