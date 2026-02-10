<?php

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
date_default_timezone_set('America/Los_Angeles');

echo "<h2>⏰ CHECK OVERDUE PER TRACKING NUMBER</h2>";
echo "Started: " . date('Y-m-d H:i:s') . "<br><br>";

$mysqli = new mysqli("localhost", "imsv2_dbims_user", "Imsv2_dbims_user", "imsv2_dbims");

if ($mysqli->connect_error) {
    die("❌ Connection failed: " . $mysqli->connect_error);
}

echo "✓ Database connected<br><br>";

// === CONFIGURATION ===
define('OVERDUE_GRACE_DAYS', 1); // Days after estimated delivery to mark as overdue

$today = new DateTime();
$graceDateThreshold = (clone $today)->modify('-' . OVERDUE_GRACE_DAYS . ' days')->format('Y-m-d');

echo "<div style='background: #e7f3ff; padding: 15px; border-left: 4px solid #007bff;'>";
echo "<strong>📅 DATE CONFIGURATION</strong><br>";
echo "Today: <strong>" . $today->format('Y-m-d') . "</strong><br>";
echo "Grace period: <strong>" . OVERDUE_GRACE_DAYS . " days</strong><br>";
echo "Will mark as overdue if estimated delivery was before: <strong>{$graceDateThreshold}</strong><br>";
echo "</div><br>";

// ========================================
// STEP 1: Get all orders with tracking numbers
// ========================================
echo "<h3>📦 STEP 1: Loading Orders with Tracking</h3>";

$query = "
    SELECT 
        ProductID,
        rtid,
        itemnumber,
        estimated_deliverydate,
        trackingnumber,
        trackingnumber2,
        trackingnumber3,
        trackingnumber4,
        tracking1_status,
        tracking2_status,
        tracking3_status,
        tracking4_status
    FROM tblproduct
    WHERE ProductModuleLoc = 'Orders'
    AND estimated_deliverydate IS NOT NULL
    AND estimated_deliverydate != ''
    AND estimated_deliverydate != '0000-00-00'
    AND (
        (trackingnumber IS NOT NULL AND trackingnumber != '')
        OR (trackingnumber2 IS NOT NULL AND trackingnumber2 != '')
        OR (trackingnumber3 IS NOT NULL AND trackingnumber3 != '')
        OR (trackingnumber4 IS NOT NULL AND trackingnumber4 != '')
    )
    ORDER BY ProductID DESC
";

$result = $mysqli->query($query);

if (!$result) {
    die("❌ Query failed: " . $mysqli->error);
}

echo "Found <strong>" . $result->num_rows . "</strong> orders with tracking and estimated delivery dates<br><br>";

// ========================================
// STEP 2: Process each order's tracking numbers
// ========================================
echo "<h3>🔍 STEP 2: Checking Each Tracking Number</h3>";

$totalChecked = 0;
$totalMarkedOverdue = 0;
$totalClearedOverdue = 0;
$errorCount = 0;

$finalStatuses = ['Delivered', 'Cancelled', 'Refunded'];

