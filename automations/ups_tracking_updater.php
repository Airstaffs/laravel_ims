<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

/**
 * IMPORTANT:
 * - Do NOT echo credentials.
 * - Rotate the secrets you pasted earlier (DB + UPS client_secret + refresh/access tokens).
 */

$imsv1_connect = dbDatabase("hostinger");
$imsv2_connect = dbDatabase("laravel_ims");

// Fetch UPS credentials (and refresh if needed)
$credentials = getUPSCredentials($imsv1_connect);
if (!$credentials) {
    die("UPS API credentials not found.\n");
}

$credentials = ups_refresher($imsv1_connect, $credentials);

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
            $username = 'u298641722_dbims_user';
            $password = '?cIk=|zRk3T';
            $database = 'u298641722_dbims';
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

function getUPSCredentials(mysqli $Connect)
{
    $id = 4;

    $stmt = $Connect->prepare("SELECT client_id, client_secret, access_token, refresh_token, expires_in FROM aws_key WHERE id = ?");
    $stmt->bind_param("i", $id);
    $stmt->execute();

    $res = $stmt->get_result();
    $row = $res ? $res->fetch_assoc() : null;

    $stmt->close();

    return $row ?: null;
}

function ups_refresher(mysqli $Connect, array $credentials)
{
    $currenttime = time();
    $expiryUnix = (int) ($credentials['expires_in'] ?? 0);
    $refreshAt = $expiryUnix - 2100;

    if ($expiryUnix > 0 && $currenttime <= $refreshAt) {
        return $credentials;
    }

    $refreshToken = (string) ($credentials['refresh_token'] ?? '');
    $clientId = (string) ($credentials['client_id'] ?? '');
    $clientSecret = (string) ($credentials['client_secret'] ?? '');

    if ($refreshToken === '' || $clientId === '' || $clientSecret === '') {
        return [
            'error' => true,
            'message' => 'Missing UPS OAuth refresh_token/client_id/client_secret.',
        ];
    }

    $payload = [
        'grant_type' => 'refresh_token',
        'refresh_token' => $refreshToken,
    ];

    $basic = base64_encode($clientId . ":" . $clientSecret);

    $curl = curl_init();
    curl_setopt_array($curl, [
        CURLOPT_URL => "https://onlinetools.ups.com/security/v1/oauth/refresh",
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_CUSTOMREQUEST => "POST",
        CURLOPT_POSTFIELDS => http_build_query($payload),
        CURLOPT_HTTPHEADER => [
            "Content-Type: application/x-www-form-urlencoded",
            "Authorization: Basic {$basic}",
        ],
    ]);

    $raw = curl_exec($curl);
    $err = curl_error($curl);
    $info = curl_getinfo($curl);
    curl_close($curl);

    if ($err) {
        return ['error' => true, 'message' => "cURL error: {$err}", 'http' => $info];
    }

    $response = json_decode($raw, true);

    if (!is_array($response)) {
        return ['error' => true, 'message' => 'Invalid JSON response from UPS refresh.', 'raw' => $raw, 'http' => $info];
    }

    if (isset($response['response']['errors'])) {
        return ['error' => true, 'message' => 'UPS refresh error.', 'ups' => $response, 'http' => $info];
    }

    if (empty($response['access_token'])) {
        return ['error' => true, 'message' => 'UPS refresh did not return access_token.', 'ups' => $response, 'http' => $info];
    }

    $newAccess = (string) $response['access_token'];
    $newRefresh = (string) ($response['refresh_token'] ?? $refreshToken);
    $expiresInSeconds = (int) ($response['expires_in'] ?? 0);
    $newExpiryUnix = $currenttime + $expiresInSeconds;

    $id = 4;
    $stmt = $Connect->prepare("UPDATE aws_key SET access_token = ?, refresh_token = ?, expires_in = ? WHERE id = ?");
    $stmt->bind_param("ssii", $newAccess, $newRefresh, $newExpiryUnix, $id);

    if (!$stmt->execute()) {
        $errMsg = $stmt->error;
        $stmt->close();
        return ['error' => true, 'message' => "DB update failed: {$errMsg}"];
    }
    $stmt->close();

    $credentials['access_token'] = $newAccess;
    $credentials['refresh_token'] = $newRefresh;
    $credentials['expires_in'] = $newExpiryUnix;

    return $credentials;
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

function handleDeliveredTransaction(mysqli $db, int $outboundId, int $productId, string $fnsku): array
{
    if ($outboundId <= 0 || $productId <= 0 || trim($fnsku) === '') {
        return ['ok' => false, 'error' => 'Missing outboundId/productId/fnsku'];
    }

    try {
        $db->begin_transaction();

        // 1) outboundordersitem -> Delivered
        // IMPORTANT: confirm your column name is orderstatus OR order_status
        $stmt1 = $db->prepare("UPDATE tbloutboundordersitem SET order_status = 'Delivered' WHERE outboundorderitemid = ?");
        if (!$stmt1)
            throw new Exception("prepare stmt1 failed: " . $db->error);
        $stmt1->bind_param("i", $outboundId);
        if (!$stmt1->execute())
            throw new Exception("execute stmt1 failed: " . $stmt1->error);
        $affected1 = $stmt1->affected_rows;
        $stmt1->close();

        // 2) product -> SoldList
        $stmt2 = $db->prepare("UPDATE tblproduct SET ProductModuleLoc = 'SoldList' WHERE ProductID = ?");
        if (!$stmt2)
            throw new Exception("prepare stmt2 failed: " . $db->error);
        $stmt2->bind_param("i", $productId);
        if (!$stmt2->execute())
            throw new Exception("execute stmt2 failed: " . $stmt2->error);
        $affected2 = $stmt2->affected_rows;
        $stmt2->close();

        // 3) fnsku units + 1
        $stmt3 = $db->prepare("UPDATE tblfnsku SET units = COALESCE(units,0) + 1 WHERE FNSKU = ?");
        if (!$stmt3)
            throw new Exception("prepare stmt3 failed: " . $db->error);
        $stmt3->bind_param("s", $fnsku);
        if (!$stmt3->execute())
            throw new Exception("execute stmt3 failed: " . $stmt3->error);
        $affected3 = $stmt3->affected_rows;
        $stmt3->close();

        // Optional safety: require rows affected
        if ($affected1 <= 0)
            throw new Exception("No outbound row updated for outboundorderitemid={$outboundId}");
        if ($affected2 <= 0)
            throw new Exception("No product row updated for ProductID={$productId}");
        if ($affected3 <= 0)
            throw new Exception("No fnsku row updated for FNSKU={$fnsku}");

        $db->commit();

        return [
            'ok' => true,
            'outbound_updated' => $affected1,
            'product_updated' => $affected2,
            'fnsku_updated' => $affected3,
        ];
    } catch (Throwable $e) {
        $db->rollback();
        return ['ok' => false, 'error' => $e->getMessage()];
    }
}