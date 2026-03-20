<?php
/**
 * PAA Runner (Native PHP)
 *
 * Behavior
 * --------------------------------------------------
 * - Cron runs every 5 minutes.
 * - No trigger scheduling is used.
 * - Each rule has:
 *      start, end, min, max, delta
 * - While inside a rule window:
 *      adjust prices in batches
 * - After that window ends:
 *      restore all adjusted items back to original_price
 * - A run can continue across many cron ticks until fully done.
 *
 * SOURCE MODES
 * --------------------------------------------------
 * - manual:
 *      snapshots rows from tbl_paa_automation_items where automation_id=? and is_active=1
 * - automatic:
 *      snapshots rows from tblfnsku where:
 *          amazon_status = 'Active'
 *          AND MSKU IS NOT NULL AND MSKU <> ''
 *          AND storename IS NOT NULL AND storename <> ''
 *      ordered by FNSKUID ASC
 *
 * IMPORTANT REQUIRED DB CHANGES
 * --------------------------------------------------
 * 1) tbl_paa_runs
 *
 * ALTER TABLE tbl_paa_runs
 * ADD COLUMN phase ENUM('adjust','restore','done') NOT NULL DEFAULT 'adjust' AFTER status,
 * ADD COLUMN window_start VARCHAR(5) DEFAULT NULL AFTER phase,
 * ADD COLUMN window_end VARCHAR(5) DEFAULT NULL AFTER window_start;
 *
 * 2) tbl_paa_run_items
 *
 * ALTER TABLE tbl_paa_run_items
 * ADD COLUMN storename VARCHAR(100) NOT NULL AFTER sku,
 * ADD COLUMN fnskuid INT NULL AFTER storename,
 * ADD COLUMN original_price DECIMAL(10,2) DEFAULT NULL AFTER current_price,
 * ADD COLUMN adjusted_at_utc DATETIME DEFAULT NULL AFTER processed_at_utc,
 * ADD COLUMN restored_at_utc DATETIME DEFAULT NULL AFTER adjusted_at_utc;
 *
 * Recommended indexes
 * --------------------------------------------------
 * ALTER TABLE tbl_paa_automation_items
 * ADD INDEX idx_paa_autoitem_auto_msku_store (automation_id, msku, storename);
 *
 * ALTER TABLE tbl_paa_run_items
 * ADD INDEX idx_paa_runitems_run_status (run_id, status),
 * ADD INDEX idx_paa_runitems_run_store_status (run_id, storename, status),
 * ADD INDEX idx_paa_runitems_run_restore (run_id, adjusted_at_utc, restored_at_utc);
 */

date_default_timezone_set('UTC');

// ----------------------------------------------------
// CONFIG
// ----------------------------------------------------
$BATCH_SIZE = 10;
$MAX_ATTEMPTS = 10;
$PROCESSING_TIMEOUT_MIN = 15;
$LOG_PREFIX = '[PAA] ';
$LARAVEL_ROOT = realpath(__DIR__ . '/..');

// Source mode: 'manual' or 'automatic'
$ITEM_SOURCE_MODE = 'manual';

// Automatic mode defaults
$AUTO_AMAZON_STATUS = 'Active';

// ----------------------------------------------------
// ENV / DB HELPERS
// ----------------------------------------------------


// ----------------------------------------------------
// MAIN
// ----------------------------------------------------

try {
    $mysqli = db();

    reset_stuck_processing_items($mysqli, $PROCESSING_TIMEOUT_MIN, $MAX_ATTEMPTS);

    $automations = get_enabled_automations($mysqli);

    if (!count($automations)) {
        logg("No enabled automations.");
        exit(0);
    }

    logg("Enabled automations: " . count($automations));

    foreach ($automations as $automation) {
        global $ITEM_SOURCE_MODE;

        $automationId = (int) $automation['id'];
        $tz = $automation['timezone'] ?: 'America/Los_Angeles';
        $rules = safe_json_array($automation['rules_json'] ?? '[]');

        $currentHHMM = now_local_hhmm($tz);
        $activeWindow = first_active_window($rules, $currentHHMM);

        logg("Automation #{$automationId} mode={$ITEM_SOURCE_MODE} local_time={$currentHHMM}");

        $openRun = find_open_run_for_automation($mysqli, $automationId);

        if ($openRun) {
            $runId = (int) $openRun['id'];
            $phase = (string) $openRun['phase'];
            $windowStart = (string) $openRun['window_start'];
            $windowEnd = (string) $openRun['window_end'];

            logg("Found open run #{$runId} phase={$phase} window={$windowStart}-{$windowEnd}");

            if ($phase === 'restore') {
                process_restore_phase($mysqli, $automation, $openRun, $BATCH_SIZE, $MAX_ATTEMPTS);
                continue;
            }

            process_adjust_phase($mysqli, $automation, $openRun, $currentHHMM, $BATCH_SIZE, $MAX_ATTEMPTS);
            continue;
        }

        if (!$activeWindow) {
            logg("Automation #{$automationId} has no active rule window right now.");
            continue;
        }

        $windowStart = $activeWindow['start'];
        $windowEnd = $activeWindow['end'];

        $existingWindowRun = find_open_run_for_window($mysqli, $automationId, $windowStart, $windowEnd);

        if ($existingWindowRun) {
            logg("Automation #{$automationId} already has open run for window {$windowStart}-{$windowEnd}");

            if (($existingWindowRun['phase'] ?? '') === 'restore') {
                process_restore_phase($mysqli, $automation, $existingWindowRun, $BATCH_SIZE, $MAX_ATTEMPTS);
            } else {
                process_adjust_phase($mysqli, $automation, $existingWindowRun, $currentHHMM, $BATCH_SIZE, $MAX_ATTEMPTS);
            }

            continue;
        }

        $runId = create_run_for_window($mysqli, $automationId, $windowStart, $windowEnd);

        logg("Created run #{$runId} for automation #{$automationId} window {$windowStart}-{$windowEnd}");

        $newRun = find_open_run_for_automation($mysqli, $automationId);

        if ($newRun && ($newRun['phase'] ?? '') === 'adjust' && ($newRun['status'] ?? '') !== 'done') {
            process_adjust_phase($mysqli, $automation, $newRun, $currentHHMM, $BATCH_SIZE, $MAX_ATTEMPTS);
        }
    }

    logg("Done.");
} catch (Exception $e) {
    logg("FATAL: " . $e->getMessage());
    exit(1);
}

