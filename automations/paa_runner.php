<?php
/**
 * PAA Runner (Native PHP)
 *
 * cPanel cron example:
 * php /home/USER/public_html/laravel_ims/cron/paa_runner.php
 *
 * Requires tables:
 *  - tbl_paa_automations
 *  - tbl_paa_automation_items
 *  - tbl_paa_runs
 *  - tbl_paa_run_items
 *
 * Notes:
 * - Supports new automation model:
 *      triggers_json => ["09:00","14:00","18:00"]
 *      rules_json => [
 *          {"start":"09:00","end":"10:00","min":200,"max":400,"delta":50},
 *          {"start":"10:00","end":"11:00","min":100,"max":200,"delta":-50}
 *      ]
 *      default_delta => 0
 * - Keeps legacy fallback support for:
 *      time_local
 *      delta
 *      frequency
 */

date_default_timezone_set('UTC');

// ----------------------------------------------------
// CONFIG
// ----------------------------------------------------
$BATCH_SIZE = 10;
$MAX_ATTEMPTS = 3;
$PROCESSING_TIMEOUT_MIN = 15;
$LOG_PREFIX = '[PAA] ';
$LARAVEL_ROOT = realpath(__DIR__ . '/..');

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
    $user = envv('DB_USERNAME', '');
    $pass = envv('DB_PASSWORD', '');
    $name = envv('DB_DATABASE', '');

    $mysqli = new mysqli($host, $user, $pass, $name);

    if ($mysqli->connect_error) {
        echo "DB CONNECT ERROR: " . $mysqli->connect_error . PHP_EOL;
        exit(1);
    }

    $mysqli->set_charset('utf8mb4');

    return $mysqli;
}

function nowUtcStr()
{
    return gmdate('Y-m-d H:i:s');
}

function logg($msg)
{
    global $LOG_PREFIX;
    echo $LOG_PREFIX . $msg . PHP_EOL;
}

// ----------------------------------------------------
// JSON / TIME / RULE HELPERS
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

function normalize_triggers($triggers)
{
    $out = [];

    foreach ((array) $triggers as $t) {
        $t = trim((string) $t);

        if ($t === '') {
            continue;
        }

        if (!preg_match('/^\d{2}:\d{2}$/', $t)) {
            continue;
        }

        $out[$t] = true;
    }

    $out = array_keys($out);
    sort($out);

    return $out;
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

    // normal same-day window
    if ($start < $end) {
        return $current >= $start && $current < $end;
    }

    // overnight window
    return $current >= $start || $current < $end;
}

function computeScheduledLocalHHMM($scheduledForUtc, $tzLocal = 'America/Los_Angeles')
{
    if (!$scheduledForUtc) {
        return null;
    }

    try {
        $dtUtc = new DateTime($scheduledForUtc, new DateTimeZone('UTC'));
        $dtUtc->setTimezone(new DateTimeZone($tzLocal ?: 'America/Los_Angeles'));
        return $dtUtc->format('H:i');
    } catch (Exception $e) {
        return null;
    }
}

/**
 * Compute next run UTC from multiple local triggers.
 * Schedules strictly AFTER $afterUtcStr if provided.
 */
function computeNextRunUtcFromTriggers(array $triggersHHMM, $tzLocal = 'America/Los_Angeles', $afterUtcStr = null)
{
    $triggersHHMM = normalize_triggers($triggersHHMM);

    if (!count($triggersHHMM)) {
        return null;
    }

    $tz = new DateTimeZone($tzLocal ?: 'America/Los_Angeles');
    $utcTz = new DateTimeZone('UTC');

    if ($afterUtcStr) {
        $baseUtc = new DateTime($afterUtcStr, $utcTz);
        $baseLocal = (clone $baseUtc)->setTimezone($tz);
    } else {
        $baseLocal = new DateTime('now', $tz);
    }

    $best = null;

    foreach ($triggersHHMM as $t) {
        [$hh, $mm] = array_map('intval', explode(':', $t));

        $cand = new DateTime($baseLocal->format('Y-m-d') . ' 00:00:00', $tz);
        $cand->setTime($hh, $mm, 0);

        if ($cand <= $baseLocal) {
            $cand->modify('+1 day');
        }

        if ($best === null || $cand < $best) {
            $best = $cand;
        }
    }

    if ($best === null) {
        return null;
    }

    return $best->setTimezone($utcTz)->format('Y-m-d H:i:s');
}

