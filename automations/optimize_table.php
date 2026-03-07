<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
date_default_timezone_set('America/Los_Angeles');

echo "<h2>🔧 DATABASE OPTIMIZE TABLES CRON JOB</h2>";
echo "Started: " . date('Y-m-d H:i:s') . "<br><br>";

// === DB CONFIG === (same as your tracking cron)
$mysqli = new mysqli("localhost", "imsv2_dbims_user", "Imsv2_dbims_user", "imsv2_dbims");

if ($mysqli->connect_error) {
    die("❌ DB connection failed: " . $mysqli->connect_error);
}

$mysqli->query("SET SESSION wait_timeout = 600");
$mysqli->query("SET SESSION interactive_timeout = 600");

echo "✓ Database connected<br><br>";

// === TABLES TO OPTIMIZE ===
$tables = [
    'tblproduct',
    'tblcapturedimages',
    'tblEbayOrderImages',
    'tblfnsku',
    'tblasin',
];

// === LOG FILE ===
$logFile = __DIR__ . '/optimize_log.txt';

// ========================================
// STEP 1: Check table fragmentation first
// ========================================
echo "<h3>📊 STEP 1: Checking Table Fragmentation</h3>";

$fragmentedTables = [];

foreach ($tables as $table) {
    $result = $mysqli->query("
        SELECT 
            table_name,
            ROUND((data_length) / 1024 / 1024, 2)       AS used_mb,
            ROUND((data_free) / 1024 / 1024, 2)          AS wasted_mb,
            ROUND((index_length) / 1024 / 1024, 2)       AS index_mb,
            table_rows
        FROM information_schema.tables
        WHERE table_schema = DATABASE()
        AND table_name = '{$table}'
    ");

    if ($row = $result->fetch_assoc()) {
        $wastedMb = (float) $row['wasted_mb'];
        $usedMb   = (float) $row['used_mb'];
        $indexMb  = (float) $row['index_mb'];
        $rows     = number_format($row['table_rows']);

        $color  = $wastedMb > 50 ? '#dc3545' : ($wastedMb > 10 ? '#ffc107' : '#28a745');
        $label  = $wastedMb > 50 ? '⚠️ HIGH fragmentation' : ($wastedMb > 10 ? '🟡 Moderate' : '✅ OK');

        echo "<div style='background: #f8f9fa; padding: 8px; margin: 5px 0; border-left: 4px solid {$color};'>";
        echo "<strong>{$table}</strong> — ";
        echo "Rows: <strong>{$rows}</strong> | ";
        echo "Used: <strong>{$usedMb} MB</strong> | ";
        echo "Index: <strong>{$indexMb} MB</strong> | ";
        echo "Wasted: <strong style='color: {$color};'>{$wastedMb} MB</strong> | ";
        echo "<span style='color: {$color};'>{$label}</span>";
        echo "</div>";

        // Only optimize if wasted space > 1MB (no point optimizing clean tables)
        if ($wastedMb > 1) {
            $fragmentedTables[] = [
                'name'      => $table,
                'wasted_mb' => $wastedMb,
                'used_mb'   => $usedMb,
                'rows'      => $row['table_rows'],
            ];
        }
    }
}

echo "<br>";

if (empty($fragmentedTables)) {
    echo "<div style='background: #d4edda; padding: 15px; border: 2px solid #28a745;'>";
    echo "✅ All tables are clean — no optimization needed at this time<br>";
    echo "</div>";
    echo "<br>Finished: " . date('Y-m-d H:i:s') . "<br>";

    // Still log the run
    $log  = "========================================\n";
    $log .= "OPTIMIZE TABLES — " . date('Y-m-d H:i:s') . "\n";
    $log .= "========================================\n";
    $log .= "Result: All tables clean, no optimization needed\n\n";
    file_put_contents($logFile, $log, FILE_APPEND);

    $mysqli->close();
    exit;
}

echo "<div style='background: #fff3cd; padding: 10px; border-left: 4px solid #ffc107;'>";
echo "<strong>⚠️ " . count($fragmentedTables) . " table(s) need optimization</strong><br>";
foreach ($fragmentedTables as $t) {
    echo "→ <strong>{$t['name']}</strong> — {$t['wasted_mb']} MB wasted<br>";
}
echo "</div><br>";


// ========================================
// STEP 2: Run OPTIMIZE on fragmented tables
// ========================================
echo "<h3>🔧 STEP 2: Optimizing Tables</h3>";

$successCount = 0;
$errorCount   = 0;
$totalTime    = 0;

$log  = "========================================\n";
$log .= "OPTIMIZE TABLES — " . date('Y-m-d H:i:s') . "\n";
$log .= "========================================\n";
$log .= "Tables needing optimization: " . count($fragmentedTables) . "\n\n";

foreach ($fragmentedTables as $tableInfo) {
    $table = $tableInfo['name'];
    $start = microtime(true);

    echo "<div style='background: #d1ecf1; padding: 10px; margin: 10px 0; border-left: 4px solid #17a2b8;'>";
    echo "<strong>🔧 Optimizing: {$table}</strong><br>";
    echo "→ Rows: " . number_format($tableInfo['rows']) . " | ";
    echo "Wasted: {$tableInfo['wasted_mb']} MB<br>";

    $result = $mysqli->query("OPTIMIZE TABLE `{$table}`");

    $elapsed    = round(microtime(true) - $start, 4);
    $totalTime += $elapsed;

    if ($result) {
        $row    = $result->fetch_assoc();
        $msgType = $row['Msg_type'] ?? '';
        $msgText = $row['Msg_text'] ?? '';

        echo "→ ✅ <strong style='color: #28a745;'>Success</strong> — {$elapsed}s<br>";
        echo "→ MySQL says: <em>{$msgText}</em><br>";

        $log .= "✅ {$table} — {$elapsed}s | {$msgText}\n";
        $successCount++;
    } else {
        echo "→ ❌ <strong style='color: #dc3545;'>Failed</strong> — " . $mysqli->error . "<br>";
        $log .= "❌ {$table} — FAILED: " . $mysqli->error . "\n";
        $errorCount++;
    }

    echo "</div>";
}


// ========================================
// STEP 3: Verify — check fragmentation after optimize
// ========================================
echo "<h3>✅ STEP 3: Verifying After Optimization</h3>";

foreach ($fragmentedTables as $tableInfo) {
    $table  = $tableInfo['name'];
    $result = $mysqli->query("
        SELECT 
            ROUND((data_free) / 1024 / 1024, 2) AS wasted_mb_after
        FROM information_schema.tables
        WHERE table_schema = DATABASE()
        AND table_name = '{$table}'
    ");

    if ($row = $result->fetch_assoc()) {
        $wastedAfter  = (float) $row['wasted_mb_after'];
        $wastedBefore = $tableInfo['wasted_mb'];
        $saved        = round($wastedBefore - $wastedAfter, 2);
        $color        = $wastedAfter < 1 ? '#28a745' : '#ffc107';

        echo "<div style='background: #f8f9fa; padding: 8px; margin: 5px 0; border-left: 4px solid {$color};'>";
        echo "<strong>{$table}</strong> — ";
        echo "Before: <strong>{$wastedBefore} MB</strong> wasted → ";
        echo "After: <strong style='color: {$color};'>{$wastedAfter} MB</strong> wasted | ";
        echo "💾 Saved: <strong style='color: #28a745;'>{$saved} MB</strong>";
        echo "</div>";

        $log .= "  Before: {$wastedBefore} MB wasted → After: {$wastedAfter} MB | Saved: {$saved} MB\n";
    }
}


// ========================================
// FINAL SUMMARY
// ========================================
$log .= "\nTotal time : {$totalTime}s\n";
$log .= "Finished   : " . date('Y-m-d H:i:s') . "\n\n";
file_put_contents($logFile, $log, FILE_APPEND);

echo "<br><div style='background: #007bff; color: white; padding: 20px; border-radius: 8px; margin-top: 20px;'>";
echo "<h3 style='margin: 0 0 15px 0;'>📊 FINAL SUMMARY</h3>";
echo "<hr style='border-color: rgba(255,255,255,0.3); margin: 15px 0;'>";
echo "Tables checked    : <strong>" . count($tables) . "</strong><br>";
echo "Tables optimized  : <strong>{$successCount}</strong><br>";
echo "Tables skipped    : <strong>" . (count($tables) - count($fragmentedTables)) . "</strong> (already clean)<br>";
echo "Errors            : <strong>{$errorCount}</strong><br>";
echo "Total time        : <strong>{$totalTime}s</strong><br>";
echo "<hr style='border-color: rgba(255,255,255,0.3); margin: 15px 0;'>";
echo "Log saved to      : <strong>optimize_log.txt</strong><br>";
echo "Finished          : <strong>" . date('Y-m-d H:i:s') . "</strong><br>";
echo "</div>";

$mysqli->close();
?>