function load_env($envPath)
{
    static $cache = null;

    if ($cache !== null) {
        return $cache;
    }

    $cache = [];

    if (!file_exists($envPath)) {
        return $cache;
    }

    $lines = file($envPath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);

    foreach ($lines as $line) {
        $line = trim($line);

        if ($line === '' || str_starts_with($line, '#')) {
            continue;
        }

        if (!str_contains($line, '=')) {
            continue;
        }

        [$k, $v] = explode('=', $line, 2);
        $k = trim($k);
        $v = trim($v);

        if (
            (str_starts_with($v, '"') && str_ends_with($v, '"')) ||
            (str_starts_with($v, "'") && str_ends_with($v, "'"))
        ) {
            $v = substr($v, 1, -1);
        }

        $cache[$k] = $v;
    }

    return $cache;
}

function envv($key, $default = null)
{
    global $LARAVEL_ROOT;
    $env = load_env($LARAVEL_ROOT . '/.env');
    return $env[$key] ?? $default;
}

function db()
{
    static $mysqli = null;

    if ($mysqli) {
        return $mysqli;
    }

    $host = envv('localhost');
    $port = (int) envv('DB_PORT', 3306);
    $user = envv('DB_USERNAME', '');
    $pass = envv('DB_PASSWORD', '');
    $name = envv('DB_DATABASE', '');

    $mysqli = new mysqli($host, $user, $pass, $name, $port);

    if ($mysqli->connect_error) {
        echo "DB CONNECT ERROR: " . $mysqli->connect_error . PHP_EOL;
        exit(1);
    }

    $mysqli->set_charset('utf8mb4');
    return $mysqli;
}

function logg($msg)
{
    global $LOG_PREFIX;
    echo $LOG_PREFIX . $msg . PHP_EOL;
}

// ----------------------------------------------------
// JSON / TIME HELPERS
// ----------------------------------------------------

function safe_json_array($v)
{
    if ($v === null) {
        return [];
    }

    if (is_array($v)) {
        return $v;
    }

    $s = trim((string) $v);
    if ($s === '') {
        return [];
    }

    $j = json_decode($s, true);
    return is_array($j) ? $j : [];
}

function time_hhmm_to_minutes($hhmm)
{
    $hhmm = trim((string) $hhmm);

    if (!preg_match('/^\d{2}:\d{2}$/', $hhmm)) {
        return null;
    }

    [$hh, $mm] = array_map('intval', explode(':', $hhmm));
    return ($hh * 60) + $mm;
}

function is_time_in_window($currentHHMM, $startHHMM, $endHHMM)
{
    $current = time_hhmm_to_minutes($currentHHMM);
    $start = time_hhmm_to_minutes($startHHMM);
    $end = time_hhmm_to_minutes($endHHMM);

    if ($current === null || $start === null || $end === null) {
        return false;
    }

    if ($start === $end) {
        return false;
    }

    if ($start < $end) {
        return $current >= $start && $current < $end;
    }

    return $current >= $start || $current < $end;
}

function now_local_hhmm($tzLocal = 'America/Los_Angeles')
{
    try {
        $dt = new DateTime('now', new DateTimeZone($tzLocal ?: 'America/Los_Angeles'));
        return $dt->format('H:i');
    } catch (Exception $e) {
        return gmdate('H:i');
    }
}

function rule_window_key($start, $end)
{
    return trim((string) $start) . '|' . trim((string) $end);
}

function first_active_window(array $rules, $currentHHMM)
{
    foreach ($rules as $r) {
        if (!is_array($r)) {
            continue;
        }

        $start = trim((string) ($r['start'] ?? ''));
        $end = trim((string) ($r['end'] ?? ''));

        if ($start === '' || $end === '') {
            continue;
        }

        if (is_time_in_window($currentHHMM, $start, $end)) {
            return [
                'start' => $start,
                'end' => $end,
                'key' => rule_window_key($start, $end),
            ];
        }
    }

    return null;
}