/**
 * Rules: first matching time window + price band wins.
 * - time window uses scheduled local HH:mm for the run
 * - price band uses min <= price < max
 */
function pickDeltaFromRules($currentPrice, array $rules, $defaultDelta = 0.0, $scheduledLocalHHMM = null)
{
    $p = (float) $currentPrice;

    foreach ($rules as $r) {
        if (!is_array($r)) {
            continue;
        }

        if (!isset($r['start'], $r['end'], $r['min'], $r['max'], $r['delta'])) {
            continue;
        }

        $start = trim((string) $r['start']);
        $end = trim((string) $r['end']);
        $min = is_numeric($r['min']) ? (float) $r['min'] : null;
        $max = is_numeric($r['max']) ? (float) $r['max'] : null;
        $delta = is_numeric($r['delta']) ? (float) $r['delta'] : null;

        if ($min === null || $max === null || $delta === null) {
            continue;
        }

        if ($scheduledLocalHHMM !== null && !is_time_in_window($scheduledLocalHHMM, $start, $end)) {
            continue;
        }

        if ($min <= $p && $p < $max) {
            return $delta;
        }
    }

    return (float) $defaultDelta;
}

// ----------------------------------------------------
// HTTP / AMAZON HOOKS
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
    $cronKey = envv('CRON_KEY');

    if (!$base) {
        throw new Exception("APP_URL missing in .env");
    }

    if (!$cronKey) {
        throw new Exception("CRON_KEY missing in .env");
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

    $res = http_post_json($url, $payload, [
        'X-CRON-KEY: ' . $cronKey,
    ], 50);

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
    $cronKey = envv('CRON_KEY');

    if (!$base) {
        throw new Exception("APP_URL missing in .env");
    }

    if (!$cronKey) {
        throw new Exception("CRON_KEY missing in .env");
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

    $res = http_post_json($url, $payload, [
        'X-CRON-KEY: ' . $cronKey,
    ], 50);

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
    $stmt = $mysqli->prepare("SELECT MSKU, SKU FROM tblproduct WHERE MSKU=? LIMIT 1");

    if (!$stmt) {
        return null;
    }

    $stmt->bind_param('s', $msku);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!empty($row['SKU'])) {
        return (string) $row['SKU'];
    }

    return null;
}