while ($row = $result->fetch_assoc()) {
    $productID = $row['ProductID'];
    $orderID = $row['rtid'];
    $estimatedDelivery = $row['estimated_deliverydate'];
    
    // Parse estimated delivery date (take the END date from range)
    // Format: "2025-01-15 to 2025-01-20" -> take "2025-01-20"
    $estimatedDateStr = $estimatedDelivery;
    if (strpos($estimatedDelivery, ' to ') !== false) {
        $parts = explode(' to ', $estimatedDelivery);
        $estimatedDateStr = trim($parts[1]);
    }
    
    try {
        $estimatedDate = new DateTime($estimatedDateStr);
    } catch (Exception $e) {
        echo "⚠️ ProductID {$productID}: Invalid date format '{$estimatedDateStr}'<br>";
        continue;
    }
    
    // Calculate days since estimated delivery
    $daysSinceEstimated = $today->diff($estimatedDate)->days;
    $isPastEstimated = $estimatedDate < $today;
    
    // Only process if past estimated date + grace period
    if (!$isPastEstimated || $daysSinceEstimated < OVERDUE_GRACE_DAYS) {
        continue; // Not overdue yet
    }
    
    echo "<div style='background: #f8f9fa; padding: 10px; margin: 10px 0; border-left: 4px solid #6c757d;'>";
    echo "<strong>📦 Order: {$orderID}</strong> | ProductID: {$productID}<br>";
    echo "   📅 Estimated: {$estimatedDelivery} | Days overdue: <strong>{$daysSinceEstimated}</strong><br>";
    
    $updatesNeeded = [];
    
    // Check each tracking number (1-4)
    for ($i = 1; $i <= 4; $i++) {
        $trackingField = $i == 1 ? 'trackingnumber' : "trackingnumber{$i}";
        $statusField = "tracking{$i}_status";
        
        $trackingNumber = trim($row[$trackingField] ?? '');
        $currentStatus = trim($row[$statusField] ?? '');
        
        // Skip if no tracking number
        if (empty($trackingNumber)) {
            continue;
        }
        
        $totalChecked++;
        
        // ========================================
        // LOGIC: Mark as "Overdue" if In Transit
        // ========================================
        
        // If status is "In Transit" -> change to "Overdue"
        if ($currentStatus === 'In Transit') {
            $updatesNeeded[$statusField] = 'Overdue';
            echo "   → Tracking{$i} (<code>{$trackingNumber}</code>): '<span style='color: #007bff;'>{$currentStatus}</span>' → '<span style='color: #dc3545; font-weight: bold;'>Overdue</span>'<br>";
            $totalMarkedOverdue++;
        }
        // If status is "Overdue" but now delivered -> keep as Delivered (don't change)
        elseif (in_array($currentStatus, $finalStatuses)) {
            echo "   → Tracking{$i} (<code>{$trackingNumber}</code>): '<span style='color: #28a745;'>{$currentStatus}</span>' (final status, no change)<br>";
        }
        // If already "Overdue" -> keep it
        elseif ($currentStatus === 'Overdue') {
            echo "   → Tracking{$i} (<code>{$trackingNumber}</code>): Already '<span style='color: #dc3545;'>Overdue</span>'<br>";
        }
        // Other statuses (Delivery Exception, Not Found, Unknown)
        else {
            echo "   → Tracking{$i} (<code>{$trackingNumber}</code>): '{$currentStatus}' (no change)<br>";
        }
    }
    
    // Execute updates if needed
    if (!empty($updatesNeeded)) {
        $updateParts = [];
        $updateValues = [];
        $updateTypes = "";
        
        foreach ($updatesNeeded as $field => $value) {
            $updateParts[] = "{$field} = ?";
            $updateValues[] = $value;
            $updateTypes .= "s";
        }
        
        $updateSQL = "UPDATE tblproduct SET " . implode(", ", $updateParts) . " WHERE ProductID = ?";
        $updateValues[] = $productID;
        $updateTypes .= "i";
        
        $stmt = $mysqli->prepare($updateSQL);
        
        if (!$stmt) {
            echo "   <span style='color: red;'>❌ Prepare failed: " . $mysqli->error . "</span><br>";
            $errorCount++;
        } else {
            $stmt->bind_param($updateTypes, ...$updateValues);
            
            if ($stmt->execute()) {
                echo "   <span style='color: green;'>✅ Updated " . count($updatesNeeded) . " tracking status(es)</span><br>";
            } else {
                echo "   <span style='color: red;'>❌ Update failed: " . $stmt->error . "</span><br>";
                $errorCount++;
            }
            
            $stmt->close();
        }
    } else {
        echo "   ℹ️ No updates needed<br>";
    }
    
    echo "</div>";
}

echo "<br>";

// ========================================
// STEP 3: Update main delivery_status based on individual tracking
// ========================================
echo "<h3>🔄 STEP 3: Updating Main Delivery Status</h3>";

/**
 * Priority logic for main delivery_status:
 * 1. If ANY tracking is "Delivered" -> "Delivered"
 * 2. If ANY tracking is "Overdue" -> "Overdue"
 * 3. If ANY tracking is "In Transit" -> "In Transit"
 * 4. Otherwise use first non-empty status
 */

$mainStatusQuery = "
    SELECT 
        ProductID,
        delivery_status,
        tracking1_status,
        tracking2_status,
        tracking3_status,
        tracking4_status
    FROM tblproduct
    WHERE ProductModuleLoc = 'Orders'
    AND (
        tracking1_status IS NOT NULL
        OR tracking2_status IS NOT NULL
        OR tracking3_status IS NOT NULL
        OR tracking4_status IS NOT NULL
    )
";