function pick_delta_from_rules($currentPrice, array $rulesForCurrentWindow, $defaultDelta = 0.0)
{
    $p = (float) $currentPrice;

    usort($rulesForCurrentWindow, function ($a, $b) {
        $aMin = is_numeric($a['min'] ?? null) ? (float) $a['min'] : INF;
        $bMin = is_numeric($b['min'] ?? null) ? (float) $b['min'] : INF;
        return $aMin <=> $bMin;
    });

    foreach ($rulesForCurrentWindow as $r) {
        if (!is_array($r)) {
            continue;
        }

        if (!isset($r['min'], $r['max'], $r['delta'])) {
            continue;
        }

        $min = is_numeric($r['min']) ? (float) $r['min'] : null;
        $max = is_numeric($r['max']) ? (float) $r['max'] : null;
        $delta = is_numeric($r['delta']) ? (float) $r['delta'] : null;

        if ($min === null || $max === null || $delta === null) {
            continue;
        }

        if ($min <= $p && $p < $max) {
            return $delta;
        }
    }

    return (float) $defaultDelta;
}

function normalize_store($store)
{
    return trim((string) $store);
}

function group_batch_by_store(array $batch)
{
    $grouped = [];

    foreach ($batch as $item) {
        $store = normalize_store($item['storename'] ?? '');

        if ($store === '') {
            $store = '__UNKNOWN__';
        }

        if (!isset($grouped[$store])) {
            $grouped[$store] = [];
        }

        $grouped[$store][] = $item;
    }

    return $grouped;
}

// ----------------------------------------------------
// HTTP / AMAZON
// ----------------------------------------------------

function http_post_json($url, $payload, $headers = [], $timeout = 50)
{
    $ch = curl_init($url);

    curl_setopt_array($ch, [
        CURLOPT_POST => true,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER => array_merge([
            'Content-Type: application/json',
            'Accept: application/json',
        ], $headers),
        CURLOPT_POSTFIELDS => json_encode($payload),
        CURLOPT_TIMEOUT => $timeout,
    ]);

    $raw = curl_exec($ch);
    $errno = curl_errno($ch);
    $err = curl_error($ch);
    $code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);

    curl_close($ch);

    if ($errno) {
        throw new Exception("cURL error ({$errno}): {$err}");
    }

    return [
        'status' => $code,
        'raw' => $raw,
        'json' => json_decode($raw, true),
    ];
}

function fetchCurrentPrice($store, $sku, $marketplaceIds)
{
    $base = rtrim((string) envv('APP_URL'), '/');
    $cronKey = envv('CRON_KEY', null);

    if (!$base) {
        throw new Exception("APP_URL missing in .env");
    }

    $url = $base . '/api/amazon/search-listings';

    $payload = [
        'store' => $store,
        'marketplaceIds' => $marketplaceIds ?: ['ATVPDKIKX0DER'],
        'includedData' => ['offers', 'summaries', 'productTypes'],
        'identifiersType' => 'SKU',
        'identifiers' => [$sku],
        'pageSize' => 1,
        'sortBy' => 'lastUpdatedDate',
        'sortOrder' => 'DESC',
    ];

    $headers = [];

    if ($cronKey) {
        $headers[] = 'X-CRON-KEY: ' . $cronKey;
    }

    $res = http_post_json($url, $payload, $headers, 50);

    if ($res['status'] < 200 || $res['status'] >= 300) {
        $msg = $res['json']['error']['message'] ?? $res['json']['message'] ?? $res['raw'];
        throw new Exception("search-listings failed HTTP {$res['status']}: {$msg}");
    }

    $items = $res['json']['data']['items'] ?? [];
    if (!$items || !isset($items[0])) {
        return null;
    }

    $it = $items[0];

    $price =
        $it['offers'][0]['price']['amount'] ??
        $it['offers'][0]['listingPrice']['amount'] ??
        null;

    if ($price === null) {
        return null;
    }

    return (float) $price;
}

function patchPrice($store, $sku, $newPrice, $currency, $marketplaceIds)
{
    $base = rtrim((string) envv('APP_URL'), '/');
    $cronKey = envv('CRON_KEY', null);

    if (!$base) {
        throw new Exception("APP_URL missing in .env");
    }

    $headers = [];

    if ($cronKey) {
        $headers[] = 'X-CRON-KEY: ' . $cronKey;
    }

    $url = $base . '/api/amazon/listings/update-one';

    $payload = [
        'store' => $store,
        'marketplaceIds' => $marketplaceIds ?: ['ATVPDKIKX0DER'],
        'sku' => $sku,
        'price' => round((float) $newPrice, 2),
        'priceCleared' => false,
        'currency' => $currency ?: 'USD',
        'productType' => 'PRODUCT',
    ];

    $res = http_post_json($url, $payload, $headers, 50);

    if ($res['status'] < 200 || $res['status'] >= 300) {
        $msg =
            $res['json']['error']['errors'][0]['message'] ??
            $res['json']['error']['message'] ??
            $res['json']['message'] ??
            $res['raw'];

        throw new Exception("update-one failed HTTP {$res['status']}: {$msg}");
    }

    $ok = $res['json']['success'] ?? false;

    if (!$ok) {
        $msg = $res['json']['message'] ?? $res['raw'];
        throw new Exception("update-one returned not-success: {$msg}");
    }

    return true;
}

