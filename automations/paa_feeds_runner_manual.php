<?php
/**
 * PAA Feeds Runner (Manual Mode)
 *
 * Behavior
 * --------------------------------------------------
 * - Cron runs every 5 minutes.
 * - Uses the SAME window / restore flow as paa_runner.php.
 * - Source is MANUAL mode only:
 *      snapshots rows from tbl_paa_automation_items where automation_id=? and is_active=1
 * - Each manual item is validated against tblfnsku using:
 *      MSKU = automation item msku
 *      storename = automation item storename
 *      amzn_item_price IS NOT NULL AND amzn_item_price > 0
 * - During adjust:
 *      uses the frozen snapshot current_price/original_price from tbl_paa_run_items
 *      submits bulk price updates through JSON_LISTINGS_FEED
 * - During restore:
 *      restores the frozen original_price snapshot through JSON_LISTINGS_FEED
 *
 * Required app endpoint
 * --------------------------------------------------
 * POST /api/amazon/listings/submit-price-feed-sync
 *
 * Required tblfnsku field
 * --------------------------------------------------
 * ALTER TABLE tblfnsku
 * ADD COLUMN amzn_item_price DECIMAL(10,2) NULL AFTER amazon_status;
 *
 * Optional
 * --------------------------------------------------
 * ALTER TABLE tblfnsku
 * ADD COLUMN amzn_item_price_updated_at DATETIME NULL AFTER amzn_item_price;
 */

date_default_timezone_set('UTC');

// ----------------------------------------------------
// CONFIG
// ----------------------------------------------------
$BATCH_SIZE = 200;
$MAX_ATTEMPTS = 10;
$PROCESSING_TIMEOUT_MIN = 15;
$LOG_PREFIX = '[PAA-FEEDS-MANUAL] ';
$LARAVEL_ROOT = realpath(__DIR__ . '/..');

// Locked to manual mode for this version
$ITEM_SOURCE_MODE = 'manual';

// ----------------------------------------------------
// MAIN
// ----------------------------------------------------