function syncResolvedSku($mysqli, $automationId, $runItemId, $msku, $sku)
{
    $stmt = $mysqli->prepare("
        UPDATE tbl_paa_automation_items
        SET sku=?, updated_at=UTC_TIMESTAMP()
        WHERE automation_id=? AND msku=?
    ");
    $stmt->bind_param('sis', $sku, $automationId, $msku);
    $stmt->execute();
    $stmt->close();

    $stmt = $mysqli->prepare("
        UPDATE tbl_paa_run_items
        SET sku=?, updated_at=UTC_TIMESTAMP()
        WHERE id=?
    ");
    $stmt->bind_param('si', $sku, $runItemId);
    $stmt->execute();
    $stmt->close();
}

function finalizeRunIfComplete($mysqli, $runId)
{
    $cntRes = $mysqli->query("
        SELECT
            SUM(status IN ('pending','processing')) AS open_count,
            SUM(status='success') AS success_count,
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

    $successCount = (int) ($cnt['success_count'] ?? 0);
    $failedCount = (int) ($cnt['failed_count'] ?? 0);
    $skippedCount = (int) ($cnt['skipped_count'] ?? 0);
    $processedCount = $successCount + $failedCount + $skippedCount;

    $mysqli->query("
        UPDATE tbl_paa_runs
        SET status='done',
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

function scheduleNextAutomationRun($mysqli, $automationId, $triggers, $tz, $scheduledForUtc, $isEnabled = true)
{
    if (!$isEnabled) {
        return;
    }

    $next = computeNextRunUtcFromTriggers($triggers, $tz, $scheduledForUtc);

    $stmt = $mysqli->prepare("
        UPDATE tbl_paa_automations
        SET last_run_at_utc=UTC_TIMESTAMP(),
            next_run_at_utc=?,
            updated_at=UTC_TIMESTAMP()
        WHERE id=?
    ");
    $stmt->bind_param('si', $next, $automationId);
    $stmt->execute();
    $stmt->close();

    logg("Scheduled next_run_at_utc={$next} for automation #{$automationId}");
}

function seedMissingNextRuns($mysqli)
{
    $sql = "
        SELECT id, timezone, triggers_json, time_local
        FROM tbl_paa_automations
        WHERE is_enabled=1
          AND next_run_at_utc IS NULL
    ";

    $res = $mysqli->query($sql);

    if (!$res) {
        logg("Seed query error: " . $mysqli->error);
        return;
    }

    while ($row = $res->fetch_assoc()) {
        $id = (int) $row['id'];
        $tz = $row['timezone'] ?: 'America/Los_Angeles';

        $triggers = normalize_triggers(safe_json_array($row['triggers_json'] ?? null));

        if (!count($triggers) && !empty($row['time_local'])) {
            $triggers = normalize_triggers([$row['time_local']]);
        }

        if (!count($triggers)) {
            logg("Skipping seed for automation #{$id}: no valid triggers");
            continue;
        }

        $next = computeNextRunUtcFromTriggers($triggers, $tz);

        if (!$next) {
            logg("Skipping seed for automation #{$id}: unable to compute next run");
            continue;
        }

        $stmt = $mysqli->prepare("
            UPDATE tbl_paa_automations
            SET next_run_at_utc=?,
                updated_at=UTC_TIMESTAMP()
            WHERE id=?
        ");
        $stmt->bind_param('si', $next, $id);
        $stmt->execute();
        $stmt->close();

        logg("Seeded next_run_at_utc for automation #{$id}: {$next}");
    }

    $res->free();
}

// ----------------------------------------------------
// MAIN
// ----------------------------------------------------

$mysqli = db();

// 0) Reset stuck processing items
{
    global $PROCESSING_TIMEOUT_MIN, $MAX_ATTEMPTS;

    $sql = "
        UPDATE tbl_paa_run_items
        SET status='pending',
            last_error=CONCAT(IFNULL(last_error,''), ' | reset stuck processing'),
            updated_at=UTC_TIMESTAMP()
        WHERE status='processing'
          AND updated_at < (UTC_TIMESTAMP() - INTERVAL ? MINUTE)
          AND attempts < ?
    ";

    $stmt = $mysqli->prepare($sql);
    $stmt->bind_param('ii', $PROCESSING_TIMEOUT_MIN, $MAX_ATTEMPTS);
    $stmt->execute();
    $affected = $stmt->affected_rows;
    $stmt->close();

    if ($affected > 0) {
        logg("Reset stuck processing items: {$affected}");
    }
}

// 1) Seed next_run_at_utc for enabled automations missing schedule
seedMissingNextRuns($mysqli);

// 2) Find due automations
$dueSql = "
    SELECT *
    FROM tbl_paa_automations
    WHERE is_enabled=1
      AND next_run_at_utc IS NOT NULL
      AND next_run_at_utc <= UTC_TIMESTAMP()
    ORDER BY next_run_at_utc ASC
";

$dueRes = $mysqli->query($dueSql);

if (!$dueRes) {
    logg("Query error (due automations): " . $mysqli->error);
    exit(1);
}

$dueAutomations = fetch_all_assoc($dueRes);
$dueRes->free();

if (!count($dueAutomations)) {
    logg("No due automations.");
    exit(0);
}

logg("Due automations: " . count($dueAutomations));

foreach ($dueAutomations as $a) {
    $automationId = (int) $a['id'];
    $store = (string) $a['store'];
    $tz = $a['timezone'] ?: 'America/Los_Angeles';
    $marketplaceIds = safe_json_array($a['marketplace_ids'] ?? '[]');
    $scheduledForUtc = $a['next_run_at_utc'];

    $triggers = normalize_triggers(safe_json_array($a['triggers_json'] ?? null));
    if (!count($triggers) && !empty($a['time_local'])) {
        $triggers = normalize_triggers([$a['time_local']]);
    }

    $rules = safe_json_array($a['rules_json'] ?? null);
    $defaultDelta = isset($a['default_delta']) ? (float) $a['default_delta'] : 0.0;

    if (!isset($a['default_delta']) && isset($a['delta'])) {
        $defaultDelta = (float) $a['delta'];
    }

    $scheduledLocalHHMM = computeScheduledLocalHHMM($scheduledForUtc, $tz);

    logg(
        "Automation #{$automationId} store={$store} scheduled_for_utc={$scheduledForUtc} " .
        "scheduled_local={$scheduledLocalHHMM} triggers=" . json_encode($triggers) .
        " default_delta={$defaultDelta}"
    );

    // 3) Create or get run row
    $runId = null;

    $stmt = $mysqli->prepare("
        SELECT id, status
        FROM tbl_paa_runs
        WHERE automation_id=? AND scheduled_for_utc=?
        LIMIT 1
    ");
    $stmt->bind_param('is', $automationId, $scheduledForUtc);
    $stmt->execute();
    $runRes = $stmt->get_result();
    $runRow = $runRes->fetch_assoc();
    $stmt->close();

    if ($runRow) {
        $runId = (int) $runRow['id'];
        logg("Found existing run #{$runId} status={$runRow['status']}");
    } else {
        $stmt = $mysqli->prepare("
            INSERT INTO tbl_paa_runs
                (automation_id, scheduled_for_utc, status, started_at_utc, created_at, updated_at)
            VALUES
                (?, ?, 'running', UTC_TIMESTAMP(), UTC_TIMESTAMP(), UTC_TIMESTAMP())
        ");
        $stmt->bind_param('is', $automationId, $scheduledForUtc);

        if (!$stmt->execute()) {
            logg("Failed to insert run for automation #{$automationId}: " . $stmt->error);
            $stmt->close();
            continue;
        }

        $runId = (int) $stmt->insert_id;
        $stmt->close();

        logg("Created run #{$runId}");

        $items = [];
        $stmt = $mysqli->prepare("
            SELECT id, msku, sku
            FROM tbl_paa_automation_items
            WHERE automation_id=? AND is_active=1
        ");
        $stmt->bind_param('i', $automationId);
        $stmt->execute();
        $res = $stmt->get_result();

        while ($r = $res->fetch_assoc()) {
            $items[] = $r;
        }

        $stmt->close();

        $totalItems = count($items);

        if ($totalItems === 0) {
            logg("Automation #{$automationId} has no active items. Marking run done.");

            $mysqli->query("
                UPDATE tbl_paa_runs
                SET status='done',
                    finished_at_utc=UTC_TIMESTAMP(),
                    updated_at=UTC_TIMESTAMP()
                WHERE id=" . (int) $runId
            );

            scheduleNextAutomationRun($mysqli, $automationId, $triggers, $tz, $scheduledForUtc, true);
            continue;
        }

        $ins = $mysqli->prepare("
            INSERT INTO tbl_paa_run_items
                (run_id, automation_item_id, msku, sku, status, attempts, created_at, updated_at)
            VALUES
                (?, ?, ?, ?, 'pending', 0, UTC_TIMESTAMP(), UTC_TIMESTAMP())
        ");

        foreach ($items as $it) {
            $automationItemId = (int) $it['id'];
            $msku = (string) $it['msku'];
            $sku = $it['sku'] !== null ? (string) $it['sku'] : null;

            $ins->bind_param('iiss', $runId, $automationItemId, $msku, $sku);

            if (!$ins->execute()) {
                logg("Run item insert failed for MSKU {$msku}: " . $ins->error);
            }
        }

        $ins->close();

        $stmt = $mysqli->prepare("
            UPDATE tbl_paa_runs
            SET total_items=?,
                updated_at=UTC_TIMESTAMP()
            WHERE id=?
        ");
        $stmt->bind_param('ii', $totalItems, $runId);
        $stmt->execute();
        $stmt->close();

        logg("Snapshot created for run #{$runId}: {$totalItems} item(s)");
    }

    // 4) Claim a batch
    $mysqli->begin_transaction();

    $claimSql = "
        SELECT id, msku, sku, attempts
        FROM tbl_paa_run_items
        WHERE run_id=?
          AND status='pending'
          AND attempts < ?
        ORDER BY id ASC
        LIMIT {$BATCH_SIZE}
        FOR UPDATE
    ";

    $stmt = $mysqli->prepare($claimSql);
    $stmt->bind_param('ii', $runId, $MAX_ATTEMPTS);
    $stmt->execute();
    $res = $stmt->get_result();

    $batch = [];
    while ($r = $res->fetch_assoc()) {
        $batch[] = $r;
    }

    $stmt->close();

    if (!count($batch)) {
        $mysqli->commit();

        $done = finalizeRunIfComplete($mysqli, $runId);

        if ($done) {
            logg("Run #{$runId} completed. Finalizing automation schedule.");
            scheduleNextAutomationRun($mysqli, $automationId, $triggers, $tz, $scheduledForUtc, true);
        } else {
            logg("Run #{$runId} has no pending batch this tick but still has open items.");
        }

        continue;
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
        logg("Claim update failed for run #{$runId}: " . $mysqli->error);
        $mysqli->rollback();
        continue;
    }

    $mysqli->commit();

    logg("Claimed batch for run #{$runId}: " . count($batch) . " item(s)");

    // 5) Process claimed items
    foreach ($batch as $it) {
        $runItemId = (int) $it['id'];
        $msku = (string) $it['msku'];
        $sku = $it['sku'] !== null ? (string) $it['sku'] : null;

        try {
            if (!$sku) {
                $sku = resolveSkuFromMsku($mysqli, $msku);

                if ($sku) {
                    syncResolvedSku($mysqli, $automationId, $runItemId, $msku, $sku);
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

                logg("Run item #{$runItemId} skipped: {$err}");
                continue;
            }

            $currentPrice = fetchCurrentPrice($store, $sku, $marketplaceIds);

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

                logg("Run item #{$runItemId} skipped: {$err}");
                continue;
            }

            $deltaToApply = pickDeltaFromRules($currentPrice, $rules, $defaultDelta, $scheduledLocalHHMM);
            $newPrice = (float) $currentPrice + (float) $deltaToApply;

            if ($newPrice < 0) {
                $newPrice = 0;
            }

            patchPrice($store, $sku, $newPrice, 'USD', $marketplaceIds);

            $stmt = $mysqli->prepare("
                UPDATE tbl_paa_run_items
                SET status='success',
                    current_price=?,
                    new_price=?,
                    processed_at_utc=UTC_TIMESTAMP(),
                    last_error=NULL,
                    updated_at=UTC_TIMESTAMP()
                WHERE id=?
            ");
            $stmt->bind_param('ddi', $currentPrice, $newPrice, $runItemId);
            $stmt->execute();
            $stmt->close();

            logg("Run item #{$runItemId} success: SKU={$sku} current={$currentPrice} delta={$deltaToApply} new={$newPrice}");

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
            $attemptRow = $stmt->get_result()->fetch_assoc();
            $stmt->close();

            $attempts = (int) ($attemptRow['attempts'] ?? 0);
            $finalStatus = ($attempts >= $MAX_ATTEMPTS) ? 'failed' : 'pending';

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

            logg("Run item #{$runItemId} {$finalStatus}: {$msg}");
        }
    }

    logg("Batch processed for run #{$runId}");
}

logg("Done.");