// ----------------------------------------------------
// DB HELPERS
// ----------------------------------------------------

function fetch_all_assoc($result)
{
    $rows = [];

    if (!$result) {
        return $rows;
    }

    while ($row = $result->fetch_assoc()) {
        $rows[] = $row;
    }

    return $rows;
}

function resolveSkuFromMsku($mysqli, $msku)
{
    return trim((string) $msku) !== '' ? (string) $msku : null;
}

function syncResolvedSku($mysqli, $automationId, $runItemId, $msku, $storename, $sku)
{
    $storename = normalize_store($storename);

    if ($automationId > 0) {
        $stmt = $mysqli->prepare("
            UPDATE tbl_paa_automation_items
            SET sku=?, updated_at=UTC_TIMESTAMP()
            WHERE automation_id=? AND msku=? AND storename=?
        ");
        $stmt->bind_param('siss', $sku, $automationId, $msku, $storename);
        $stmt->execute();
        $stmt->close();
    }

    $stmt = $mysqli->prepare("
        UPDATE tbl_paa_run_items
        SET sku=?, updated_at=UTC_TIMESTAMP()
        WHERE id=?
    ");
    $stmt->bind_param('si', $sku, $runItemId);
    $stmt->execute();
    $stmt->close();
}

function reset_stuck_processing_items($mysqli, $timeoutMin, $maxAttempts)
{
    $sql1 = "
        UPDATE tbl_paa_run_items
        SET status='pending',
            last_error=CONCAT(IFNULL(last_error,''), ' | reset stuck processing'),
            updated_at=UTC_TIMESTAMP()
        WHERE status='processing'
          AND updated_at < (UTC_TIMESTAMP() - INTERVAL ? MINUTE)
          AND attempts < ?
          AND adjusted_at_utc IS NULL
    ";
    $stmt = $mysqli->prepare($sql1);
    $stmt->bind_param('ii', $timeoutMin, $maxAttempts);
    $stmt->execute();
    $affected1 = $stmt->affected_rows;
    $stmt->close();

    $sql2 = "
        UPDATE tbl_paa_run_items
        SET status='success',
            last_error=CONCAT(IFNULL(last_error,''), ' | reset stuck restore'),
            updated_at=UTC_TIMESTAMP()
        WHERE status='processing'
          AND restored_at_utc IS NULL
          AND adjusted_at_utc IS NOT NULL
          AND updated_at < (UTC_TIMESTAMP() - INTERVAL ? MINUTE)
          AND attempts < ?
    ";
    $stmt = $mysqli->prepare($sql2);
    $stmt->bind_param('ii', $timeoutMin, $maxAttempts);
    $stmt->execute();
    $affected2 = $stmt->affected_rows;
    $stmt->close();

    if ($affected1 > 0) {
        logg("Reset stuck adjust items: {$affected1}");
    }

    if ($affected2 > 0) {
        logg("Reset stuck restore items: {$affected2}");
    }
}

function get_enabled_automations($mysqli)
{
    $sql = "
        SELECT *
        FROM tbl_paa_automations
        WHERE is_enabled=1
        ORDER BY id ASC
    ";

    $res = $mysqli->query($sql);

    if (!$res) {
        throw new Exception("Automation query failed: " . $mysqli->error);
    }

    return fetch_all_assoc($res);
}

function find_open_run_for_automation($mysqli, $automationId)
{
    $stmt = $mysqli->prepare("
        SELECT *
        FROM tbl_paa_runs
        WHERE automation_id=?
          AND status IN ('pending','running')
        ORDER BY id ASC
        LIMIT 1
    ");
    $stmt->bind_param('i', $automationId);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    return $row ?: null;
}

function find_open_run_for_window($mysqli, $automationId, $windowStart, $windowEnd)
{
    $stmt = $mysqli->prepare("
        SELECT *
        FROM tbl_paa_runs
        WHERE automation_id=?
          AND window_start=?
          AND window_end=?
          AND status IN ('pending','running')
        ORDER BY id ASC
        LIMIT 1
    ");
    $stmt->bind_param('iss', $automationId, $windowStart, $windowEnd);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    return $row ?: null;
}

function get_snapshot_items_manual($mysqli, $automationId)
{
    $items = [];

    $stmt = $mysqli->prepare("
        SELECT id, msku, sku, storename, NULL AS fnskuid
        FROM tbl_paa_automation_items
        WHERE automation_id=? AND is_active=1
        ORDER BY id ASC
    ");
    $stmt->bind_param('i', $automationId);
    $stmt->execute();
    $res = $stmt->get_result();

    while ($r = $res->fetch_assoc()) {
        $msku = trim((string) ($r['msku'] ?? ''));
        $storename = normalize_store($r['storename'] ?? '');

        if ($msku === '' || $storename === '') {
            continue;
        }

        $items[] = [
            'automation_item_id' => (int) $r['id'],
            'msku' => $msku,
            'sku' => $r['sku'] !== null ? trim((string) $r['sku']) : null,
            'storename' => $storename,
            'fnskuid' => null,
        ];
    }

    $stmt->close();

    return $items;
}

function get_snapshot_items_automatic($mysqli)
{
    global $AUTO_AMAZON_STATUS;

    $items = [];

    $sql = "
        SELECT FNSKUID, MSKU, storename
        FROM tblfnsku
        WHERE amazon_status=?
          AND MSKU IS NOT NULL AND TRIM(MSKU) <> ''
          AND storename IS NOT NULL AND TRIM(storename) <> ''
        ORDER BY FNSKUID ASC
    ";

    $stmt = $mysqli->prepare($sql);
    $stmt->bind_param('s', $AUTO_AMAZON_STATUS);
    $stmt->execute();
    $res = $stmt->get_result();

    while ($r = $res->fetch_assoc()) {
        $msku = trim((string) ($r['MSKU'] ?? ''));
        $storename = normalize_store($r['storename'] ?? '');
        $fnskuid = isset($r['FNSKUID']) ? (int) $r['FNSKUID'] : null;

        if ($msku === '' || $storename === '') {
            continue;
        }

        $items[] = [
            'automation_item_id' => null,
            'msku' => $msku,
            'sku' => null,
            'storename' => $storename,
            'fnskuid' => $fnskuid,
        ];
    }

    $stmt->close();

    return $items;
}

function get_snapshot_items_for_run($mysqli, $automationId)
{
    global $ITEM_SOURCE_MODE;

    if ($ITEM_SOURCE_MODE === 'automatic') {
        return get_snapshot_items_automatic($mysqli);
    }

    return get_snapshot_items_manual($mysqli, $automationId);
}

function create_run_for_window($mysqli, $automationId, $windowStart, $windowEnd)
{
    global $ITEM_SOURCE_MODE;

    $stmt = $mysqli->prepare("
        INSERT INTO tbl_paa_runs
            (automation_id, scheduled_for_utc, status, phase, window_start, window_end, started_at_utc, created_at, updated_at)
        VALUES
            (?, UTC_TIMESTAMP(), 'running', 'adjust', ?, ?, UTC_TIMESTAMP(), UTC_TIMESTAMP(), UTC_TIMESTAMP())
    ");
    $stmt->bind_param('iss', $automationId, $windowStart, $windowEnd);
    $ok = $stmt->execute();

    if (!$ok) {
        $err = $stmt->error;
        $stmt->close();
        throw new Exception("Failed to create run: {$err}");
    }

    $runId = (int) $stmt->insert_id;
    $stmt->close();

    $items = get_snapshot_items_for_run($mysqli, $automationId);
    $totalItems = count($items);

    if ($totalItems > 0) {
        $ins = $mysqli->prepare("
            INSERT INTO tbl_paa_run_items
                (run_id, automation_item_id, msku, sku, storename, fnskuid, status, attempts, created_at, updated_at)
            VALUES
                (?, ?, ?, ?, ?, ?, 'pending', 0, UTC_TIMESTAMP(), UTC_TIMESTAMP())
        ");

        foreach ($items as $it) {
            $automationItemId = $it['automation_item_id'] !== null ? (int) $it['automation_item_id'] : null;
            $msku = (string) $it['msku'];
            $sku = $it['sku'] !== null ? (string) $it['sku'] : null;
            $storename = (string) $it['storename'];
            $fnskuid = $it['fnskuid'] !== null ? (int) $it['fnskuid'] : null;

            $ins->bind_param('iisssi', $runId, $automationItemId, $msku, $sku, $storename, $fnskuid);
            $ins->execute();
        }

        $ins->close();
    }

    $stmt = $mysqli->prepare("
        UPDATE tbl_paa_runs
        SET total_items=?, updated_at=UTC_TIMESTAMP()
        WHERE id=?
    ");
    $stmt->bind_param('ii', $totalItems, $runId);
    $stmt->execute();
    $stmt->close();

    if ($totalItems === 0) {
        $mysqli->query("
            UPDATE tbl_paa_runs
            SET status='done',
                phase='done',
                finished_at_utc=UTC_TIMESTAMP(),
                total_items=0,
                processed_items=0,
                updated_at=UTC_TIMESTAMP()
            WHERE id=" . (int) $runId
        );

        logg("Run #{$runId} created with 0 snapshot items. Marked done immediately. mode={$ITEM_SOURCE_MODE}");
    } else {
        logg("Run #{$runId} snapshot created. mode={$ITEM_SOURCE_MODE} total_items={$totalItems}");
    }

    return $runId;
}

function finalize_run_done($mysqli, $runId)
{
    $cntRes = $mysqli->query("
        SELECT
            SUM(status IN ('pending','processing')) AS open_count,
            SUM(status='success' AND restored_at_utc IS NOT NULL) AS restored_success_count,
            SUM(status='failed') AS failed_count,
            SUM(status='skipped') AS skipped_count
        FROM tbl_paa_run_items
        WHERE run_id=" . (int) $runId
    );

    $cnt = $cntRes ? $cntRes->fetch_assoc() : [];
    $openCount = (int) ($cnt['open_count'] ?? 0);

    if ($openCount !== 0) {
        return false;
    }

    $successCount = (int) ($cnt['restored_success_count'] ?? 0);
    $failedCount = (int) ($cnt['failed_count'] ?? 0);
    $skippedCount = (int) ($cnt['skipped_count'] ?? 0);
    $processedCount = $successCount + $failedCount + $skippedCount;

    $mysqli->query("
        UPDATE tbl_paa_runs
        SET status='done',
            phase='done',
            finished_at_utc=UTC_TIMESTAMP(),
            success_items={$successCount},
            failed_items={$failedCount},
            skipped_items={$skippedCount},
            processed_items={$processedCount},
            updated_at=UTC_TIMESTAMP()
        WHERE id=" . (int) $runId
    );

    return true;
}

function move_run_to_restore_phase($mysqli, $runId)
{
    $stmt = $mysqli->prepare("
        UPDATE tbl_paa_runs
        SET phase='restore',
            status='running',
            updated_at=UTC_TIMESTAMP()
        WHERE id=?
    ");
    $stmt->bind_param('i', $runId);
    $stmt->execute();
    $stmt->close();
}

function claim_adjust_batch($mysqli, $runId, $batchSize, $maxAttempts)
{
    $mysqli->begin_transaction();

    $sql = "
        SELECT id, msku, sku, storename, fnskuid, attempts
        FROM tbl_paa_run_items
        WHERE run_id=?
          AND status='pending'
          AND attempts < ?
        ORDER BY id ASC
        LIMIT {$batchSize}
        FOR UPDATE
    ";

    $stmt = $mysqli->prepare($sql);
    $stmt->bind_param('ii', $runId, $maxAttempts);
    $stmt->execute();
    $res = $stmt->get_result();

    $batch = [];
    while ($r = $res->fetch_assoc()) {
        $batch[] = $r;
    }

    $stmt->close();

    if (!count($batch)) {
        $mysqli->commit();
        return [];
    }

    $ids = array_map(fn($x) => (int) $x['id'], $batch);
    $idList = implode(',', $ids);

    $upd = "
        UPDATE tbl_paa_run_items
        SET status='processing',
            attempts=attempts+1,
            updated_at=UTC_TIMESTAMP()
        WHERE id IN ({$idList})
    ";

    if (!$mysqli->query($upd)) {
        $mysqli->rollback();
        throw new Exception("Claim adjust batch failed: " . $mysqli->error);
    }

    $mysqli->commit();

    return $batch;
}

function claim_restore_batch($mysqli, $runId, $batchSize, $maxAttempts)
{
    $mysqli->begin_transaction();

    $sql = "
        SELECT id, msku, sku, storename, fnskuid, attempts, original_price
        FROM tbl_paa_run_items
        WHERE run_id=?
          AND adjusted_at_utc IS NOT NULL
          AND restored_at_utc IS NULL
          AND status='success'
          AND attempts < ?
        ORDER BY id ASC
        LIMIT {$batchSize}
        FOR UPDATE
    ";

    $stmt = $mysqli->prepare($sql);
    $stmt->bind_param('ii', $runId, $maxAttempts);
    $stmt->execute();
    $res = $stmt->get_result();

    $batch = [];
    while ($r = $res->fetch_assoc()) {
        $batch[] = $r;
    }

    $stmt->close();

    if (!count($batch)) {
        $mysqli->commit();
        return [];
    }

    $ids = array_map(fn($x) => (int) $x['id'], $batch);
    $idList = implode(',', $ids);

    $upd = "
        UPDATE tbl_paa_run_items
        SET status='processing',
            attempts=attempts+1,
            updated_at=UTC_TIMESTAMP()
        WHERE id IN ({$idList})
    ";

    if (!$mysqli->query($upd)) {
        $mysqli->rollback();
        throw new Exception("Claim restore batch failed: " . $mysqli->error);
    }

    $mysqli->commit();

    return $batch;
}

function count_restore_remaining($mysqli, $runId)
{
    $res = $mysqli->query("
        SELECT COUNT(*) AS c
        FROM tbl_paa_run_items
        WHERE run_id=" . (int) $runId . "
          AND adjusted_at_utc IS NOT NULL
          AND restored_at_utc IS NULL
    ");
    $row = $res ? $res->fetch_assoc() : ['c' => 0];
    return (int) ($row['c'] ?? 0);
}

function mark_item_failed_or_retry($mysqli, $runItemId, $msg, $maxAttempts)
{
    $stmt = $mysqli->prepare("
        SELECT attempts
        FROM tbl_paa_run_items
        WHERE id=?
        LIMIT 1
    ");
    $stmt->bind_param('i', $runItemId);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    $attempts = (int) ($row['attempts'] ?? 0);
    $finalStatus = ($attempts >= $maxAttempts) ? 'failed' : 'pending';

    $stmt = $mysqli->prepare("
        UPDATE tbl_paa_run_items
        SET status=?,
            last_error=?,
            updated_at=UTC_TIMESTAMP()
        WHERE id=?
    ");
    $stmt->bind_param('ssi', $finalStatus, $msg, $runItemId);
    $stmt->execute();
    $stmt->close();

    return $finalStatus;
}

// ----------------------------------------------------
// PROCESSING
// ----------------------------------------------------

function process_adjust_phase($mysqli, $automation, $run, $currentHHMM, $batchSize, $maxAttempts)
{
    $automationId = (int) $automation['id'];
    $runId = (int) $run['id'];
    $marketplaceIds = safe_json_array($automation['marketplace_ids'] ?? '[]');
    $rules = safe_json_array($automation['rules_json'] ?? '[]');
    $defaultDelta = isset($automation['default_delta']) ? (float) $automation['default_delta'] : 0.0;

    $windowStart = (string) $run['window_start'];
    $windowEnd = (string) $run['window_end'];

    if (!is_time_in_window($currentHHMM, $windowStart, $windowEnd)) {
        logg("Run #{$runId} window {$windowStart}-{$windowEnd} ended. Switching to restore.");
        move_run_to_restore_phase($mysqli, $runId);
        return;
    }

    $rulesForWindow = [];
    foreach ($rules as $r) {
        if (!is_array($r)) {
            continue;
        }

        if (
            trim((string) ($r['start'] ?? '')) === $windowStart &&
            trim((string) ($r['end'] ?? '')) === $windowEnd
        ) {
            $rulesForWindow[] = $r;
        }
    }

    $batch = claim_adjust_batch($mysqli, $runId, $batchSize, $maxAttempts);

    if (!count($batch)) {
        logg("Run #{$runId} adjust phase has no claimable batch.");
        return;
    }

    $groupedBatch = group_batch_by_store($batch);

    foreach ($groupedBatch as $itemStore => $storeItems) {
        logg("Run #{$runId} adjust store={$itemStore} claimed " . count($storeItems) . " item(s)");

        foreach ($storeItems as $it) {
            $runItemId = (int) $it['id'];
            $msku = (string) $it['msku'];
            $sku = $it['sku'] !== null ? (string) $it['sku'] : null;
            $storename = normalize_store($it['storename'] ?? '');

            try {
                if ($storename === '' || $storename === '__UNKNOWN__') {
                    $stmt = $mysqli->prepare("
                        UPDATE tbl_paa_run_items
                        SET status='skipped',
                            last_error=?,
                            updated_at=UTC_TIMESTAMP()
                        WHERE id=?
                    ");
                    $err = "Missing storename for run item";
                    $stmt->bind_param('si', $err, $runItemId);
                    $stmt->execute();
                    $stmt->close();

                    logg("Run item #{$runItemId} skipped in adjust: {$err}");
                    continue;
                }

                if (!$sku) {
                    $sku = resolveSkuFromMsku($mysqli, $msku);

                    if ($sku) {
                        syncResolvedSku($mysqli, $automationId, $runItemId, $msku, $storename, $sku);
                    }
                }

                if (!$sku) {
                    $stmt = $mysqli->prepare("
                        UPDATE tbl_paa_run_items
                        SET status='skipped',
                            last_error=?,
                            updated_at=UTC_TIMESTAMP()
                        WHERE id=?
                    ");
                    $err = "No SKU resolved for MSKU";
                    $stmt->bind_param('si', $err, $runItemId);
                    $stmt->execute();
                    $stmt->close();

                    logg("Run item #{$runItemId} skipped in adjust: {$err}");
                    continue;
                }

                $currentPrice = fetchCurrentPrice($storename, $sku, $marketplaceIds);

                if ($currentPrice === null) {
                    $stmt = $mysqli->prepare("
                        UPDATE tbl_paa_run_items
                        SET status='skipped',
                            last_error=?,
                            updated_at=UTC_TIMESTAMP()
                        WHERE id=?
                    ");
                    $err = "No current price found";
                    $stmt->bind_param('si', $err, $runItemId);
                    $stmt->execute();
                    $stmt->close();

                    logg("Run item #{$runItemId} skipped in adjust: {$err}");
                    continue;
                }

                $deltaToApply = pick_delta_from_rules($currentPrice, $rulesForWindow, $defaultDelta);
                $newPrice = (float) $currentPrice + (float) $deltaToApply;

                if ($newPrice < 0) {
                    $newPrice = 0;
                }

                patchPrice($storename, $sku, $newPrice, 'USD', $marketplaceIds);

                $stmt = $mysqli->prepare("
                    UPDATE tbl_paa_run_items
                    SET status='success',
                        current_price=?,
                        original_price=IFNULL(original_price, ?),
                        new_price=?,
                        processed_at_utc=UTC_TIMESTAMP(),
                        adjusted_at_utc=IFNULL(adjusted_at_utc, UTC_TIMESTAMP()),
                        last_error=NULL,
                        updated_at=UTC_TIMESTAMP()
                    WHERE id=?
                ");
                $stmt->bind_param('dddi', $currentPrice, $currentPrice, $newPrice, $runItemId);
                $stmt->execute();
                $stmt->close();

                logg("Run item #{$runItemId} adjust success: store={$storename} SKU={$sku} current={$currentPrice} delta={$deltaToApply} new={$newPrice}");

            } catch (Exception $e) {
                $msg = substr($e->getMessage(), 0, 2000);
                $finalStatus = mark_item_failed_or_retry($mysqli, $runItemId, $msg, $maxAttempts);
                logg("Run item #{$runItemId} adjust {$finalStatus}: store={$storename} msg={$msg}");
            }
        }
    }
}

function process_restore_phase($mysqli, $automation, $run, $batchSize, $maxAttempts)
{
    $automationId = (int) $automation['id'];
    $runId = (int) $run['id'];
    $marketplaceIds = safe_json_array($automation['marketplace_ids'] ?? '[]');

    $batch = claim_restore_batch($mysqli, $runId, $batchSize, $maxAttempts);

    if (!count($batch)) {
        $remaining = count_restore_remaining($mysqli, $runId);

        if ($remaining === 0) {
            $done = finalize_run_done($mysqli, $runId);

            if ($done) {
                $stmt = $mysqli->prepare("
                    UPDATE tbl_paa_automations
                    SET last_run_at_utc=UTC_TIMESTAMP(),
                        updated_at=UTC_TIMESTAMP()
                    WHERE id=?
                ");
                $stmt->bind_param('i', $automationId);
                $stmt->execute();
                $stmt->close();

                logg("Run #{$runId} restore completed. Run marked done.");
            }
        } else {
            logg("Run #{$runId} restore phase has no claimable batch, remaining={$remaining}");
        }

        return;
    }

    $groupedBatch = group_batch_by_store($batch);

    foreach ($groupedBatch as $itemStore => $storeItems) {
        logg("Run #{$runId} restore store={$itemStore} claimed " . count($storeItems) . " item(s)");

        foreach ($storeItems as $it) {
            $runItemId = (int) $it['id'];
            $msku = (string) $it['msku'];
            $sku = $it['sku'] !== null ? (string) $it['sku'] : null;
            $storename = normalize_store($it['storename'] ?? '');
            $originalPrice = isset($it['original_price']) ? (float) $it['original_price'] : null;

            try {
                if ($storename === '' || $storename === '__UNKNOWN__') {
                    $stmt = $mysqli->prepare("
                        UPDATE tbl_paa_run_items
                        SET status='skipped',
                            last_error=?,
                            updated_at=UTC_TIMESTAMP()
                        WHERE id=?
                    ");
                    $err = "Missing storename for run item during restore";
                    $stmt->bind_param('si', $err, $runItemId);
                    $stmt->execute();
                    $stmt->close();

                    logg("Run item #{$runItemId} skipped in restore: {$err}");
                    continue;
                }

                if (!$sku) {
                    $sku = resolveSkuFromMsku($mysqli, $msku);

                    if ($sku) {
                        syncResolvedSku($mysqli, $automationId, $runItemId, $msku, $storename, $sku);
                    }
                }

                if (!$sku) {
                    $stmt = $mysqli->prepare("
                        UPDATE tbl_paa_run_items
                        SET status='skipped',
                            last_error=?,
                            updated_at=UTC_TIMESTAMP()
                        WHERE id=?
                    ");
                    $err = "No SKU resolved for MSKU during restore";
                    $stmt->bind_param('si', $err, $runItemId);
                    $stmt->execute();
                    $stmt->close();

                    logg("Run item #{$runItemId} skipped in restore: {$err}");
                    continue;
                }

                if ($originalPrice === null) {
                    $stmt = $mysqli->prepare("
                        UPDATE tbl_paa_run_items
                        SET status='skipped',
                            last_error=?,
                            updated_at=UTC_TIMESTAMP()
                        WHERE id=?
                    ");
                    $err = "No original price available for restore";
                    $stmt->bind_param('si', $err, $runItemId);
                    $stmt->execute();
                    $stmt->close();

                    logg("Run item #{$runItemId} skipped in restore: {$err}");
                    continue;
                }

                patchPrice($storename, $sku, $originalPrice, 'USD', $marketplaceIds);

                $stmt = $mysqli->prepare("
                    UPDATE tbl_paa_run_items
                    SET status='success',
                        new_price=?,
                        restored_at_utc=UTC_TIMESTAMP(),
                        last_error=NULL,
                        updated_at=UTC_TIMESTAMP()
                    WHERE id=?
                ");
                $stmt->bind_param('di', $originalPrice, $runItemId);
                $stmt->execute();
                $stmt->close();

                logg("Run item #{$runItemId} restore success: store={$storename} SKU={$sku} restore_to={$originalPrice}");

            } catch (Exception $e) {
                $msg = substr($e->getMessage(), 0, 2000);

                $stmt = $mysqli->prepare("
                    SELECT attempts
                    FROM tbl_paa_run_items
                    WHERE id=?
                    LIMIT 1
                ");
                $stmt->bind_param('i', $runItemId);
                $stmt->execute();
                $row = $stmt->get_result()->fetch_assoc();
                $stmt->close();

                $attempts = (int) ($row['attempts'] ?? 0);
                $finalStatus = ($attempts >= $maxAttempts) ? 'failed' : 'success';

                $stmt = $mysqli->prepare("
                    UPDATE tbl_paa_run_items
                    SET status=?,
                        last_error=?,
                        updated_at=UTC_TIMESTAMP()
                    WHERE id=?
                ");
                $stmt->bind_param('ssi', $finalStatus, $msg, $runItemId);
                $stmt->execute();
                $stmt->close();

                logg("Run item #{$runItemId} restore {$finalStatus}: store={$storename} msg={$msg}");
            }
        }
    }
}