try {
    $mysqli = db();

    reset_stuck_processing_items($mysqli, $PROCESSING_TIMEOUT_MIN, $MAX_ATTEMPTS);

    $automations = get_enabled_automations($mysqli);

    if (!count($automations)) {
        logg('No enabled automations.');
        exit(0);
    }

    logg('Enabled automations: ' . count($automations));

    foreach ($automations as $automation) {
        $automationId = (int) $automation['id'];
        $tz = $automation['timezone'] ?: 'America/Los_Angeles';
        $rules = safe_json_array($automation['rules_json'] ?? '[]');

        $currentHHMM = now_local_hhmm($tz);
        $activeWindow = first_active_window($rules, $currentHHMM);

        logg("Automation #{$automationId} mode=manual local_time={$currentHHMM}");

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

    logg('Done.');
} catch (Exception $e) {
    logg('FATAL: ' . $e->getMessage());
    exit(1);
}

// ----------------------------------------------------
// ENV / DB HELPERS
// ----------------------------------------------------

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

    $host = envv('DB_HOST', 'localhost');
    $port = (int) envv('DB_PORT', 3306);
    $user = envv('DB_USERNAME', 'imsv2_dbims_user');
    $pass = envv('DB_PASSWORD', 'Imsv2_dbims_user');
    $name = envv('DB_DATABASE', 'imsv2_dbims');

    $mysqli = new mysqli($host, $user, $pass, $name, $port);

    if ($mysqli->connect_error) {
        echo 'DB CONNECT ERROR: ' . $mysqli->connect_error . PHP_EOL;
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
// HTTP / AMAZON APP BRIDGE
// ----------------------------------------------------

function http_post_json($url, $payload, $headers = [], $timeout = 180)
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

function app_headers()
{
    $cronKey = envv('CRON_KEY', null);
    $headers = [];

    if ($cronKey) {
        $headers[] = 'X-CRON-KEY: ' . $cronKey;
    }

    return $headers;
}

function submitPriceFeedSync($store, $marketplaceIds, array $updates, $currency = 'USD')
{
    $base = rtrim((string) envv('APP_URL'), '/');

    if (!$base) {
        throw new Exception('APP_URL missing in .env');
    }

    if (!count($updates)) {
        throw new Exception('No feed updates provided');
    }

    $url = $base . '/api/amazon/listings/submit-price-feed-sync';

    $payload = [
        'store' => $store,
        'marketplaceIds' => $marketplaceIds ?: ['ATVPDKIKX0DER'],
        'currency' => $currency,
        'updates' => array_values($updates),
    ];

    $res = http_post_json($url, $payload, app_headers(), 240);

    if ($res['status'] < 200 || $res['status'] >= 300) {
        $msg =
            $res['json']['error']['errors'][0]['message'] ??
            $res['json']['error']['message'] ??
            $res['json']['message'] ??
            $res['raw'];

        throw new Exception("submit-price-feed-sync failed HTTP {$res['status']}: {$msg}");
    }

    $ok = (bool) ($res['json']['success'] ?? false);

    if (!$ok) {
        $msg = $res['json']['message'] ?? $res['raw'];
        throw new Exception("submit-price-feed-sync returned not-success: {$msg}");
    }

    $processingStatus = strtoupper((string) ($res['json']['processingStatus'] ?? 'DONE'));

    if (in_array($processingStatus, ['FATAL', 'CANCELLED'], true)) {
        $msg = $res['json']['message'] ?? ('Feed processing failed with status ' . $processingStatus);
        throw new Exception($msg);
    }

    return $res['json'];
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

function get_tblfnsku_cached_price_row($mysqli, $msku, $storename)
{
    $msku = trim((string) $msku);
    $storename = normalize_store($storename);

    if ($msku === '' || $storename === '') {
        return null;
    }

    $stmt = $mysqli->prepare("
        SELECT FNSKUID, MSKU, storename, amzn_item_price
        FROM tblfnsku
        WHERE MSKU=?
          AND storename=?
          AND amzn_item_price IS NOT NULL
          AND amzn_item_price > 0
        ORDER BY FNSKUID DESC
        LIMIT 1
    ");
    $stmt->bind_param('ss', $msku, $storename);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!$row) {
        return null;
    }

    return [
        'fnskuid' => isset($row['FNSKUID']) ? (int) $row['FNSKUID'] : null,
        'cached_price' => isset($row['amzn_item_price']) ? (float) $row['amzn_item_price'] : null,
    ];
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
            last_error=CONCAT(IFNULL(last_error,''), ' | reset stuck restore processing'),
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
        throw new Exception('Automation query failed: ' . $mysqli->error);
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

        $priceRow = get_tblfnsku_cached_price_row($mysqli, $msku, $storename);

        if (!$priceRow || !isset($priceRow['cached_price']) || (float) $priceRow['cached_price'] <= 0) {
            continue;
        }

        $items[] = [
            'automation_item_id' => (int) $r['id'],
            'msku' => $msku,
            'sku' => $r['sku'] !== null ? trim((string) $r['sku']) : null,
            'storename' => $storename,
            'fnskuid' => $priceRow['fnskuid'] ?? null,
            'cached_price' => (float) $priceRow['cached_price'],
        ];
    }

    $stmt->close();

    return $items;
}

function create_run_for_window($mysqli, $automationId, $windowStart, $windowEnd)
{
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

    $items = get_snapshot_items_manual($mysqli, $automationId);
    $totalItems = count($items);

    if ($totalItems > 0) {
        $ins = $mysqli->prepare("
            INSERT INTO tbl_paa_run_items
                (run_id, automation_item_id, msku, sku, storename, fnskuid, current_price, original_price, status, attempts, created_at, updated_at)
            VALUES
                (?, ?, ?, ?, ?, ?, ?, ?, 'pending', 0, UTC_TIMESTAMP(), UTC_TIMESTAMP())
        ");

        foreach ($items as $it) {
            $automationItemId = $it['automation_item_id'] !== null ? (int) $it['automation_item_id'] : null;
            $msku = (string) $it['msku'];
            $sku = $it['sku'] !== null ? (string) $it['sku'] : null;
            $storename = (string) $it['storename'];
            $fnskuid = $it['fnskuid'] !== null ? (int) $it['fnskuid'] : null;
            $cachedPrice = isset($it['cached_price']) ? (float) $it['cached_price'] : null;

            $ins->bind_param('iisssidd', $runId, $automationItemId, $msku, $sku, $storename, $fnskuid, $cachedPrice, $cachedPrice);
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

        logg("Run #{$runId} created with 0 snapshot items. Marked done immediately. mode=manual");
    } else {
        logg("Run #{$runId} snapshot created. mode=manual total_items={$totalItems}");
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
        SELECT id, msku, sku, storename, fnskuid, attempts, current_price, original_price
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
        throw new Exception('Claim adjust batch failed: ' . $mysqli->error);
    }

    $mysqli->commit();

    return $batch;
}

function claim_restore_batch($mysqli, $runId, $batchSize, $maxAttempts)
{
    $mysqli->begin_transaction();

    $sql = "
        SELECT id, msku, sku, storename, fnskuid, attempts, current_price, original_price
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
        throw new Exception('Claim restore batch failed: ' . $mysqli->error);
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

function skip_unadjusted_items_for_expired_run($mysqli, $runId)
{
    $stmt = $mysqli->prepare("
        UPDATE tbl_paa_run_items
        SET status='skipped',
            last_error='Skipped because adjust window expired before processing',
            updated_at=UTC_TIMESTAMP()
        WHERE run_id=?
          AND status IN ('pending','processing')
          AND adjusted_at_utc IS NULL
    ");
    $stmt->bind_param('i', $runId);
    $stmt->execute();
    $affected = $stmt->affected_rows;
    $stmt->close();

    if ($affected > 0) {
        logg("Run #{$runId} skipped expired unadjusted items: {$affected}");
    }

    return $affected;
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

function mark_items_submitted_success($mysqli, array $preparedRows, $feedId, $phase)
{
    foreach ($preparedRows as $row) {
        $runItemId = (int) $row['run_item_id'];
        $sku = (string) $row['sku'];
        $currentPrice = isset($row['current_price']) ? (float) $row['current_price'] : null;
        $originalPrice = isset($row['original_price']) ? (float) $row['original_price'] : null;
        $targetPrice = (float) $row['target_price'];

        if ($phase === 'adjust') {
            $stmt = $mysqli->prepare("
                UPDATE tbl_paa_run_items
                SET status='success',
                    current_price=?,
                    original_price=IFNULL(original_price, ?),
                    new_price=?,
                    processed_at_utc=UTC_TIMESTAMP(),
                    adjusted_at_utc=IFNULL(adjusted_at_utc, UTC_TIMESTAMP()),
                    last_error=?,
                    updated_at=UTC_TIMESTAMP()
                WHERE id=?
            ");
            $note = 'Feed applied: ' . $feedId . ' SKU=' . $sku;
            $stmt->bind_param('dddsi', $currentPrice, $originalPrice, $targetPrice, $note, $runItemId);
            $stmt->execute();
            $stmt->close();
        } else {
            $stmt = $mysqli->prepare("
                UPDATE tbl_paa_run_items
                SET status='success',
                    new_price=?,
                    restored_at_utc=UTC_TIMESTAMP(),
                    last_error=?,
                    updated_at=UTC_TIMESTAMP()
                WHERE id=?
            ");
            $note = 'Restore feed applied: ' . $feedId . ' SKU=' . $sku;
            $stmt->bind_param('dsi', $targetPrice, $note, $runItemId);
            $stmt->execute();
            $stmt->close();
        }
    }
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
        logg("Run #{$runId} window {$windowStart}-{$windowEnd} ended. Skipping unprocessed items and switching to restore.");
        skip_unadjusted_items_for_expired_run($mysqli, $runId);
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
        logg("Run #{$runId} adjust store={$itemStore} claimed " . count($storeItems) . ' item(s)');

        $preparedRows = [];

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
                    $err = 'Missing storename for run item';
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
                    $err = 'No SKU resolved for MSKU';
                    $stmt->bind_param('si', $err, $runItemId);
                    $stmt->execute();
                    $stmt->close();

                    logg("Run item #{$runItemId} skipped in adjust: {$err}");
                    continue;
                }

                $currentPrice = isset($it['current_price']) ? (float) $it['current_price'] : null;
                $originalPrice = isset($it['original_price']) ? (float) $it['original_price'] : null;

                if ($currentPrice === null || $currentPrice <= 0) {
                    $stmt = $mysqli->prepare("
                        UPDATE tbl_paa_run_items
                        SET status='skipped',
                            last_error=?,
                            updated_at=UTC_TIMESTAMP()
                        WHERE id=?
                    ");
                    $err = 'No snapshot current_price found';
                    $stmt->bind_param('si', $err, $runItemId);
                    $stmt->execute();
                    $stmt->close();

                    logg("Run item #{$runItemId} skipped in adjust: {$err}");
                    continue;
                }

                if ($originalPrice === null || $originalPrice <= 0) {
                    $originalPrice = $currentPrice;
                }

                $deltaToApply = pick_delta_from_rules($currentPrice, $rulesForWindow, $defaultDelta);
                $newPrice = (float) $currentPrice + (float) $deltaToApply;

                if ($newPrice < 0) {
                    $newPrice = 0;
                }

                $preparedRows[] = [
                    'run_item_id' => $runItemId,
                    'msku' => $msku,
                    'sku' => $sku,
                    'storename' => $storename,
                    'current_price' => (float) $currentPrice,
                    'original_price' => (float) $originalPrice,
                    'target_price' => round((float) $newPrice, 2),
                    'delta' => (float) $deltaToApply,
                ];
            } catch (Exception $e) {
                $msg = substr($e->getMessage(), 0, 2000);
                $finalStatus = mark_item_failed_or_retry($mysqli, $runItemId, $msg, $maxAttempts);
                logg("Run item #{$runItemId} adjust {$finalStatus}: store={$storename} msg={$msg}");
            }
        }

        if (!count($preparedRows)) {
            continue;
        }

        try {
            $feedUpdates = [];
            foreach ($preparedRows as $row) {
                $feedUpdates[] = [
                    'sku' => $row['sku'],
                    'price' => $row['target_price'],
                ];
            }

            $feedRes = submitPriceFeedSync($itemStore, $marketplaceIds, $feedUpdates, 'USD');
            $feedId = (string) ($feedRes['feedId'] ?? 'UNKNOWN');

            mark_items_submitted_success($mysqli, $preparedRows, $feedId, 'adjust');

            foreach ($preparedRows as $row) {
                logg(
                    'Run item #' . $row['run_item_id'] .
                    ' adjust success via feed: store=' . $itemStore .
                    ' SKU=' . $row['sku'] .
                    ' current=' . $row['current_price'] .
                    ' delta=' . $row['delta'] .
                    ' new=' . $row['target_price'] .
                    ' feedId=' . $feedId
                );
            }
        } catch (Exception $e) {
            $msg = substr($e->getMessage(), 0, 2000);

            foreach ($preparedRows as $row) {
                $finalStatus = mark_item_failed_or_retry($mysqli, (int) $row['run_item_id'], $msg, $maxAttempts);
                logg('Run item #' . $row['run_item_id'] . ' adjust ' . $finalStatus . ': store=' . $itemStore . ' msg=' . $msg);
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
            skip_unadjusted_items_for_expired_run($mysqli, $runId);
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
        logg("Run #{$runId} restore store={$itemStore} claimed " . count($storeItems) . ' item(s)');

        $preparedRows = [];

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
                    $err = 'Missing storename for run item during restore';
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
                    $err = 'No SKU resolved for MSKU during restore';
                    $stmt->bind_param('si', $err, $runItemId);
                    $stmt->execute();
                    $stmt->close();

                    logg("Run item #{$runItemId} skipped in restore: {$err}");
                    continue;
                }

                if ($originalPrice === null || $originalPrice <= 0) {
                    $stmt = $mysqli->prepare("
                        UPDATE tbl_paa_run_items
                        SET status='skipped',
                            last_error=?,
                            updated_at=UTC_TIMESTAMP()
                        WHERE id=?
                    ");
                    $err = 'No original price available for restore';
                    $stmt->bind_param('si', $err, $runItemId);
                    $stmt->execute();
                    $stmt->close();

                    logg("Run item #{$runItemId} skipped in restore: {$err}");
                    continue;
                }

                $preparedRows[] = [
                    'run_item_id' => $runItemId,
                    'msku' => $msku,
                    'sku' => $sku,
                    'storename' => $storename,
                    'original_price' => (float) $originalPrice,
                    'target_price' => round((float) $originalPrice, 2),
                ];
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

        if (!count($preparedRows)) {
            continue;
        }

        try {
            $feedUpdates = [];
            foreach ($preparedRows as $row) {
                $feedUpdates[] = [
                    'sku' => $row['sku'],
                    'price' => $row['target_price'],
                ];
            }

            $feedRes = submitPriceFeedSync($itemStore, $marketplaceIds, $feedUpdates, 'USD');
            $feedId = (string) ($feedRes['feedId'] ?? 'UNKNOWN');

            mark_items_submitted_success($mysqli, $preparedRows, $feedId, 'restore');

            foreach ($preparedRows as $row) {
                logg(
                    'Run item #' . $row['run_item_id'] .
                    ' restore success via feed: store=' . $itemStore .
                    ' SKU=' . $row['sku'] .
                    ' restore_to=' . $row['target_price'] .
                    ' feedId=' . $feedId
                );
            }
        } catch (Exception $e) {
            $msg = substr($e->getMessage(), 0, 2000);

            foreach ($preparedRows as $row) {
                $stmt = $mysqli->prepare("
                    SELECT attempts
                    FROM tbl_paa_run_items
                    WHERE id=?
                    LIMIT 1
                ");
                $runItemId = (int) $row['run_item_id'];
                $stmt->bind_param('i', $runItemId);
                $stmt->execute();
                $attemptRow = $stmt->get_result()->fetch_assoc();
                $stmt->close();

                $attempts = (int) ($attemptRow['attempts'] ?? 0);
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

                logg('Run item #' . $runItemId . ' restore ' . $finalStatus . ': store=' . $itemStore . ' msg=' . $msg);
            }
        }
    }
}