$mainResult = $mysqli->query($mainStatusQuery);
$mainStatusUpdated = 0;

while ($row = $mainResult->fetch_assoc()) {
    $productID = $row['ProductID'];
    $currentMainStatus = $row['delivery_status'] ?? '';
    
    $statuses = [
        $row['tracking1_status'],
        $row['tracking2_status'],
        $row['tracking3_status'],
        $row['tracking4_status']
    ];
    
    // Remove empty
    $statuses = array_filter($statuses);
    
    if (empty($statuses)) {
        continue;
    }
    
    // Determine new main status based on priority
    $newMainStatus = null;
    
    // Priority 1: Delivered
    if (in_array('Delivered', $statuses)) {
        $newMainStatus = 'Delivered';
    }
    // Priority 2: Overdue
    elseif (in_array('Overdue', $statuses)) {
        $newMainStatus = 'Overdue';
    }
    // Priority 3: In Transit
    elseif (in_array('In Transit', $statuses)) {
        $newMainStatus = 'In Transit';
    }
    // Priority 4: Delivery Exception
    elseif (in_array('Delivery Exception', $statuses)) {
        $newMainStatus = 'Delivery Exception';
    }
    // Otherwise: First status
    else {
        $newMainStatus = reset($statuses);
    }
    
    // Skip if already in final status (Delivered, Cancelled, Refunded)
    if (in_array($currentMainStatus, $finalStatuses)) {
        continue;
    }
    
    // Update if different
    if ($newMainStatus && $newMainStatus !== $currentMainStatus) {
        $updateStmt = $mysqli->prepare("UPDATE tblproduct SET delivery_status = ? WHERE ProductID = ?");
        $updateStmt->bind_param("si", $newMainStatus, $productID);
        
        if ($updateStmt->execute()) {
            echo "→ ProductID {$productID}: '<span style='color: #6c757d;'>{$currentMainStatus}</span>' → '<strong>{$newMainStatus}</strong>'<br>";
            $mainStatusUpdated++;
        }
        
        $updateStmt->close();
    }
}

echo "<br><div style='background: #d4edda; padding: 10px; border-left: 4px solid #28a745;'>";
echo "✅ Main delivery status updated for <strong>{$mainStatusUpdated}</strong> orders<br>";
echo "</div><br>";

// ========================================
// STEP 4: Summary
// ========================================
echo "<h3>📊 STEP 4: Overdue Summary by Status</h3>";

$summaryQuery = "
    SELECT 
        'Tracking 1' as tracking_field,
        tracking1_status as status,
        COUNT(*) as count
    FROM tblproduct
    WHERE tracking1_status = 'Overdue'
    AND ProductModuleLoc = 'Orders'
    
    UNION ALL
    
    SELECT 
        'Tracking 2',
        tracking2_status,
        COUNT(*)
    FROM tblproduct
    WHERE tracking2_status = 'Overdue'
    AND ProductModuleLoc = 'Orders'
    
    UNION ALL
    
    SELECT 
        'Tracking 3',
        tracking3_status,
        COUNT(*)
    FROM tblproduct
    WHERE tracking3_status = 'Overdue'
    AND ProductModuleLoc = 'Orders'
    
    UNION ALL
    
    SELECT 
        'Tracking 4',
        tracking4_status,
        COUNT(*)
    FROM tblproduct
    WHERE tracking4_status = 'Overdue'
    AND ProductModuleLoc = 'Orders'
";

$summaryResult = $mysqli->query($summaryQuery);

