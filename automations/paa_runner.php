<?php
/**
 * PAA Runner (Native PHP)
 * - Run via cPanel cron: 
 *
 * Requires tables:
 *  - tbl_paa_automations
 *  - tbl_paa_automation_items
 *  - tbl_paa_runs
 *  - tbl_paa_run_items
 */

date_default_timezone_set('UTC');

// ----------------------------------------------------
// CONFIG
// ----------------------------------------------------
$BATCH_SIZE = 10;                 // how many items per cron tick
$MAX_ATTEMPTS = 3;                // stop retrying after N attempts
$PROCESSING_TIMEOUT_MIN = 15;     // reset stuck "processing" after N minutes
$LOG_PREFIX = '[PAA] ';

// Laravel root (to read .env if you want)
$LARAVEL_ROOT = realpath(__DIR__ . '/..');

// ----------------------------------------------------
// ENV / DB HELPERS
// ----------------------------------------------------

function load_env($envPath)
{
    static $cache = null;
    if ($cache !== null)
        return $cache;

    $cache = [];
    if (!file_exists($envPath))
        return $cache;

    $lines = file($envPath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        $line = trim($line);
        if ($line === '' || str_starts_with($line, '#'))
            continue;
        if (!str_contains($line, '='))
            continue;

        [$k, $v] = explode('=', $line, 2);
        $k = trim($k);
        $v = trim($v);

        // strip quotes
        if ((str_starts_with($v, '"') && str_ends_with($v, '"')) || (str_starts_with($v, "'") && str_ends_with($v, "'"))) {
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
    if ($mysqli)
        return $mysqli;

    // Prefer Laravel .env values
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
// JSON helpers + rules + triggers scheduling
// ----------------------------------------------------

function safe_json_array($v)
{
    if ($v === null)
        return [];
    if (is_array($v))
        return $v;
    $s = trim((string) $v);
    if ($s === '')
        return [];
    $j = json_decode($s, true);
    return is_array($j) ? $j : [];
}

function normalize_triggers($triggers)
{
    $out = [];
    foreach ((array) $triggers as $t) {
        $t = trim((string) $t);
        if ($t === '')
            continue;
        if (!preg_match('/^\d{2}:\d{2}$/', $t))
            continue;
        $out[$t] = true;
    }
    $out = array_keys($out);
    sort($out); // HH:mm sorts correctly
    return $out;
}

/**
 * Compute next run UTC from multiple local triggers.
 * $afterUtcStr: schedule strictly AFTER this moment (use current run scheduled_for_utc)
 */

function computeNextRunUtcFromTriggers(array $triggersHHMM, $tzLocal = 'America/Los_Angeles', $afterUtcStr = null)
{
    $triggersHHMM = normalize_triggers($triggersHHMM);
    if (!count($triggersHHMM))
        return null;

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

        // candidate on the same local date as baseLocal
        $cand = new DateTime($baseLocal->format('Y-m-d') . ' 00:00:00', $tz);
        $cand->setTime($hh, $mm, 0);

        // must be strictly > baseLocal
        if ($cand <= $baseLocal) {
            $cand->modify('+1 day');
        }

        if ($best === null || $cand < $best) {
            $best = $cand;
        }
    }

    if ($best === null)
        return null;

    $bestUtc = (clone $best)->setTimezone($utcTz);
    return $bestUtc->format('Y-m-d H:i:s');
}

/**
 * Rules: first match wins: min <= price < max
 * rules_json example: [{min:200,max:400,delta:50}, ...]
 */

function pickDeltaFromRules($currentPrice, array $rules, $defaultDelta = 0.0)
{
    $p = (float) $currentPrice;

    // sort by min asc (safe even if already sorted)
    usort($rules, function ($a, $b) {
        $amin = isset($a['min']) ? (float) $a['min'] : INF;
        $bmin = isset($b['min']) ? (float) $b['min'] : INF;
        return $amin <=> $bmin;
    });

    foreach ($rules as $r) {
        if (!is_array($r))
            continue;
        if (!isset($r['min'], $r['max'], $r['delta']))
            continue;

        $min = (float) $r['min'];
        $max = (float) $r['max'];
        $delta = (float) $r['delta'];

        if ($min <= $p && $p < $max) {
            return $delta;
        }
    }

    return (float) $defaultDelta;
}

// ----------------------------------------------------
// TIME: compute next_run_at_utc from LA local HH:mm
// ----------------------------------------------------

function computeNextRunUtc($timeLocalHHMM, $tzLocal = 'America/Los_Angeles')
{
    $timeLocalHHMM = trim((string) $timeLocalHHMM);
    if (!preg_match('/^\d{2}:\d{2}$/', $timeLocalHHMM))
        return null;

    [$hh, $mm] = array_map('intval', explode(':', $timeLocalHHMM));

    $nowLocal = new DateTime('now', new DateTimeZone($tzLocal));
    $runLocal = (clone $nowLocal);
    $runLocal->setTime($hh, $mm, 0);

    // if already passed today, schedule tomorrow
    if ($runLocal <= $nowLocal) {
        $runLocal->modify('+1 day');
    }

    $runUtc = (clone $runLocal);
    $runUtc->setTimezone(new DateTimeZone('UTC'));
    return $runUtc->format('Y-m-d H:i:s');
}

// ----------------------------------------------------
// AMAZON HOOKS (YOU PLUG THESE IN)
// ----------------------------------------------------

function fetchCurrentPrice($store, $sku, $marketplaceIds)
{
    $base = rtrim(envv('APP_URL'), '/'); // from .env
    $cronKey = envv('CRON_KEY');

    if (!$base)
        throw new Exception("APP_URL missing in .env");
    if (!$cronKey)
        throw new Exception("CRON_KEY missing in .env");

    $url = $base . '/api/amazon/search-listings';

    $payload = [
        'store' => $store,
        'marketplaceIds' => $marketplaceIds ?: ['ATVPDKIKX0DER'],
        'includedData' => ['offers', 'summaries', 'productTypes'], // keep small
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
        $msg = $res['json']['error']['message'] ?? $res['raw'];
        throw new Exception("search-listings failed HTTP {$res['status']}: {$msg}");
    }

    // Your controller returns: { ok: true, data: payload }
    $items = $res['json']['data']['items'] ?? [];
    if (!$items || !isset($items[0]))
        return null;

    $it = $items[0];

    // Offers shape varies; try multiple paths
    $price =
        $it['offers'][0]['price']['amount'] ??
        $it['offers'][0]['listingPrice']['amount'] ??
        null;

    if ($price === null)
        return null;

    return (float) $price;
}

function patchPrice($store, $sku, $newPrice, $currency, $marketplaceIds)
{
    $base = rtrim(envv('APP_URL'), '/');
    $cronKey = envv('CRON_KEY');

    if (!$base)
        throw new Exception("APP_URL missing in .env");
    if (!$cronKey)
        throw new Exception("CRON_KEY missing in .env");

    $url = $base . '/api/amazon/listings/update-one';

    $payload = [
        'store' => $store,
        'marketplaceIds' => $marketplaceIds ?: ['ATVPDKIKX0DER'],
        'sku' => $sku,
        'price' => round((float) $newPrice, 2),
        'priceCleared' => false,
        'currency' => $currency ?: 'USD',

        // optional but helps patchListingsItem; your updateOne defaults to PRODUCT
        'productType' => 'PRODUCT',
    ];

    $res = http_post_json($url, $payload, [
        'X-CRON-KEY: ' . $cronKey,
    ], 50);

    if ($res['status'] < 200 || $res['status'] >= 300) {
        // Amazon errors are usually in error json
        $msg =
            $res['json']['error']['errors'][0]['message'] ??
            $res['json']['message'] ??
            $res['raw'];

        throw new Exception("update-one failed HTTP {$res['status']}: {$msg}");
    }

    // Your updateOne returns success=true on success
    $ok = $res['json']['success'] ?? false;
    if (!$ok) {
        $msg = $res['json']['message'] ?? $res['raw'];
        throw new Exception("update-one returned not-success: {$msg}");
    }

    return true;
}

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

    $json = json_decode($raw, true);

    return [
        'status' => $code,
        'raw' => $raw,
        'json' => $json,
    ];
}

// ----------------------------------------------------
// CORE
// ----------------------------------------------------

$mysqli = db();

// 0) Reset stuck processing items (resume safety)
{
    global $PROCESSING_TIMEOUT_MIN;

    $sql = "
        UPDATE tbl_paa_run_items
        SET status='pending', last_error=CONCAT(IFNULL(last_error,''), ' | reset stuck processing'), updated_at=UTC_TIMESTAMP()
        WHERE status='processing'
          AND updated_at < (UTC_TIMESTAMP() - INTERVAL ? MINUTE)
          AND attempts < ?
    ";

    $stmt = $mysqli->prepare($sql);
    $stmt->bind_param('ii', $PROCESSING_TIMEOUT_MIN, $MAX_ATTEMPTS);
    $stmt->execute();
    $affected = $stmt->affected_rows;
    $stmt->close();

    if ($affected > 0)
        logg("Reset stuck processing items: {$affected}");
}

// 1) Find due automations
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

$dueAutomations = [];
while ($row = $dueRes->fetch_assoc())
    $dueAutomations[] = $row;
$dueRes->free();

if (!count($dueAutomations)) {
    logg("No due automations.");
    exit(0);
}

logg("Due automations: " . count($dueAutomations));

foreach ($dueAutomations as $a) {
    $automationId = (int) $a['id'];
    $store = $a['store'];
    $tz = $a['timezone'] ?: 'America/Los_Angeles';
    $frequency = $a['frequency'] ?: 'DAILY'; // keep legacy support
    $marketplaceIds = json_decode($a['marketplace_ids'] ?? '[]', true) ?: [];
    $scheduledForUtc = $a['next_run_at_utc']; // current run schedule

    // NEW: triggers/rules/default
    $triggers = normalize_triggers(safe_json_array($a['triggers_json'] ?? null));
    if (!count($triggers)) {
        // fallback: legacy single time_local
        if (!empty($a['time_local']))
            $triggers = normalize_triggers([$a['time_local']]);
    }

    $rules = safe_json_array($a['rules_json'] ?? null);
    $defaultDelta = isset($a['default_delta']) ? (float) $a['default_delta'] : 0.0;

    // fallback: legacy delta if default_delta not set
    if (!isset($a['default_delta']) && isset($a['delta'])) {
        $defaultDelta = (float) $a['delta'];
    }

    logg("Automation #{$automationId} store={$store} scheduled_for_utc={$scheduledForUtc} triggers=" . json_encode($triggers) . " default_delta={$defaultDelta}");

    logg("Automation #{$automationId} store={$store} scheduled_for_utc={$scheduledForUtc} delta={$delta}");

    // 2) Create or get run row (unique per automation + scheduled_for_utc)
    $runId = null;

    $stmt = $mysqli->prepare("SELECT id, status FROM tbl_paa_runs WHERE automation_id=? AND scheduled_for_utc=? LIMIT 1");
    $stmt->bind_param('is', $automationId, $scheduledForUtc);
    $stmt->execute();
    $runRes = $stmt->get_result();
    $runRow = $runRes->fetch_assoc();
    $stmt->close();

    if ($runRow) {
        $runId = (int) $runRow['id'];
        logg("Found existing run #{$runId} status={$runRow['status']}");
    } else {
        // create run
        $stmt = $mysqli->prepare("
            INSERT INTO tbl_paa_runs
              (automation_id, scheduled_for_utc, status, started_at_utc, created_at, updated_at)
            VALUES
              (?, ?, 'running', UTC_TIMESTAMP(), UTC_TIMESTAMP(), UTC_TIMESTAMP())
        ");
        $stmt->bind_param('is', $automationId, $scheduledForUtc);
        if (!$stmt->execute()) {
            logg("Failed to insert run: " . $stmt->error);
            $stmt->close();
            continue;
        }
        $runId = (int) $stmt->insert_id;
        $stmt->close();

        logg("Created run #{$runId}");

        // snapshot template items into run items
        $items = [];
        $stmt = $mysqli->prepare("
            SELECT id, msku, sku
            FROM tbl_paa_automation_items
            WHERE automation_id=? AND is_active=1
        ");
        $stmt->bind_param('i', $automationId);
        $stmt->execute();
        $res = $stmt->get_result();
        while ($r = $res->fetch_assoc())
            $items[] = $r;
        $stmt->close();

        $totalItems = count($items);

        if ($totalItems === 0) {
            // mark run done as nothing to do, schedule next run
            logg("No items in automation. Marking run done.");

            $mysqli->query("UPDATE tbl_paa_runs SET status='done', finished_at_utc=UTC_TIMESTAMP(), updated_at=UTC_TIMESTAMP() WHERE id={$runId}");

            // schedule next
            if ($frequency === 'DAILY') {
                $next = computeNextRunUtcFromTriggers($triggers, $tz, $scheduledForUtc);
                $stmt = $mysqli->prepare("UPDATE tbl_paa_automations SET last_run_at_utc=UTC_TIMESTAMP(), next_run_at_utc=?, updated_at=UTC_TIMESTAMP() WHERE id=?");
                $stmt->bind_param('si', $next, $automationId);
                $stmt->execute();
                $stmt->close();
            } else {
                $mysqli->query("UPDATE tbl_paa_automations SET last_run_at_utc=UTC_TIMESTAMP(), is_enabled=0, updated_at=UTC_TIMESTAMP() WHERE id={$automationId}");
            }
            continue;
        }

        // bulk insert run items (simple loop; ok for moderate sizes)
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
                logg("Run item insert failed (msku={$msku}): " . $ins->error);
            }
        }
        $ins->close();

        $stmt = $mysqli->prepare("UPDATE tbl_paa_runs SET total_items=?, updated_at=UTC_TIMESTAMP() WHERE id=?");
        $stmt->bind_param('ii', $totalItems, $runId);
        $stmt->execute();
        $stmt->close();

        logg("Snapshot created: {$totalItems} item(s)");
    }

    // 3) Claim a batch of pending items (transaction + FOR UPDATE)
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
    while ($r = $res->fetch_assoc())
        $batch[] = $r;
    $stmt->close();

    if (!count($batch)) {
        $mysqli->commit();

        // If nothing pending/processing, finalize run
        $cntRes = $mysqli->query("SELECT
            SUM(status IN ('pending','processing')) AS open_count,
            SUM(status='success') AS success_count,
            SUM(status='failed') AS failed_count,
            SUM(status='skipped') AS skipped_count
            FROM tbl_paa_run_items
            WHERE run_id={$runId}
        ");
        $cnt = $cntRes ? $cntRes->fetch_assoc() : null;

        $openCount = (int) ($cnt['open_count'] ?? 0);

        if ($openCount === 0) {
            logg("Run #{$runId} completed. Finalizing...");

            $mysqli->query("
                UPDATE tbl_paa_runs
                SET status='done',
                    finished_at_utc=UTC_TIMESTAMP(),
                    success_items=" . (int) ($cnt['success_count'] ?? 0) . ",
                    failed_items=" . (int) ($cnt['failed_count'] ?? 0) . ",
                    skipped_items=" . (int) ($cnt['skipped_count'] ?? 0) . ",
                    processed_items=(" . (int) ($cnt['success_count'] ?? 0) . " + " . (int) ($cnt['failed_count'] ?? 0) . " + " . (int) ($cnt['skipped_count'] ?? 0) . "),
                    updated_at=UTC_TIMESTAMP()
                WHERE id={$runId}
            ");

            if ($frequency === 'DAILY') {
                $next = computeNextRunUtcFromTriggers($triggers, $tz, $scheduledForUtc);

                $stmt = $mysqli->prepare("UPDATE tbl_paa_automations SET last_run_at_utc=UTC_TIMESTAMP(), next_run_at_utc=?, updated_at=UTC_TIMESTAMP() WHERE id=?");
                $stmt->bind_param('si', $next, $automationId);
                $stmt->execute();
                $stmt->close();

                logg("Scheduled next_run_at_utc={$next}");
            } else {
                $mysqli->query("UPDATE tbl_paa_automations SET last_run_at_utc=UTC_TIMESTAMP(), is_enabled=0, updated_at=UTC_TIMESTAMP() WHERE id={$automationId}");
                logg("Frequency ONCE: automation disabled");
            }
        } else {
            logg("Run #{$runId} has no pending batch this tick (open_count={$openCount}).");
        }

        continue;
    }

    $ids = array_map(fn($x) => (int) $x['id'], $batch);
    $idList = implode(',', $ids);

    // mark claimed items as processing + attempts++
    $upd = "UPDATE tbl_paa_run_items
            SET status='processing', attempts=attempts+1, updated_at=UTC_TIMESTAMP()
            WHERE id IN ({$idList})";
    if (!$mysqli->query($upd)) {
        logg("Claim update failed: " . $mysqli->error);
        $mysqli->rollback();
        continue;
    }

    $mysqli->commit();

    logg("Claimed batch: " . count($batch) . " item(s)");

    // 4) Process each item
    foreach ($batch as $it) {
        $runItemId = (int) $it['id'];
        $msku = (string) $it['msku'];
        $sku = $it['sku'] !== null ? (string) $it['sku'] : null;

        try {
            // If sku not resolved yet, attempt resolve from your IMS (example)
            // Adjust this query to your real schema if needed.
            if (!$sku) {
                // Try resolve from tblproduct where MSKU matches
                $stmt = $mysqli->prepare("SELECT MSKU, SKU FROM tblproduct WHERE MSKU=? LIMIT 1");
                if ($stmt) {
                    $stmt->bind_param('s', $msku);
                    $stmt->execute();
                    $rr = $stmt->get_result()->fetch_assoc();
                    $stmt->close();

                    if (!empty($rr['SKU'])) {
                        $sku = $rr['SKU'];

                        // persist sku into template + run_item (optional but recommended)
                        $stmt = $mysqli->prepare("UPDATE tbl_paa_automation_items SET sku=?, updated_at=UTC_TIMESTAMP() WHERE automation_id=? AND msku=?");
                        $stmt->bind_param('sis', $sku, $automationId, $msku);
                        $stmt->execute();
                        $stmt->close();

                        $stmt = $mysqli->prepare("UPDATE tbl_paa_run_items SET sku=?, updated_at=UTC_TIMESTAMP() WHERE id=?");
                        $stmt->bind_param('si', $sku, $runItemId);
                        $stmt->execute();
                        $stmt->close();
                    }
                }
            }

            if (!$sku) {
                $stmt = $mysqli->prepare("UPDATE tbl_paa_run_items SET status='skipped', last_error=?, updated_at=UTC_TIMESTAMP() WHERE id=?");
                $err = "No SKU resolved for MSKU";
                $stmt->bind_param('si', $err, $runItemId);
                $stmt->execute();
                $stmt->close();
                continue;
            }

            // Fetch current price (YOU implement this)
            $currentPrice = fetchCurrentPrice($store, $sku, $marketplaceIds);

            if ($currentPrice === null) {
                $stmt = $mysqli->prepare("UPDATE tbl_paa_run_items SET status='skipped', last_error=?, updated_at=UTC_TIMESTAMP() WHERE id=?");
                $err = "No current price found";
                $stmt->bind_param('si', $err, $runItemId);
                $stmt->execute();
                $stmt->close();
                continue;
            }

            $deltaToApply = pickDeltaFromRules($currentPrice, $rules, $defaultDelta);
            $newPrice = (float) $currentPrice + (float) $deltaToApply;
            if ($newPrice < 0)
                $newPrice = 0;

            // Patch price (YOU implement this)
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

        } catch (Exception $e) {
            $msg = substr($e->getMessage(), 0, 2000);

            // if attempts hit max, mark failed; else send back to pending for next tick
            $stmt = $mysqli->prepare("SELECT attempts FROM tbl_paa_run_items WHERE id=? LIMIT 1");
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
        }
    }

    logg("Batch processed for run #{$runId}");
}

logg("Done.");