if ($summaryResult && $summaryResult->num_rows > 0) {
    echo "<table style='border-collapse: collapse; width: 100%; margin-top: 10px;'>";
    echo "<thead style='background: #dc3545; color: white;'>";
    echo "<tr>";
    echo "<th style='padding: 10px; border: 1px solid #ddd;'>Tracking Field</th>";
    echo "<th style='padding: 10px; border: 1px solid #ddd; text-align: center;'>Overdue Count</th>";
    echo "</tr>";
    echo "</thead>";
    echo "<tbody>";
    
    $totalOverdueTracking = 0;
    
    while ($row = $summaryResult->fetch_assoc()) {
        $field = $row['tracking_field'];
        $count = $row['count'];
        $totalOverdueTracking += $count;
        
        echo "<tr>";
        echo "<td style='padding: 10px; border: 1px solid #ddd;'><strong>{$field}</strong></td>";
        echo "<td style='padding: 10px; border: 1px solid #ddd; text-align: center; background: #f8d7da;'><strong>{$count}</strong></td>";
        echo "</tr>";
    }
    
    echo "<tfoot style='background: #dc3545; color: white; font-weight: bold;'>";
    echo "<tr>";
    echo "<td style='padding: 10px; border: 1px solid #ddd;'>TOTAL OVERDUE TRACKING NUMBERS</td>";
    echo "<td style='padding: 10px; border: 1px solid #ddd; text-align: center;'>{$totalOverdueTracking}</td>";
    echo "</tr>";
    echo "</tfoot>";
    echo "</tbody>";
    echo "</table>";
} else {
    echo "<div style='background: #d4edda; padding: 15px; border: 2px solid #28a745;'>";
    echo "✅ <strong>No overdue tracking numbers!</strong> All deliveries are on track.<br>";
    echo "</div>";
}

// ========================================
// FINAL SUMMARY
// ========================================
echo "<br><div style='background: #007bff; color: white; padding: 20px; border-radius: 8px;'>";
echo "<h3 style='margin: 0 0 15px 0;'>📊 FINAL SUMMARY</h3>";
echo "<hr style='border-color: rgba(255,255,255,0.3); margin: 15px 0;'>";
echo "Tracking numbers checked: <strong>{$totalChecked}</strong><br>";
echo "Marked as overdue: <strong>{$totalMarkedOverdue}</strong><br>";
echo "Main delivery statuses updated: <strong>{$mainStatusUpdated}</strong><br>";
echo "Errors: <strong>{$errorCount}</strong><br>";
echo "<hr style='border-color: rgba(255,255,255,0.3); margin: 15px 0;'>";
echo "Grace period: <strong>" . OVERDUE_GRACE_DAYS . " days</strong><br>";
echo "Finished: <strong>" . date('Y-m-d H:i:s') . "</strong><br>";
echo "</div><br>";

// ========================================
// HOW TO QUERY OVERDUE TRACKING
// ========================================
echo "<div style='background: #f8f9fa; padding: 15px; border: 2px solid #6c757d; border-radius: 5px;'>";
echo "<h4>💡 Example Queries:</h4>";
echo "<pre style='background: #e9ecef; padding: 10px; border-radius: 5px; overflow-x: auto;'>";
echo "-- Get orders with ANY overdue tracking\n";
echo "SELECT * FROM tblproduct \n";
echo "WHERE (\n";
echo "    tracking1_status = 'Overdue' \n";
echo "    OR tracking2_status = 'Overdue'\n";
echo "    OR tracking3_status = 'Overdue'\n";
echo "    OR tracking4_status = 'Overdue'\n";
echo ")\n";
echo "AND ProductModuleLoc = 'Orders';\n\n";

echo "-- Count overdue tracking numbers per field\n";
echo "SELECT \n";
echo "    SUM(CASE WHEN tracking1_status = 'Overdue' THEN 1 ELSE 0 END) as tracking1_overdue,\n";
echo "    SUM(CASE WHEN tracking2_status = 'Overdue' THEN 1 ELSE 0 END) as tracking2_overdue,\n";
echo "    SUM(CASE WHEN tracking3_status = 'Overdue' THEN 1 ELSE 0 END) as tracking3_overdue,\n";
echo "    SUM(CASE WHEN tracking4_status = 'Overdue' THEN 1 ELSE 0 END) as tracking4_overdue\n";
echo "FROM tblproduct \n";
echo "WHERE ProductModuleLoc = 'Orders';";
echo "</pre>";
echo "</div><br>";

echo "<div style='background: #fff3cd; padding: 15px; border-left: 5px solid #ffc107;'>";
echo "<strong>⚠️ IMPORTANT NOTES:</strong><br><br>";
echo "1. Each tracking number is checked independently<br>";
echo "2. Grace period of <strong>" . OVERDUE_GRACE_DAYS . " days</strong> after estimated delivery<br>";
echo "3. Only changes 'In Transit' → 'Overdue'<br>";
echo "4. Does NOT change Delivered/Cancelled/Refunded statuses<br>";
echo "5. Main delivery_status uses priority: Delivered > Overdue > In Transit<br>";
echo "6. If item has multiple tracking and ONE is overdue, main status = 'Overdue'<br>";
echo "</div>";

$mysqli->close();
?>