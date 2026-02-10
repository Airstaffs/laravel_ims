<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
session_start();
date_default_timezone_set('America/Los_Angeles');

echo "Current directory: " . __DIR__ . "<br>";
echo "Working directory: " . getcwd() . "<br>";

// === CONFIGURATION CONSTANTS FROM V1 ===
define('BATCH_SIZE', 50);
define('MAX_ORDERS_PER_RUN', 100);
define('API_CALL_DELAY', 1);
define('BATCH_PROCESSING_DELAY', 2);
define('MAX_PAGES_PER_RUN', 5);
define('MAX_EMPTY_PAGES', 2);


// === DB CONFIG ===
$mysqli = new mysqli("localhost", "imsv2_dbims_user", "Imsv2_dbims_user", "imsv2_dbims");

if ($mysqli->connect_error) {
    die("DB connection failed: " . $mysqli->connect_error . "<br>");
}

// Set connection options to prevent "MySQL server has gone away"
$mysqli->options(MYSQLI_OPT_CONNECT_TIMEOUT, 30);
$mysqli->options(MYSQLI_OPT_READ_TIMEOUT, 60);

// CRITICAL: Set session timeout variables to prevent connection loss
$mysqli->query("SET SESSION wait_timeout = 600");
$mysqli->query("SET SESSION interactive_timeout = 600");

echo "✓ Database connection established with extended timeouts<br>";

// Function to check and reconnect if connection is lost
function checkDatabaseConnection() {
    global $mysqli;
    
    // Use @ to suppress ping errors and handle them manually
    if (!@$mysqli->ping()) {
        echo "⚠️ Database connection lost. Reconnecting...<br>";
        @$mysqli->close();
        
        $mysqli = new mysqli("localhost", "imsv2_dbims_user", "Imsv2_dbims_user", "imsv2_dbims");
        
        if ($mysqli->connect_error) {
            die("❌ DB reconnection failed: " . $mysqli->connect_error . "<br>");
        }
        
        // Set connection options
        $mysqli->options(MYSQLI_OPT_CONNECT_TIMEOUT, 30);
        $mysqli->options(MYSQLI_OPT_READ_TIMEOUT, 60);
        
        // CRITICAL: Reset session timeout variables after reconnection
        $mysqli->query("SET SESSION wait_timeout = 600");
        $mysqli->query("SET SESSION interactive_timeout = 600");
        
        echo "✓ Database reconnected successfully with extended timeouts<br>";
        return true;
    }
    
    return false;
}

// Progress file paths
$progressFile = '/home/imsv2/public_html/laravel_ims/automations/progress.json';

// =====================================
// MAIN ENTRY POINT 
// =====================================
fetchOrdersCron();

// === PROGRESS TRACKING FUNCTIONS FROM V1 ===
function saveProgress($lastProcessedOrderId, $lastProcessedItemId, $currentPage, $totalProcessed, $pageOrderIndex = 0, $pageCompleted = false) {
    global $progressFile;
    
    $progressData = [
        'last_processed_order_id' => $lastProcessedOrderId,
        'last_processed_item_id' => $lastProcessedItemId,
        'current_page' => $currentPage,
        'page_order_index' => $pageOrderIndex,
        'total_processed' => $totalProcessed,
        'last_run_timestamp' => time(),
        'last_run_date' => date('Y-m-d H:i:s'),
        'optimized_mode' => true,
        'page_completed' => $pageCompleted
    ];
    
    static $saveCounter = 0;
    $saveCounter++;
    
    if ($saveCounter % 3 == 0 || $saveCounter == 1) {
        if (!file_put_contents($progressFile, json_encode($progressData, JSON_PRETTY_PRINT))) {
            echo "Warning: Unable to save progress to file.<br>";
        } else {
            echo "Progress saved: Page $currentPage, Index $pageOrderIndex, Total processed: $totalProcessed<br>";
        }
    }
}

function loadProgress() {
    global $progressFile;
    
    if (!file_exists($progressFile)) {
        return [
            'last_processed_order_id' => null,
            'last_processed_item_id' => null,
            'current_page' => 1,
            'page_order_index' => 0,
            'total_processed' => 0,
            'last_run_timestamp' => null,
            'page_completed' => false
        ];
    }
    
    $progressData = json_decode(file_get_contents($progressFile), true);
    if (json_last_error() !== JSON_ERROR_NONE) {
        echo "Warning: Invalid JSON in progress file, starting from beginning.<br>";
        return [
            'last_processed_order_id' => null,
            'last_processed_item_id' => null,
            'current_page' => 1,
            'page_order_index' => 0,
            'total_processed' => 0,
            'last_run_timestamp' => null,
            'page_completed' => false
        ];
    }
    
    if (!isset($progressData['page_order_index'])) {
        $progressData['page_order_index'] = 0;
    }
    if (!isset($progressData['page_completed'])) {
        $progressData['page_completed'] = false;
    }
    
    return $progressData;
}

function performSmartReset($reason, $totalProcessedOverall) {
    global $progressFile;
    
    echo "SMART RESET TRIGGERED: $reason<br>";
    
    $resetProgress = [
        'last_processed_order_id' => null,
        'last_processed_item_id' => null,
        'current_page' => 1,
        'page_order_index' => 0,
        'total_processed' => $totalProcessedOverall,
        'last_run_timestamp' => time(),
        'last_run_date' => date('Y-m-d H:i:s'),
        'optimized_mode' => true,
        'reset_reason' => $reason,
        'reset_timestamp' => time(),
        'resume_cleared' => true,
        'page_completed' => false
    ];
    
    if (file_put_contents($progressFile, json_encode($resetProgress, JSON_PRETTY_PRINT))) {
        echo "✓ Reset completed - will start fresh from page 1 next run<br>";
        echo "✓ Resume tracking CLEARED - will process all orders from page 1<br>";
        echo "✓ Total processed count preserved: $totalProcessedOverall<br>";
        return true;
    } else {
        echo "✗ Warning: Failed to save reset progress<br>";
        return false;
    }
}

// === UTILITY FUNCTIONS ===
function now()
{
    return date('Y-m-d H:i:s');
}

function env($key, $default = null)
{
    return getenv($key) ?: $default;
}

function db_query($query, $bind = [])
{
    global $mysqli;
    
    // CRITICAL: Check connection before every query
    checkDatabaseConnection();
    
    $stmt = $mysqli->prepare($query);
    if (!$stmt) {
        // If prepare fails, try reconnecting once
        echo "⚠️ Prepare failed, attempting reconnection...<br>";
        checkDatabaseConnection();
        $stmt = $mysqli->prepare($query);
        
        if (!$stmt) {
            throw new Exception("Failed to prepare statement: " . $mysqli->error);
        }
    }
    
    if ($bind) {
        $types = str_repeat("s", count($bind));
        $stmt->bind_param($types, ...$bind);
    }
    
    if (!$stmt->execute()) {
        throw new Exception("Failed to execute statement: " . $stmt->error);
    }
    
    return $stmt;
}

function db_fetch_assoc($query, $bind = [])
{
    $stmt = db_query($query, $bind);
    $result = $stmt->get_result();
    return $result ? $result->fetch_assoc() : null;
}

function db_fetch_all($query, $bind = [])
{
    $stmt = db_query($query, $bind);
    $result = $stmt->get_result();
    return $result ? $result->fetch_all(MYSQLI_ASSOC) : [];
}

// === ENHANCED MAIN FUNCTION WITH V1 LOGIC ===
function fetchOrdersCron()
{
    $serverconfig = env('EBAY_SERVER_CONFIG', 'LIVE');
    
    
    // Load progress for multi-page processing
    $progress = loadProgress();
    $pageNumber = $progress['current_page'];
    
    echo "Starting from saved page: {$pageNumber}<br>";

    $credentials = EbayCredentials();
    if (!$credentials || empty($credentials['access_token'])) {
        echo "❌ Failed to retrieve a valid access token.<br>";
        return;
    }

    $accessToken = $credentials['access_token'];
    
    $pagesProcessed = 0;
    $totalOrdersFound = 0;
    $consecutiveEmptyPages = 0;

    try {
        // Process multiple pages like V1
        while ($pagesProcessed < MAX_PAGES_PER_RUN) {
            echo "<br>=== FETCHING PAGE {$pageNumber} ===<br>";
            
            // Check connection before API call
            checkDatabaseConnection();
            
            $response = sendEbayRequest($accessToken, $pageNumber);

            if (!$response) {
                echo "❌ Failed to retrieve orders for page {$pageNumber}.<br>";
                $pageNumber++;
                $pagesProcessed++;
                continue;
            }

            if (!empty($response['Errors'])) {
                handleEbayErrors($response['Errors'], $serverconfig, $credentials);
                return;
            }

            $pageOrders = processOrders($response, $accessToken);
            
            if (!empty($pageOrders)) {
                $totalOrdersFound += count($pageOrders);
                $consecutiveEmptyPages = 0;
                echo "✓ Page {$pageNumber}: " . count($pageOrders) . " orders found<br>";
                
                // Process orders with resume capability
                $processedCount = processOrdersWithResume($pageOrders, $pageNumber);
                echo "Processed {$processedCount} orders on page {$pageNumber}<br>";
                
            } else {
                $consecutiveEmptyPages++;
                echo "○ Page {$pageNumber}: No orders found (consecutive empty: {$consecutiveEmptyPages}/" . MAX_EMPTY_PAGES . ")<br>";
                
                // Still advance the page in progress even if empty
                saveProgress(null, null, $pageNumber + 1, $progress['total_processed'], 0, true);
            }

            $pageNumber++;
            $pagesProcessed++;
            
            // Reset condition after consecutive empty pages
            if ($consecutiveEmptyPages >= MAX_EMPTY_PAGES) {
                echo "RESET CONDITION MET: {$consecutiveEmptyPages} consecutive empty pages<br>";
                performSmartReset("Consecutive empty pages reached", $progress['total_processed']);
                break;
            }
            
            // Reset if very high page number with no recent results
            if ($pageNumber > 20 && $consecutiveEmptyPages >= 3) {
                echo "RESET CONDITION MET: High page number ({$pageNumber}) with recent empty pages<br>";
                performSmartReset("High page number with no recent results", $progress['total_processed']);
                break;
            }
            
            sleep(1); // Small delay between page fetches
        }

        // IMPORTANT: Reset to page 1 after reaching MAX_PAGES_PER_RUN
        if ($pagesProcessed >= MAX_PAGES_PER_RUN) {
            echo "<br>📍 MAX_PAGES_PER_RUN ({$pagesProcessed}) reached. Resetting to page 1 for next run.<br>";
            performSmartReset("Max pages per run reached - cycling back to page 1", $progress['total_processed']);
        }

        echo "<br>=== FINAL SUMMARY ===<br>";
        echo "Pages processed this run: {$pagesProcessed}<br>";
        echo "Total orders found this run: {$totalOrdersFound}<br>";
        echo "Consecutive empty pages: {$consecutiveEmptyPages}<br>";

        // Load final progress to show next start point
        $finalProgress = loadProgress();
        echo "Next run starts from page: " . $finalProgress['current_page'] . "<br>";

        echo "✅ Orders fetched and processed successfully.<br>";

    } catch (Exception $e) {
        echo "❌ Exception in fetchOrders: " . $e->getMessage() . "<br>";
        echo "Stack trace: " . $e->getTraceAsString() . "<br>";
    }
}

// === ENHANCED ORDER PROCESSING WITH RESUME FROM V1 ===
function processOrdersWithResume($pageOrders, $currentPage) {
    global $mysqli;
    
    if (empty($pageOrders)) {
        echo "No orders to process for page $currentPage<br>";
        return 0;
    }
    
    $progress = loadProgress();
    $totalProcessed = $progress['total_processed'];
    $pageOrderIndex = 0;
    
    // Resume logic
    if ($currentPage == $progress['current_page'] && !$progress['page_completed']) {
        $pageOrderIndex = $progress['page_order_index'];
        echo "Resuming page $currentPage from order index $pageOrderIndex<br>";
    } else {
        echo "Starting fresh processing for page $currentPage<br>";
    }
    
    echo "=== PROCESSING PAGE $currentPage ===<br>";
    echo "Total orders on this page: " . count($pageOrders) . "<br>";
    echo "Starting from index: $pageOrderIndex<br>";
    echo "MODE: SMART PROCESSING - Prevent duplicates, Smart update for Orders module<br>";
    echo "====================================<br><br>";
    
    $currentProcessed = 0;
    $skippedCount = 0;
    $errorCount = 0;
    $trackingUpdatedCount = 0;
    $newRecordsCount = 0;
    $existingRecordsUpdated = 0;
    $smartUpdatedCount = 0;
    $preventedDuplicates = 0;
    
    // Process orders starting from the correct index within the page
    for ($i = $pageOrderIndex; $i < count($pageOrders); $i++) {
        // CRITICAL: Check database connection at the start of each loop iteration
        checkDatabaseConnection();
        
        $order = $pageOrders[$i];
        $orderID = $order['order_id'];
        
        echo "<hr style='border: 2px solid #007bff;'>";
        echo "<strong style='font-size: 1.2em;'>📦 ORDER: {$orderID}</strong><br>";
        
        if (empty($order['items'])) {
            echo "⚠️ No items in order<br>";
            continue;
        }
        
        try {
            // Process each item in the order
            foreach ($order['items'] as $itemIndex => $item) {
                $itemID = $item['item_id'];
                
                if (!$itemID) {
                    echo "⚠️ Skipping item with missing ID<br>";
                    continue;
                }
                
                $originalTitle = $item['title'];
                $title = cleanTitle($originalTitle);
                $orderStatus = $order['order_status'];
                
                echo "<div style='background: #f8f9fa; padding: 10px; margin: 10px 0; border-left: 4px solid #28a745;'>";
                echo "<strong>🔍 ITEM #{$itemIndex}: {$itemID}</strong><br>";
                echo "📝 Title: " . substr($title, 0, 60) . "...<br>";
                echo "📊 Status: {$orderStatus}<br>";
                echo "<br>";

                // ============================================
                // CRITICAL: Check for existing record FIRST
                // ============================================
                echo "<strong style='color: #0056b3;'>🔎 CHECKING DATABASE FOR EXISTING RECORD...</strong><br>";
                
                checkDatabaseConnection();
                $checkStmt = $mysqli->prepare("
                    SELECT ProductID, ProductModuleLoc, rtid, itemnumber,
                           trackingnumber, trackingnumber2, trackingnumber3, 
                           trackingnumber4, carrier, shipdate,
                           listedcondition, itemstatus, delivery_status, 
                           datedelivered, estimated_deliverydate
                    FROM tblproduct 
                    WHERE rtid = ? AND itemnumber = ?
                    LIMIT 1
                ");
                
                if (!$checkStmt) {
                    throw new Exception("Failed to prepare check statement: " . $mysqli->error);
                }
                
                $checkStmt->bind_param("ss", $orderID, $itemID);
                echo "   Searching: rtid='{$orderID}' AND itemnumber='{$itemID}'<br>";
                
                if (!$checkStmt->execute()) {
                    throw new Exception("Failed to execute check: " . $checkStmt->error);
                }
                
                $result = $checkStmt->get_result();
                $existingRecord = $result->fetch_assoc();
                $checkStmt->close();

                // ============================================
                // DECISION POINT: Existing vs New Record
                // ============================================
                if ($existingRecord) {
                    // EXISTING RECORD FOUND
                    echo "<div style='background: #fff3cd; padding: 8px; border-left: 4px solid #ffc107;'>";
                    echo "<strong>✅ EXISTING RECORD FOUND</strong><br>";
                    echo "   ProductID: {$existingRecord['ProductID']}<br>";
                    echo "   Module: <strong>{$existingRecord['ProductModuleLoc']}</strong><br>";
                    echo "   <strong style='color: #dc3545;'>🚫 WILL NOT INSERT (Duplicate Prevention)</strong><br>";
                    echo "</div>";
                    
                    $preventedDuplicates++;
                    
                    // Only update if in Orders module
                    if ($existingRecord['ProductModuleLoc'] === 'Orders') {
                        echo "<div style='background: #d1ecf1; padding: 8px; margin-top: 5px;'>";
                        echo "<strong>📝 Orders Module - Applying Smart Updates</strong><br>";
                        
                        // Extract tracking data from current order
                        $newTrackingNumber1 = !empty($order['tracking_number1']) ? trim($order['tracking_number1']) : '';
                        $newTrackingNumber2 = !empty($order['tracking_number2']) ? trim($order['tracking_number2']) : '';
                        $newTrackingNumber3 = !empty($order['tracking_number3']) ? trim($order['tracking_number3']) : '';
                        $newTrackingNumber4 = !empty($order['tracking_number4']) ? trim($order['tracking_number4']) : '';
                        $newCarrier = !empty($order['shipping_carrier']) ? trim($order['shipping_carrier']) : '';
                        $newShipDate = isset($order['shipped_time']) ? date('Y-m-d H:i:s', strtotime($order['shipped_time'])) : null;
                        $sellerLocation = isset($order['locationdetails']) && $order['locationdetails'] !== 'N/A' ? $order['locationdetails'] : 'N/A';
                        
                        $updateFields = [];
                        $updateValues = [];
                        $updateTypes = "";
                        
                        // Build update fields for tracking
                        if (!empty($newTrackingNumber1)) {
                            $updateFields[] = "trackingnumber = ?";
                            $updateValues[] = $newTrackingNumber1;
                            $updateTypes .= "s";
                            echo "   → Tracking1: '{$newTrackingNumber1}'<br>";
                        }
                        
                        if (!empty($newTrackingNumber2)) {
                            $updateFields[] = "trackingnumber2 = ?";
                            $updateValues[] = $newTrackingNumber2;
                            $updateTypes .= "s";
                            echo "   → Tracking2: '{$newTrackingNumber2}'<br>";
                        }
                        
                        if (!empty($newTrackingNumber3)) {
                            $updateFields[] = "trackingnumber3 = ?";
                            $updateValues[] = $newTrackingNumber3;
                            $updateTypes .= "s";
                            echo "   → Tracking3: '{$newTrackingNumber3}'<br>";
                        }
                        
                        if (!empty($newTrackingNumber4)) {
                            $updateFields[] = "trackingnumber4 = ?";
                            $updateValues[] = $newTrackingNumber4;
                            $updateTypes .= "s";
                            echo "   → Tracking4: '{$newTrackingNumber4}'<br>";
                        }
                        
                        if (!empty($newCarrier)) {
                            $updateFields[] = "carrier = ?";
                            $updateValues[] = $newCarrier;
                            $updateTypes .= "s";
                            echo "   → Carrier: '{$newCarrier}'<br>";
                        }
                        
                        if ($newShipDate) {
                            $updateFields[] = "shipdate = ?";
                            $updateValues[] = $newShipDate;
                            $updateTypes .= "s";
                            echo "   → ShipDate: '{$newShipDate}'<br>";
                        }
                        
                        // ========================================
                        // ✅ DELIVERY STATUS UPDATE
                        // ========================================
                        
                        $newDeliveryStatus = !empty($order['delivery_status']) ? trim($order['delivery_status']) : '';
                        $existingDeliveryStatus = isset($existingRecord['delivery_status']) ? trim($existingRecord['delivery_status']) : '';

                        $finalStatuses = ['Delivered', 'Cancelled', 'Refunded'];

                        // ✅ CRITICAL FIX: Only update if we have a REAL status (not Unknown, Not Found)
                        $ignoredStatuses = ['Unknown', 'Not Found', ''];

                        if (!empty($newDeliveryStatus) && 
                            !in_array($newDeliveryStatus, $ignoredStatuses) &&
                            $newDeliveryStatus !== $existingDeliveryStatus &&
                            !in_array($existingDeliveryStatus, $finalStatuses)) {
                            
                            $updateFields[] = "delivery_status = ?";
                            $updateValues[] = $newDeliveryStatus;
                            $updateTypes .= "s";
                            
                            echo "   → Delivery Status: '<strong style='color: #28a745;'>{$newDeliveryStatus}</strong>' ";
                            echo "(was: '{$existingDeliveryStatus}')<br>";
                            
                        } elseif (in_array($existingDeliveryStatus, $finalStatuses)) {
                            echo "   ⛔ Delivery Status NOT updated - already in final state: '<strong>{$existingDeliveryStatus}</strong>'<br>";
                            
                        } elseif (in_array($newDeliveryStatus, $ignoredStatuses)) {
                            echo "   ⏭️ Skipping delivery status update - new status is '{$newDeliveryStatus}'<br>";
                            
                        } elseif (!empty($existingDeliveryStatus) && $newDeliveryStatus === $existingDeliveryStatus) {
                            echo "   ℹ️ Delivery Status unchanged: '{$existingDeliveryStatus}'<br>";
                            
                        } else {
                            echo "   ℹ️ No delivery status to update<br>";
                        }
                        
                        // ========================================
                        // ✅ ACTUAL DELIVERED DATE (from 17track only)
                        // ✅ CRITICAL FIX: Only update if status is "Delivered"
                        // ========================================
                        $actualDeliveredDate = isset($order['actual_delivered_date']) ? $order['actual_delivered_date'] : null;
                        $existingDeliveredDate = isset($existingRecord['datedelivered']) ? trim($existingRecord['datedelivered']) : '';
                        
                        // ✅ Check if the NEW delivery status is "Delivered"
                        $isDelivered = (
                            $newDeliveryStatus === 'Delivered' || 
                            stripos($newDeliveryStatus, 'Delivered') !== false
                        );
                        
                        // Only update datedelivered if:
                        // 1. Status is "Delivered" (not "In Transit", "Awaiting Shipment", etc.)
                        // 2. We have ACTUAL date from 17track
                        // 3. No existing date in DB (or it's empty/zero date)
                        if ($isDelivered && 
                            !empty($actualDeliveredDate) && 
                            (empty($existingDeliveredDate) || $existingDeliveredDate === '0000-00-00 00:00:00')) {
                            
                            $updateFields[] = "datedelivered = ?";
                            $updateValues[] = $actualDeliveredDate;
                            $updateTypes .= "s";
                            echo "   → 📅 ACTUAL Delivered Date: '<strong style='color: #28a745;'>{$actualDeliveredDate}</strong>' (from 17track, status: {$newDeliveryStatus})<br>";
                            
                        } elseif (!$isDelivered && !empty($actualDeliveredDate)) {
                            // ✅ IMPORTANT: Don't set date if status isn't "Delivered"
                            echo "   ⚠️ NOT setting delivered date - Status is '<strong>{$newDeliveryStatus}</strong>' (not Delivered)<br>";
                            echo "      Available date ignored: {$actualDeliveredDate}<br>";
                            
                        } elseif (!empty($existingDeliveredDate) && $existingDeliveredDate !== '0000-00-00 00:00:00') {
                            echo "   ✓ ACTUAL Delivered Date already set: '<strong>{$existingDeliveredDate}</strong>' (NOT overwriting)<br>";
                            
                        } elseif ($isDelivered && empty($actualDeliveredDate)) {
                            echo "   ℹ️ Status is Delivered but no actual date from 17track yet<br>";
                        }
                        
                        // ========================================
                        // ✅ ESTIMATED DELIVERY DATE (from eBay)
                        // ========================================
                        $newEstimatedDate = isset($order['estimated_deliverydate']) ? $order['estimated_deliverydate'] : null;
                        $existingEstimatedDate = isset($existingRecord['estimated_deliverydate']) ? trim($existingRecord['estimated_deliverydate']) : '';
                        
                        // Update estimated date if:
                        // 1. We have a new estimate from eBay
                        // 2. New estimate is different from existing
                        // 3. No actual delivered date exists yet (once actual exists, estimate is irrelevant)
                        if (!empty($newEstimatedDate) && 
                            $newEstimatedDate !== $existingEstimatedDate &&
                            (empty($existingDeliveredDate) || $existingDeliveredDate === '0000-00-00 00:00:00')) {
                            
                            $updateFields[] = "estimated_deliverydate = ?";
                            $updateValues[] = $newEstimatedDate;
                            $updateTypes .= "s";
                            echo "   → 📅 Estimated Delivery: '<strong>{$newEstimatedDate}</strong>' (from eBay)<br>";
                            
                        } elseif (!empty($existingDeliveredDate) && $existingDeliveredDate !== '0000-00-00 00:00:00') {
                            echo "   ℹ️ Estimated Date not needed - ACTUAL delivered date exists: '{$existingDeliveredDate}'<br>";
                        } elseif (!empty($existingEstimatedDate) && $newEstimatedDate === $existingEstimatedDate) {
                            echo "   ℹ️ Estimated Delivery unchanged: '{$existingEstimatedDate}'<br>";
                        } elseif (empty($newEstimatedDate)) {
                            echo "   ℹ️ No estimated delivery date from eBay<br>";
                        }
                        
                        // ========================================
                        // Other order fields
                        // ========================================
                        $paymentDate = isset($order['paid_time']) ? date('Y-m-d H:i:s', strtotime($order['paid_time'])) : null;
                        $paymentMethod = 'eBay';
                        $sellerName = $order['seller_user_id'] ?? 'N/A';
                        $createdTime = isset($order['created_time']) ? date('Y-m-d H:i:s', strtotime($order['created_time'])) : null;
                        
                        if ($paymentDate) {
                            $updateFields[] = "paymentdate = ?";
                            $updateValues[] = $paymentDate;
                            $updateTypes .= "s";
                        }
                        
                        if ($paymentMethod !== 'N/A') {
                            $updateFields[] = "paymentmethod = ?";
                            $updateValues[] = $paymentMethod;
                            $updateTypes .= "s";
                        }
                        
                        if ($sellerName !== 'N/A') {
                            $updateFields[] = "seller = ?";
                            $updateValues[] = $sellerName;
                            $updateTypes .= "s";
                        }
                        
                        $updateFields[] = "Ebay_seller_location = ?";
                        $updateValues[] = $sellerLocation;
                        $updateTypes .= "s";
                        
                        if ($createdTime) {
                            $updateFields[] = "orderdate = ?";
                            $updateValues[] = $createdTime;
                            $updateTypes .= "s";
                        }
                        
                        // Check condition update
                        $currentCondition = isset($existingRecord['listedcondition']) ? trim($existingRecord['listedcondition']) : '';
                        
                        if (empty($currentCondition) || $currentCondition === 'N/A') {
                            try {
                                $credentials = EbayCredentials();
                                $accessToken = $credentials['access_token'];
                                $itemDetails = fetchItemDetails($itemID, $accessToken);
                                
                                if ($itemDetails !== false && isset($itemDetails['Item'])) {
                                    $conditionDisplay = getConditionDisplay($itemDetails);
                                    
                                    if ($conditionDisplay !== 'N/A' && !empty($conditionDisplay)) {
                                        $updateFields[] = "listedcondition = ?";
                                        $updateValues[] = $conditionDisplay;
                                        $updateTypes .= "s";
                                        
                                        $newItemStatus = 'Working';
                                        $appliedCondition = 'Condition based';
                                        
                                        if ($conditionDisplay === 'For parts or not working') {
                                            $newItemStatus = 'Not Working';
                                        }
                                        
                                        $updateFields[] = "itemstatus = ?";
                                        $updateValues[] = $newItemStatus;
                                        $updateTypes .= "s";
                                        
                                        $updateFields[] = "conditionStatusApplied = ?";
                                        $updateValues[] = $appliedCondition;
                                        $updateTypes .= "s";
                                        
                                        echo "   → Condition: '{$conditionDisplay}', Status: '{$newItemStatus}'<br>";
                                    }
                                }
                            } catch (Exception $e) {
                                echo "   ⚠️ Condition fetch error: " . $e->getMessage() . "<br>";
                            }
                        }
                        
                        // Execute update
                        if (!empty($updateFields)) {
                            checkDatabaseConnection();
                            
                            $updateSQL = "UPDATE tblproduct SET " . implode(", ", $updateFields) . " WHERE rtid = ? AND itemnumber = ?";
                            $updateValues[] = $orderID;
                            $updateValues[] = $itemID;
                            $updateTypes .= "ss";
                            
                            $updateStmt = $mysqli->prepare($updateSQL);
                            if (!$updateStmt) {
                                throw new Exception("Failed to prepare update: " . $mysqli->error);
                            }
                            
                            $updateStmt->bind_param($updateTypes, ...$updateValues);
                            
                            if ($updateStmt->execute()) {
                                $affectedRows = $updateStmt->affected_rows;
                                echo "<strong style='color: #28a745;'>✅ UPDATE SUCCESSFUL</strong> (Rows: {$affectedRows})<br>";
                                
                                $existingProductID = $existingRecord['ProductID'];
                                smartImageUpdateForExistingRecord($existingProductID, $itemID);
                                
                                $trackingUpdatedCount++;
                                $smartUpdatedCount++;
                            } else {
                                throw new Exception("Update failed: " . $updateStmt->error);
                            }
                            $updateStmt->close();
                        } else {
                            echo "   ℹ️ No fields to update<br>";
                        }
                        
                        echo "</div>"; // End Orders module update div
                        
                        $existingRecordsUpdated++;
                        
                    } else {
                        // NON-ORDERS MODULE
                        echo "<div style='background: #f8d7da; padding: 8px; margin-top: 5px;'>";
                        echo "<strong>⏭️ Non-Orders Module ('{$existingRecord['ProductModuleLoc']}') - NO UPDATES</strong><br>";
                        echo "   Record exists in different module, skipping all operations<br>";
                        echo "</div>";
                        $skippedCount++;
                    }
                    
                } else {
                    // NO EXISTING RECORD - Safe to insert
                    echo "<div style='background: #d4edda; padding: 8px; border-left: 4px solid #28a745;'>";
                    echo "<strong>🆕 NO EXISTING RECORD FOUND</strong><br>";
                    
                    if ($orderStatus !== 'Completed') {
                        echo "   <strong>⏭️ SKIPPING INSERT</strong><br>";
                        echo "   Reason: Order status is '{$orderStatus}' (not Completed)<br>";
                        echo "</div>";
                        $skippedCount++;
                    } else {
                        echo "   <strong style='color: #28a745;'>✅ ORDER IS COMPLETED</strong><br>";
                        echo "   🆕 Proceeding with INSERT...<br>";
                        echo "</div>";
                        
                        // Insert new record
                        insertNewRecord($order, $item, $orderID, $itemID, $title);
                        echo "<strong style='color: #28a745;'>✅ INSERT COMPLETED</strong><br>";
                        $newRecordsCount++;
                    }
                }
                
                echo "</div>"; // End item div
                echo "<br>";
            }
            
            $currentProcessed++;
            $totalProcessed++;
            
            // Save progress with page index every few items
            if ($currentProcessed % 5 == 0) {
                saveProgress($orderID, isset($itemID) ? $itemID : null, $currentPage, $totalProcessed, $i + 1, false);
                echo "💾 <strong>CHECKPOINT SAVED</strong><br><br>";
            }
            
            // Small delay between items
            usleep(100000); // 0.1 second delay
            
        } catch (Exception $e) {
            $errorCount++;
            echo "<div style='background: #f8d7da; padding: 10px; border: 2px solid #dc3545;'>";
            echo "<strong>❌ ERROR:</strong> " . $e->getMessage() . "<br>";
            echo "</div>";
            
            // If it's a MySQL error, force reconnection
            if (strpos($e->getMessage(), 'MySQL') !== false || 
                strpos($e->getMessage(), 'gone away') !== false ||
                strpos($e->getMessage(), 'Lost connection') !== false) {
                echo "🔄 MySQL connection issue detected - Reconnecting...<br>";
                checkDatabaseConnection();
            }
            
            $currentProcessed++;
            $totalProcessed++;
            
            // Still save progress even on error
            saveProgress($orderID, isset($itemID) ? $itemID : null, $currentPage, $totalProcessed, $i + 1, false);
            
            continue;
        }
    }
    
    // Mark this page as completed and move to next page
    echo "<hr>";
    echo "✅ Page $currentPage processing completed<br>";
    saveProgress(null, null, $currentPage + 1, $totalProcessed, 0, true);
    
    echo "<br><div style='background: #e7f3ff; padding: 15px; border: 2px solid #007bff;'>";
    echo "<strong>=== PAGE $currentPage SUMMARY ===</strong><br>";
    echo "📊 Orders processed: <strong>{$currentProcessed}</strong><br>";
    echo "✅ Smart updates: <strong>{$smartUpdatedCount}</strong><br>";
    echo "🆕 New records: <strong>{$newRecordsCount}</strong><br>";
    echo "🚫 Prevented duplicates: <strong style='color: #dc3545;'>{$preventedDuplicates}</strong><br>";
    echo "⏭️ Skipped: <strong>{$skippedCount}</strong><br>";
    echo "❌ Errors: <strong>{$errorCount}</strong><br>";
    echo "</div>";
    
    return $currentProcessed;
}

// === ENHANCED IMAGE PROCESSING FROM V1 ===
function shouldFetchImagesForUpdate($productID) {
    global $mysqli;
    
    // Check connection before query
    checkDatabaseConnection();
    
    $checkStmt = $mysqli->prepare("
        SELECT img1, img2, img3, img4, img5 
        FROM tblproduct 
        WHERE ProductID = ? 
        LIMIT 1
    ");
    
    $checkStmt->bind_param("i", $productID);
    $checkStmt->execute();
    $result = $checkStmt->get_result();
    $row = $result->fetch_assoc();
    $checkStmt->close();
    
    // If no images exist, we should fetch
    if (!$row || (empty($row['img1']) && empty($row['img2']) && empty($row['img3']) && empty($row['img4']) && empty($row['img5']))) {
        return true;
    }
    
    return false; // Already has images, skip fetching
}

function smartImageUpdateForExistingRecord($existingProductID, $itemID) {
    global $mysqli;
    
    // Only fetch images if record doesn't have any
    if (shouldFetchImagesForUpdate($existingProductID)) {
        echo "No images found for existing ProductID: {$existingProductID}, fetching...<br>";
        
        try {
            $credentials = EbayCredentials();
            $accessToken = $credentials['access_token'];
            $itemDetails = fetchItemDetails($itemID, $accessToken);
            
            if ($itemDetails !== false && isset($itemDetails['Item']['PictureDetails']['PictureURL'])) {
                echo "Processing images for existing ProductID: {$existingProductID}<br>";
                saveEbayImages($existingProductID, $itemDetails['Item']['PictureDetails']['PictureURL']);
            } else {
                echo "Could not fetch item details for image update - Item ID: {$itemID}<br>";
            }
        } catch (Exception $e) {
            echo "Exception while fetching item details for update: " . $e->getMessage() . "<br>";
        }
    } else {
        echo "Existing ProductID: {$existingProductID} already has images, skipping fetch<br>";
    }
}

function insertNewRecord($order, $item, $orderID, $itemID, $title) {
    global $mysqli;
    
    // Check connection before insert
    checkDatabaseConnection();
    
    $createdTime = $order['created_time'] ? date('Y-m-d H:i:s', strtotime($order['created_time'])) : null;
    $shippedTime = $order['shipped_time'] ? date('Y-m-d H:i:s', strtotime($order['shipped_time'])) : null;
    $paymentDate = $order['paid_time'] ? date('Y-m-d H:i:s', strtotime($order['paid_time'])) : null;
    
    // Get delivery status first
    $deliveryStatus = $order['delivery_status'] ?? 'Unknown';
    
    // ========================================
    // ✅ CRITICAL FIX: Only set datedelivered if status is "Delivered"
    // ========================================
    $actualDeliveredDate = null;
    
    // Check if status is actually "Delivered" (handle variations)
    $isDelivered = (
        $deliveryStatus === 'Delivered' || 
        stripos($deliveryStatus, 'Delivered') !== false
    );
    
    if ($isDelivered && !empty($order['actual_delivered_date'])) {
        $actualDeliveredDate = $order['actual_delivered_date'];
        echo "DEBUG: Status is '{$deliveryStatus}' - Setting ACTUAL delivered date: $actualDeliveredDate<br>";
    } else {
        if (!empty($order['actual_delivered_date'])) {
            echo "DEBUG: Status is '{$deliveryStatus}' (NOT Delivered) - SKIPPING actual delivered date (was: {$order['actual_delivered_date']})<br>";
        } else {
            echo "DEBUG: Status is '{$deliveryStatus}' - No actual delivered date available<br>";
        }
    }
    
    // ========================================
    // ESTIMATED delivery date (from eBay) - always store if available
    // ========================================
    $estimatedDeliveryDate = null;
    if (!empty($order['estimated_deliverydate'])) {
        $estimatedDeliveryDate = $order['estimated_deliverydate'];
        echo "DEBUG: Using ESTIMATED delivery range from eBay: $estimatedDeliveryDate<br>";
    }

    $total = $order['total'] ?? 0.00;
    $sellerName = $order['seller_user_id'];
    $moduleLoc = 'Orders';
    $fetchStatus = 'eBAYAPI';
    $quantityPurchased = $item['quantity_purchased'];
    $transactionPrice = $item['item_details']['Item']['SellingStatus']['CurrentPrice'] ?? 0.00;
    $materialType = 'Inventory';
    $trackingNumber1 = $order['tracking_number1'] ?? null;
    $trackingNumber2 = $order['tracking_number2'] ?? null;
    $trackingNumber3 = $order['tracking_number3'] ?? null;
    $trackingNumber4 = $order['tracking_number4'] ?? null;
    $shippingCarrierUsed = $order['shipping_carrier'] ?? null;
    $PaymentMethod = 'eBay';
    $tax = 0.00;
    $DiscountedPrice = 0.00;
    $shippingPrice = $order['shipping_cost'] ?? 0.00;
    $sellerNotes = '';
    $locationdetails = $order['locationdetails'];

    // Enhanced condition detection
    $conditionDisplay = 'N/A';
    if (isset($item['item_details']['Item']['ConditionDisplayName'])) {
        $conditionDisplay = $item['item_details']['Item']['ConditionDisplayName'];
    } else {
        $conditionDisplay = getConditionDisplay($item['item_details']);
    }

    $itemDescription = '';
    if (!empty($item['item_details']['Item']['Description'])) {
        $htmlDescription = $item['item_details']['Item']['Description'];
        $itemDescription = strip_tags($htmlDescription);
        $itemDescription = str_replace(["'", '"', "\n", "\r"], "", $itemDescription);
        $itemDescription = trim($itemDescription);
    }

    if (isset($item['item_details']['Item']['ConditionDescription'])) {
        $sellerNotes = str_replace(["'", '"'], "", $item['item_details']['Item']['ConditionDescription']);
    }

    // Enhanced item status logic
    $keywords = db_fetch_all("SELECT descriptionStatus FROM tblItemstatus");
    $itemStatus = 'Working';
    $appliedCondition = '';
    
    // Check condition first
    if ($conditionDisplay === 'For parts or not working') {
        $itemStatus = 'Not Working';
        $appliedCondition = 'Condition based';
    } else {
        // Check title keywords
        foreach ($keywords as $row) {
            $keyword = strtolower($row['descriptionStatus']);
            if (stripos($title, $keyword) !== false) {
                $itemStatus = 'Not Working';
                $appliedCondition = 'Title keyword match';
                break;
            }
        }
        
        // Check description keywords if still working
        if ($itemStatus === 'Working' && !empty($itemDescription) && $itemDescription !== 'N/A') {
            $descriptionLength = strlen($itemDescription);
            $minDescriptionLength = 150;
            
            if ($descriptionLength >= $minDescriptionLength) {
                $cutOffPoint = (int) ($descriptionLength * 0.8);
                $topMiddleDescription = substr($itemDescription, 0, $cutOffPoint);
                $appliedCondition = '80% applied';
            } else {
                $topMiddleDescription = $itemDescription;
                $appliedCondition = '80% not applied';
            }
            
            foreach ($keywords as $row) {
                $keyword = strtolower($row['descriptionStatus']);
                if (stripos($topMiddleDescription, $keyword) !== false) {
                    $itemStatus = 'Not Working';
                    $appliedCondition .= ' - Description keyword match';
                    break;
                }
            }
        }
    }

    $rtcounter = fetchRtCounter();
    
    $stmt = $mysqli->prepare("INSERT INTO tblproduct (rtid, itemnumber, ProductTitle, orderdate, total, quantity, price, Discount, priceshipping, tax, trackingnumber, trackingnumber2, trackingnumber3, trackingnumber4, carrier, listedcondition, seller, shipdate, paymentdate, rtcounter, description, notes, paymentmethod, datedelivered, estimated_deliverydate, itemstatus, conditionStatusApplied, fetchStatus, ProductModuleLoc, materialtype, Ebay_seller_location, delivery_status)
                      VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
    
    if (!$stmt) {
        throw new Exception("Failed to prepare insert statement: " . $mysqli->error);
    }
    
    $stmt->bind_param("ssssdiddddsssssssssissssssssssss", 
        $orderID,              // 1  - s - rtid
        $itemID,               // 2  - s - itemnumber
        $title,                // 3  - s - ProductTitle
        $createdTime,          // 4  - s - orderdate
        $total,                // 5  - d - total
        $quantityPurchased,    // 6  - i - quantity
        $transactionPrice,     // 7  - d - price
        $DiscountedPrice,      // 8  - d - Discount
        $shippingPrice,        // 9  - d - priceshipping
        $tax,                  // 10 - d - tax
        $trackingNumber1,      // 11 - s - trackingnumber
        $trackingNumber2,      // 12 - s - trackingnumber2
        $trackingNumber3,      // 13 - s - trackingnumber3
        $trackingNumber4,      // 14 - s - trackingnumber4
        $shippingCarrierUsed,  // 15 - s - carrier
        $conditionDisplay,     // 16 - s - listedcondition
        $sellerName,           // 17 - s - seller
        $shippedTime,          // 18 - s - shipdate
        $paymentDate,          // 19 - s - paymentdate
        $rtcounter,            // 20 - i - rtcounter
        $itemDescription,      // 21 - s - description
        $sellerNotes,          // 22 - s - notes
        $PaymentMethod,        // 23 - s - paymentmethod
        $actualDeliveredDate,  // 24 - s - datedelivered (NULL unless Delivered)
        $estimatedDeliveryDate,// 25 - s - estimated_deliverydate (always store)
        $itemStatus,           // 26 - s - itemstatus
        $appliedCondition,     // 27 - s - conditionStatusApplied
        $fetchStatus,          // 28 - s - fetchStatus
        $moduleLoc,            // 29 - s - ProductModuleLoc
        $materialType,         // 30 - s - materialtype
        $locationdetails,      // 31 - s - Ebay_seller_location
        $deliveryStatus        // 32 - s - delivery_status
    );

    if ($stmt->execute()) {
        $productID = $mysqli->insert_id;
        echo "✅ Inserted Order ID: $orderID (Item ID: $itemID) - ProductID: $productID<br>";
        echo "   📦 Delivery Status: <strong>$deliveryStatus</strong><br>";
        echo "   📅 Estimated Delivery: " . ($estimatedDeliveryDate ?? 'N/A') . "<br>";
        
        if ($actualDeliveredDate) {
            echo "   ✅ Actual Delivered: <strong style='color: #28a745;'>$actualDeliveredDate</strong><br>";
        } else {
            echo "   📅 Actual Delivered: N/A (status is '$deliveryStatus', not 'Delivered')<br>";
        }
        
        echo "   ✅ Item Status: $itemStatus ($appliedCondition)<br>";
        
        // Process images for new records
        if (isset($item['item_details']['Item']['PictureDetails']['PictureURL'])) {
            saveEbayImages($productID, $item['item_details']['Item']['PictureDetails']['PictureURL']);
        }
    } else {
        throw new Exception("Failed to insert record: " . $stmt->error);
    }
    
    $stmt->close();
}

function sendEbayRequest($accessToken, $pageNumber)
{
    // Enhanced to include ModTime filter for tracking updates like V1
    $createTimeFrom = (new DateTime('-30 days', new DateTimeZone('UTC')))->format(DATE_ATOM);
    $createTimeTo = (new DateTime('now', new DateTimeZone('UTC')))->format(DATE_ATOM);
    
    // ADDED: ModTime filter to catch recently modified orders (last 7 days) for tracking updates
    $modTimeFrom = (new DateTime('-7 days', new DateTimeZone('UTC')))->format(DATE_ATOM);

    $requestBody = '<?xml version="1.0" encoding="utf-8"?>
    <GetOrdersRequest xmlns="urn:ebay:apis:eBLBaseComponents">
        <RequesterCredentials>
            <eBayAuthToken>' . $accessToken . '</eBayAuthToken>
        </RequesterCredentials>
        <CreateTimeFrom>' . $createTimeFrom . '</CreateTimeFrom>
        <CreateTimeTo>' . $createTimeTo . '</CreateTimeTo>
        <ModTimeFrom>' . $modTimeFrom . '</ModTimeFrom>
        <ModTimeTo>' . $createTimeTo . '</ModTimeTo>
        <OrderRole>Buyer</OrderRole>
        <DetailLevel>ReturnAll</DetailLevel>
        <Pagination>
            <EntriesPerPage>100</EntriesPerPage>
            <PageNumber>' . $pageNumber . '</PageNumber>
        </Pagination>
        <OutputSelector>OrderArray.Order.OrderID</OutputSelector>
        <OutputSelector>OrderArray.Order.OrderStatus</OutputSelector>
        <OutputSelector>OrderArray.Order.PaidTime</OutputSelector>
        <OutputSelector>OrderArray.Order.AmountPaid</OutputSelector>
        <OutputSelector>OrderArray.Order.CreatedTime</OutputSelector>
        <OutputSelector>OrderArray.Order.ShippingServiceSelected.ShippingServiceCost</OutputSelector>
        <OutputSelector>OrderArray.Order.Subtotal</OutputSelector>
        <OutputSelector>OrderArray.Order.Total</OutputSelector>
        <OutputSelector>OrderArray.Order.TransactionArray.Transaction.Taxes.TaxDetails.TaxAmount</OutputSelector>
        <OutputSelector>OrderArray.Order.TransactionArray.Transaction.TransactionID</OutputSelector>
        <OutputSelector>OrderArray.Order.TransactionArray.Transaction.Item.ItemID</OutputSelector>
        <OutputSelector>OrderArray.Order.TransactionArray.Transaction.Item.Title</OutputSelector>
        <OutputSelector>OrderArray.Order.TransactionArray.Transaction.QuantityPurchased</OutputSelector>
        <OutputSelector>OrderArray.Order.TransactionArray.Transaction.SellerDiscounts.SellerDiscount.ItemDiscountAmount</OutputSelector>
        <OutputSelector>OrderArray.Order.TransactionArray.Transaction.TransactionPrice</OutputSelector>
        <OutputSelector>OrderArray.Order.ShippedTime</OutputSelector>
        <OutputSelector>OrderArray.Order.SellerUserID</OutputSelector>
        <OutputSelector>OrderArray.Order.SellerEmail</OutputSelector>
        <OutputSelector>OrderArray.Order.Seller.RegistrationAddress</OutputSelector>
        <OutputSelector>OrderArray.Order.ShippingAddress</OutputSelector>
        <OutputSelector>OrderArray.Order.TransactionArray.Transaction.ShippingDetails.ShipmentTrackingDetails.ShipmentTrackingNumber</OutputSelector>
        <OutputSelector>OrderArray.Order.TransactionArray.Transaction.ShippingDetails.ShipmentTrackingDetails.ShippingCarrierUsed</OutputSelector>
        <OutputSelector>OrderArray.Order.TransactionArray.Transaction.Item.ConditionDisplayName</OutputSelector>
        <OutputSelector>OrderArray.Order.TransactionArray.Transaction.ShippingServiceSelected.ShippingPackageInfo.EstimatedDeliveryTimeMin</OutputSelector>
        <OutputSelector>OrderArray.Order.TransactionArray.Transaction.ShippingServiceSelected.ShippingPackageInfo.EstimatedDeliveryTimeMax</OutputSelector>
        <OutputSelector>OrderArray.Order.CheckoutStatus.PaymentMethod</OutputSelector>
    </GetOrdersRequest>';

    return sendRequest($requestBody, 'GetOrders');
}

function handleEbayErrors($errors, $serverconfig, $credentials)
{
    // Handle case where $errors is a single error (not an array of errors)
    if (isset($errors['ErrorCode'])) {
        $errors = [$errors]; // Wrap in array
    }
    
    foreach ($errors as $error) {
        if (!is_array($error)) {
            echo "⚠️ Unexpected error format: " . print_r($error, true) . "<br>";
            continue;
        }
        
        $errorCode = $error['ErrorCode'] ?? null;
        
        if ($errorCode == '931') {
            echo "❌ Invalid eBay auth token.<br>";
            if ($serverconfig === 'LIVE') {
                echo "🔄 Refreshing token...<br>";
                $newAccessToken = refreshEbayAccessToken($credentials);
                if (!$newAccessToken) {
                    echo "❌ Refresh failed.<br>";
                    return;
                }
                echo "✅ Token refreshed. Retrying fetchOrdersCron...<br>";
                fetchOrdersCron();
                return;
            }
            echo "⚠️ Invalid token (not LIVE mode).<br>";
            return;
        } elseif ($errorCode == '932') {
            echo "❌ Auth token hard expired.<br>";
            return;
        } elseif ($errorCode == '518' || $errorCode == '21916653') {
            echo "❌ API call usage limit reached. Stopping.<br>";
            return;
        } else {
            echo "❌ eBay error code {$errorCode}: " . ($error['ShortMessage'] ?? 'Unknown') . "<br>";
        }
    }
}

function sendRequest($requestBody, $apiCallName)
{
    $apiEndpoint = 'https://api.ebay.com/ws/api.dll';

    $apiHeaders = [
        'X-EBAY-API-SITEID: 0',
        'X-EBAY-API-COMPATIBILITY-LEVEL: 967',
        'X-EBAY-API-CALL-NAME: ' . $apiCallName,
        'Content-Type: text/xml',
    ];

    $curl = curl_init();
    curl_setopt($curl, CURLOPT_URL, $apiEndpoint);
    curl_setopt($curl, CURLOPT_POST, true);
    curl_setopt($curl, CURLOPT_POSTFIELDS, $requestBody);
    curl_setopt($curl, CURLOPT_HTTPHEADER, $apiHeaders);
    curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($curl, CURLOPT_TIMEOUT, 60);
    curl_setopt($curl, CURLOPT_CONNECTTIMEOUT, 30);

    $response = curl_exec($curl);
    $error = curl_error($curl);
    curl_close($curl);

    if ($error) {
        echo 'cURL Error: ' . $error . '<br>';
        return null;
    }

    echo "📦 Raw XML Response for API Call: $apiCallName<br>";

    $xml = simplexml_load_string($response);
    if (!$xml) {
        echo '❌ Invalid XML Response from eBay<br>';
        return null;
    }

    return json_decode(json_encode($xml), true);
}

function processOrders($response, $accessToken)
{
    global $mysqli;
    
    if (empty($response['OrderArray']['Order'])) {
        return [];
    }

    $orders = $response['OrderArray']['Order'];
    
    // ✅ FIX: Handle single order (not wrapped in array)
    if (isset($orders['OrderID'])) {
        $orders = [$orders];
    }
    
    $processedOrders = [];
    $exchangeRates = fetchExchangeRates('f5d29ab775a644eca3f13e4c');

    // ========================================
    // PHASE 1: Build all orders (eBay data only, NO 17track calls)
    // ========================================
    echo "<br>=== PHASE 1: Processing eBay order data ===<br>";
    
    foreach ($orders as $order) {
        $currency = $order['AmountPaid']['@currencyID'] ?? 'USD';
        $ebayorderid = $order['OrderID'] ?? null;
        $amountPaid = $order['AmountPaid'] ?? 0;
        $amountPaidInUSD = convertToUSD($amountPaid, $currency, $exchangeRates);

        $preshippingServiceCost = $order['ShippingServiceSelected']['ShippingServiceCost'] ?? 0;
        
        // ========================================
        // Extract ESTIMATED delivery range from eBay
        // ========================================
        $estimatedDeliveryMin = null;
        $estimatedDeliveryMax = null;
        $estimatedDeliveryRange = null;
        
        if (isset($order['TransactionArray']['Transaction']['ShippingServiceSelected']['ShippingPackageInfo']['EstimatedDeliveryTimeMin'])) {
            $estimatedDeliveryMin = $order['TransactionArray']['Transaction']['ShippingServiceSelected']['ShippingPackageInfo']['EstimatedDeliveryTimeMin'];
            echo "DEBUG: Found EstimatedDeliveryTimeMin = '{$estimatedDeliveryMin}' for Order: {$order['OrderID']}<br>";
        }
        
        if (isset($order['TransactionArray']['Transaction']['ShippingServiceSelected']['ShippingPackageInfo']['EstimatedDeliveryTimeMax'])) {
            $estimatedDeliveryMax = $order['TransactionArray']['Transaction']['ShippingServiceSelected']['ShippingPackageInfo']['EstimatedDeliveryTimeMax'];
            echo "DEBUG: Found EstimatedDeliveryTimeMax = '{$estimatedDeliveryMax}' for Order: {$order['OrderID']}<br>";
        }
        
        // Format as range
        if ($estimatedDeliveryMin && $estimatedDeliveryMax) {
            try {
                $minDate = new DateTime($estimatedDeliveryMin);
                $maxDate = new DateTime($estimatedDeliveryMax);
                
                $minFormatted = $minDate->format('Y-m-d');
                $maxFormatted = $maxDate->format('Y-m-d');
                
                if ($minFormatted === $maxFormatted) {
                    $estimatedDeliveryRange = $minFormatted;
                } else {
                    $estimatedDeliveryRange = $minFormatted . ' to ' . $maxFormatted;
                }
                
                echo "DEBUG: Estimated delivery range: '{$estimatedDeliveryRange}' for Order: {$order['OrderID']}<br>";
            } catch (Exception $e) {
                echo "DEBUG: Failed to parse estimated delivery dates: {$e->getMessage()}<br>";
            }
        } elseif ($estimatedDeliveryMax) {
            try {
                $maxDate = new DateTime($estimatedDeliveryMax);
                $estimatedDeliveryRange = $maxDate->format('Y-m-d');
                echo "DEBUG: Estimated delivery (MAX only): '{$estimatedDeliveryRange}' for Order: {$order['OrderID']}<br>";
            } catch (Exception $e) {
                echo "DEBUG: Failed to parse MAX date: {$e->getMessage()}<br>";
            }
        } else {
            echo "DEBUG: No estimated delivery date found for Order: {$order['OrderID']}<br>";
        }
        
        $shipping_currency = $currency;
        $shippingServiceCost = convertToUSD($preshippingServiceCost, $shipping_currency, $exchangeRates);

        $trackingNumber1 = $trackingNumber2 = $trackingNumber3 = $trackingNumber4 = '';
        $shippingCarrier = '';
        $items = [];
        $locationDetails = 'N/A';

        // Tracking Details
        if (isset($order['TransactionArray']['Transaction']['ShippingDetails']['ShipmentTrackingDetails'])) {
            $trackingDetails = $order['TransactionArray']['Transaction']['ShippingDetails']['ShipmentTrackingDetails'];
            $isArray = isset($trackingDetails[0]);

            for ($i = 0; $i <= 4; $i++) {
                $numField = 'trackingNumber' . ($i + 1);
                if ($isArray && isset($trackingDetails[$i]['ShipmentTrackingNumber'])) {
                    ${$numField} = $trackingDetails[$i]['ShipmentTrackingNumber'];
                } elseif (!$isArray && $i === 0 && isset($trackingDetails['ShipmentTrackingNumber'])) {
                    $trackingNumber1 = $trackingDetails['ShipmentTrackingNumber'];
                }
            }

            if ($isArray && isset($trackingDetails[0]['ShippingCarrierUsed'])) {
                $shippingCarrier = $trackingDetails[0]['ShippingCarrierUsed'];
            } elseif (!$isArray && isset($trackingDetails['ShippingCarrierUsed'])) {
                $shippingCarrier = $trackingDetails['ShippingCarrierUsed'];
            }
        }

        // Transactions
        if (!empty($order['TransactionArray']['Transaction'])) {
            $transactions = $order['TransactionArray']['Transaction'];
            if (!isset($transactions[0]))
                $transactions = [$transactions];

            foreach ($transactions as $transaction) {
                if (!is_array($transaction) || !isset($transaction['Item'])) {
                    continue;
                }

                $itemId = $transaction['Item']['ItemID'] ?? null;
                if (!$itemId) {
                    continue;
                }

                $itemDetails = fetchItemDetails($itemId, $accessToken);
                $locationDetails = getItemLocation($itemId, $accessToken);

                $items[] = [
                    'transaction_id' => $transaction['TransactionID'] ?? null,
                    'item_id' => $itemId,
                    'title' => $transaction['Item']['Title'] ?? null,
                    'quantity_purchased' => $transaction['QuantityPurchased'] ?? null,
                    'item_details' => $itemDetails
                ];
            }
        }

        // Build processed order (WITHOUT delivery status — that comes in Phase 2)
        $processedOrder = [
            'order_id' => $order['OrderID'] ?? null,
            'order_status' => $order['OrderStatus'] ?? null,
            'paid_time' => $order['PaidTime'] ?? null,
            'amount_paid' => $amountPaidInUSD,
            'created_time' => $order['CreatedTime'] ?? null,
            'shipping_cost' => $shippingServiceCost,
            'subtotal' => $order['Subtotal'] ?? null,
            'total' => $order['Total'] ?? null,
            'seller_user_id' => $order['SellerUserID'] ?? null,
            'seller_email' => $order['SellerEmail'] ?? null,
            'shipped_time' => $order['ShippedTime'] ?? null,
            'shipping_address' => isset($order['ShippingAddress']) ? json_encode($order['ShippingAddress']) : null,
            'tracking_number1' => $trackingNumber1,
            'tracking_number2' => $trackingNumber2,
            'tracking_number3' => $trackingNumber3,
            'tracking_number4' => $trackingNumber4,
            'shipping_carrier' => $shippingCarrier,
            'items' => $items,
            'locationdetails' => $locationDetails,
            'estimated_deliverydate' => $estimatedDeliveryRange,
            // Placeholders — filled in Phase 2
            'delivery_status' => 'Unknown',
            'actual_delivered_date' => null,
        ];

        $processedOrders[] = $processedOrder;
    }

    // ========================================
    // PHASE 2: BATCH 17track lookup (ONE call per 40 tracking numbers)
    // Instead of 1 API call per order, we do 1 call per 40 orders
    // ========================================
    echo "<br>=== PHASE 2: Batch 17track delivery status lookup ===<br>";
    echo "Total orders to check: " . count($processedOrders) . "<br>";
    
    $trackingResults = batch17TrackLookup($processedOrders);
    
    echo "17track results received: " . count($trackingResults) . " tracking numbers resolved<br>";
    
    // ✅ BULK UPDATE: Update ALL rows in tblproduct that share the same tracking number
    // This ensures multi-item orders and any other rows with the same tracking get updated
    bulkUpdateDeliveryStatusByTracking($trackingResults);

    // ========================================
    // PHASE 3: Attach 17track results to each order
    // ========================================
    echo "<br>=== PHASE 3: Attaching delivery statuses to orders ===<br>";
    
    foreach ($processedOrders as $idx => &$po) {
        $tn = trim($po['tracking_number1'] ?? '');
        $orderId = $po['order_id'];
        
        if (!empty($tn) && isset($trackingResults[$tn])) {
            // Got result from 17track batch or DB cache
            $result = $trackingResults[$tn];
            $po['delivery_status'] = $result['status'] ?? 'Unknown';
            
            if (isset($result['delivered_date']) && !empty($result['delivered_date'])) {
                $po['actual_delivered_date'] = $result['delivered_date'];
                echo "   📅 Order {$orderId}: Status=<strong>{$po['delivery_status']}</strong>, Delivered={$result['delivered_date']}<br>";
            } else {
                echo "   📦 Order {$orderId}: Status=<strong>{$po['delivery_status']}</strong><br>";
            }
            
        } else {
            // No tracking number or no result — use fallback estimate
            $fallbackStatus = getEstimateFallbackStatus($po);
            
            // fallbackStatus can be a string or array
            if (is_array($fallbackStatus)) {
                $po['delivery_status'] = $fallbackStatus['status'] ?? 'Unknown';
                if (isset($fallbackStatus['delivered_date'])) {
                    $po['actual_delivered_date'] = $fallbackStatus['delivered_date'];
                }
            } else {
                $po['delivery_status'] = $fallbackStatus;
            }
            
            echo "   🔄 Order {$orderId}: Status=<strong>{$po['delivery_status']}</strong> (fallback";
            if (empty($tn)) {
                echo ", no tracking number";
            }
            echo ")<br>";
        }
        
        // Log summary
        if (!empty($po['estimated_deliverydate'])) {
            echo "      Estimated delivery (eBay): {$po['estimated_deliverydate']}<br>";
        }
    }
    unset($po); // break reference
    
    echo "<br>=== Order processing complete: " . count($processedOrders) . " orders ready ===<br>";

    return $processedOrders;
}

// === ADDITIONAL HELPER FUNCTIONS FROM V1 ===

function getConditionDisplay($itemDetails) {
    if (!$itemDetails || !isset($itemDetails['Item'])) {
        echo "No item details available for condition check<br>";
        return 'N/A';
    }
    
    $conditionDisplay = 'N/A';
    
    try {
        if (isset($itemDetails['Item']['ConditionDisplayName'])) {
            $conditionDisplay = trim($itemDetails['Item']['ConditionDisplayName']);
        }
        
        if (empty($conditionDisplay) || $conditionDisplay === 'N/A') {
            if (isset($itemDetails['Item']['ConditionID'])) {
                $conditionId = (int) $itemDetails['Item']['ConditionID'];
                $conditionDisplay = mapConditionIdToDisplayName($conditionId);
            }
        }
        
    } catch (Exception $e) {
        echo "Exception while getting condition display: " . $e->getMessage() . "<br>";
        return 'N/A';
    }
    
    return !empty($conditionDisplay) ? $conditionDisplay : 'N/A';
}

function mapConditionIdToDisplayName($conditionId) {
    $conditionMap = [
        1000 => 'New',
        1500 => 'New other (see details)',
        1750 => 'New with defects',
        2000 => 'Manufacturer refurbished',
        2500 => 'Seller refurbished',
        3000 => 'Used',
        4000 => 'Very Good',
        5000 => 'Good',
        6000 => 'Acceptable',
        7000 => 'For parts or not working'
    ];
    
    return isset($conditionMap[$conditionId]) ? $conditionMap[$conditionId] : 'N/A';
}

function formatDate($dateString)
{
    if (empty($dateString)) {
        return null;
    }
    
    $datePart = substr($dateString, 0, 10);
    $date = DateTime::createFromFormat('Y-m-d', $datePart);
    if ($date !== false) {
        return $date->format('Y-m-d');
    } else {
        return null;
    }
}

function downloadImageWithRetry($url, $maxRetries = 2, $timeout = 20) {
    $retryCount = 0;
    
    while ($retryCount < $maxRetries) {
        try {
            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, $url);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
            curl_setopt($ch, CURLOPT_TIMEOUT, $timeout);
            curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 5);
            curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36');
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
            curl_setopt($ch, CURLOPT_MAXREDIRS, 3);
            
            $imageContent = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $curlError = curl_error($ch);
            curl_close($ch);
            
            if ($imageContent === false) {
                throw new Exception("cURL error: $curlError");
            }
            
            if ($httpCode !== 200) {
                throw new Exception("HTTP error: $httpCode");
            }
            
            return $imageContent;
            
        } catch (Exception $e) {
            $retryCount++;
            echo "Attempt $retryCount failed for $url: " . $e->getMessage() . "<br>";
            
            if ($retryCount < $maxRetries) {
                echo "Retrying in 1 second...<br>";
                sleep(1);
            }
        }
    }
    
    return false;
}

function clearProgress() {
    global $progressFile;
    if (file_exists($progressFile)) {
        unlink($progressFile);
        echo "Progress file cleared. Next run will start from the beginning.<br>";
    }
}

function saveEbayImages($productID, $imageUrls)
{
    global $mysqli;

    // Check connection before image processing
    checkDatabaseConnection();

    if (!is_array($imageUrls)) {
        $imageUrls = [$imageUrls];
    }

    $imageUrls = array_slice($imageUrls, 0, 5); // Limit to 5

    $imageDir = '/home/imsv2/public_html/laravel_ims/public/images/thumbnails';
    if (!file_exists($imageDir)) {
        mkdir($imageDir, 0755, true);
    }

    if (!is_writable($imageDir)) {
        echo "Error: Image directory is not writable: $imageDir<br>";
        return false;
    }

    $successCount = 0;
    $totalCount = count($imageUrls);

    foreach ($imageUrls as $index => $imageUrl) {
        try {
            if (empty($imageUrl) || !filter_var($imageUrl, FILTER_VALIDATE_URL)) {
                echo "Invalid image URL at index $index: $imageUrl<br>";
                continue;
            }
            
            $imageName = $productID . ($index > 0 ? "_$index" : "") . ".jpg";
            $imagePath = $imageDir . '/' . $imageName;

            $imageContent = downloadImageWithRetry($imageUrl, 2);
            
            if ($imageContent === false) {
                echo "Failed to download image from: $imageUrl<br>";
                continue;
            }
            
            if (strlen($imageContent) < 100) {
                echo "Downloaded image too small (likely error page): $imageUrl<br>";
                continue;
            }

            if (file_put_contents($imagePath, $imageContent) === false) {
                echo "Error writing image file at: $imagePath<br>";
                continue;
            }
            
            if (!file_exists($imagePath) || filesize($imagePath) == 0) {
                echo "Image file not properly saved: $imagePath<br>";
                if (file_exists($imagePath)) {
                    unlink($imagePath);
                }
                continue;
            }
            
            $imageInfo = getimagesize($imagePath);
            if ($imageInfo === false) {
                echo "Downloaded file is not a valid image: $imagePath<br>";
                unlink($imagePath);
                continue;
            }

            // Check connection before image update
            checkDatabaseConnection();

            $imgField = "img" . ($index + 1);
            $stmt = $mysqli->prepare("UPDATE tblproduct SET $imgField = ? WHERE ProductID = ?");
            
            if ($stmt === false) {
                echo "Error preparing image update statement: " . $mysqli->error . "<br>";
                continue;
            }
            
            $stmt->bind_param("si", $imageName, $productID);
            
            if (!$stmt->execute()) {
                echo "Error updating image field {$imgField}: " . $stmt->error . "<br>";
                $stmt->close();
                continue;
            }
            
            $stmt->close();
            $successCount++;
            echo "📷 Saved image $imageName for ProductID: $productID<br>";

        } catch (Exception $e) {
            echo "Exception while processing image $index for ProductID $productID: " . $e->getMessage() . "<br>";
            continue;
        }
    }
    
    echo "Image processing complete for ProductID $productID: $successCount/$totalCount images saved successfully<br>";
    return $successCount > 0;
}

function fetchItemDetails($itemId, $accessToken)
{
    if (!$itemId) {
        echo "❌ fetchItemDetails: Item ID is missing.<br>";
        return null;
    }

    // CRITICAL: Check connection before API call
    checkDatabaseConnection();

    static $callCount = 0;
    static $lastCallTime = 0;
    static $dailyCallCount = 0;
    static $lastResetDate = null;
    static $consecutiveFailures = 0;
    
    // Reset daily counter if it's a new day
    $currentDate = date('Y-m-d');
    if ($lastResetDate !== $currentDate) {
        $dailyCallCount = 0;
        $lastResetDate = $currentDate;
        $consecutiveFailures = 0;
        echo "Daily API call counter reset for date: $currentDate<br>";
    }
    
    // Daily limit check
    if ($dailyCallCount >= 2000) {
        echo "Daily API limit reached ($dailyCallCount calls). Stopping for today.<br>";
        return false;
    }
    
    // Rate limiting
    $currentTime = time();
    $timeSinceLastCall = $currentTime - $lastCallTime;
    $requiredDelay = API_CALL_DELAY;
    
    if ($timeSinceLastCall < $requiredDelay && $lastCallTime > 0) {
        $sleepTime = $requiredDelay - $timeSinceLastCall;
        echo "Rate limiting: waiting {$sleepTime} seconds before API call for Item ID: $itemId<br>";
        sleep($sleepTime);
    }
    
    $callCount++;
    $dailyCallCount++;
    echo "API Call #{$callCount} (Daily: {$dailyCallCount}) for Item ID: $itemId<br>";

    $requestBody = '<?xml version="1.0" encoding="utf-8"?>
    <GetItemRequest xmlns="urn:ebay:apis:eBLBaseComponents">
        <RequesterCredentials>
            <eBayAuthToken>' . $accessToken . '</eBayAuthToken>
        </RequesterCredentials>
        <ItemID>' . htmlspecialchars($itemId) . '</ItemID>
        <DetailLevel>ReturnAll</DetailLevel>
        <IncludeItemSpecifics>true</IncludeItemSpecifics>
        <IncludeWatchCount>true</IncludeWatchCount>
        <IncludeCrossPromotion>false</IncludeCrossPromotion>
        <IncludeItemCompatibilityList>false</IncludeItemCompatibilityList>
    </GetItemRequest>';

    $maxRetries = 2;
    $retryCount = 0;
    
    while ($retryCount < $maxRetries) {
        $curl = curl_init();
        curl_setopt($curl, CURLOPT_URL, 'https://api.ebay.com/ws/api.dll');
        curl_setopt($curl, CURLOPT_POST, true);
        curl_setopt($curl, CURLOPT_POSTFIELDS, $requestBody);
        curl_setopt($curl, CURLOPT_HTTPHEADER, [
            'X-EBAY-API-SITEID: 0',
            'X-EBAY-API-COMPATIBILITY-LEVEL: 967',
            'X-EBAY-API-CALL-NAME: GetItem',
        ]);
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($curl, CURLOPT_TIMEOUT, 30);
        curl_setopt($curl, CURLOPT_CONNECTTIMEOUT, 10);
        curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($curl, CURLOPT_SSL_VERIFYHOST, false);
        curl_setopt($curl, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($curl, CURLOPT_MAXREDIRS, 3);
        curl_setopt($curl, CURLOPT_USERAGENT, 'eBayAPI-PHP-Client/1.0');
        
        $response = curl_exec($curl);
        $httpCode = curl_getinfo($curl, CURLINFO_HTTP_CODE);
        $curlError = curl_error($curl);
        $curlErrno = curl_errno($curl);
        curl_close($curl);

        $lastCallTime = time();

        if ($response === false || !empty($curlError)) {
            $retryCount++;
            echo "cURL Error (attempt $retryCount/$maxRetries) for Item ID $itemId: Error #$curlErrno - $curlError<br>";
            
            if ($retryCount < $maxRetries) {
                $retryDelay = $retryCount * 3;
                echo "Retrying in $retryDelay seconds...<br>";
                sleep($retryDelay);
                continue;
            } else {
                $consecutiveFailures++;
                echo "Max retries reached for Item ID $itemId due to cURL errors.<br>";
                return false;
            }
        }

        if ($httpCode !== 200) {
            echo "HTTP Error $httpCode for Item ID $itemId<br>";
            
            switch ($httpCode) {
                case 429:
                    echo "Rate limit exceeded. Waiting 60 seconds before continuing...<br>";
                    sleep(60);
                    $retryCount++;
                    if ($retryCount < $maxRetries) {
                        continue;
                    }
                    break;
                    
                case 500:
                case 502:
                case 503:
                case 504:
                    $retryCount++;
                    if ($retryCount < $maxRetries) {
                        $retryDelay = $retryCount * 5;
                        echo "Server error. Retrying in $retryDelay seconds...<br>";
                        sleep($retryDelay);
                        continue;
                    }
                    break;
                    
                default:
                    $consecutiveFailures++;
                    return false;
            }
            
            if ($retryCount >= $maxRetries) {
                $consecutiveFailures++;
                return false;
            }
        }
        
        break;
    }

    if (empty($response)) {
        echo "Empty response received for Item ID $itemId<br>";
        $consecutiveFailures++;
        return false;
    }

    libxml_use_internal_errors(true);
    $xml = simplexml_load_string($response);
    $xmlErrors = libxml_get_errors();
    
    if ($xml === false) {
        echo "XML Parse Error for Item ID $itemId:<br>";
        foreach ($xmlErrors as $error) {
            echo "- " . trim($error->message) . "<br>";
        }
        libxml_clear_errors();
        $consecutiveFailures++;
        return false;
    }
    
    libxml_clear_errors();
    
    if (isset($xml->Errors)) {
        $errorCode = (string)$xml->Errors->ErrorCode;
        $errorMessage = (string)$xml->Errors->ShortMessage;
        $longMessage = isset($xml->Errors->LongMessage) ? (string)$xml->Errors->LongMessage : '';
        
        echo "eBay API Error for Item ID $itemId: Code $errorCode - $errorMessage<br>";
        if (!empty($longMessage)) {
            echo "Details: $longMessage<br>";
        }
        
        switch ($errorCode) {
            case '17':
            case '1047':
                echo "Rate limiting detected. Waiting 60 seconds...<br>";
                sleep(60);
                $consecutiveFailures++;
                return false;
                
            case '291':
            case '1':
                echo "Item $itemId not found or ended - this is normal for older items.<br>";
                $consecutiveFailures = max(0, $consecutiveFailures - 1);
                return false;
                
            case '21916653':
                echo "Application request limit exceeded. Stopping API calls for today.<br>";
                $dailyCallCount = 2000;
                return false;
                
            default:
                echo "Unhandled eBay API error code: $errorCode<br>";
                $consecutiveFailures++;
                return false;
        }
    }
    
    if (!isset($xml->Item)) {
        echo "No Item element found in response for Item ID $itemId<br>";
        $consecutiveFailures++;
        return false;
    }
    
    $consecutiveFailures = 0;
    echo "Successfully fetched details for Item ID: $itemId<br>";
    
    // Convert XML to array format like V2 expects
    return json_decode(json_encode($xml), true);
}

function getItemLocation($itemId, $accessToken)
{
    if (!$itemId) {
        echo "❌ getItemLocation: Item ID is missing.<br>";
        return "N/A";
    }

    // Check connection before API call
    checkDatabaseConnection();

    $requestBody = '<?xml version="1.0" encoding="utf-8"?>
    <GetItemRequest xmlns="urn:ebay:apis:eBLBaseComponents">
        <RequesterCredentials>
            <eBayAuthToken>' . $accessToken . '</eBayAuthToken>
        </RequesterCredentials>
        <ItemID>' . $itemId . '</ItemID>
        <DetailLevel>ReturnAll</DetailLevel>
    </GetItemRequest>';

    $response = sendRequest($requestBody, 'GetItem');

    if (!$response || !isset($response['Item']['Location'])) {
        echo "⚠️ getItemLocation: Could not retrieve location for item ID: $itemId<br>";
        return "N/A";
    }

    $itemLocation = $response['Item']['Location'] ?? '';
    $itemCountry = $response['Item']['Country'] ?? '';

    return $itemCountry && stripos($itemLocation, $itemCountry) === false
        ? "$itemLocation, $itemCountry"
        : $itemLocation;
}

function fetchExchangeRates($apiKey)
{
    $url = "https://v6.exchangerate-api.com/v6/$apiKey/latest/USD";
    
    $context = stream_context_create([
        'http' => [
            'timeout' => 15,
        ]
    ]);
    
    $response = @file_get_contents($url, false, $context);
    
    if ($response === false) {
        echo "WARNING: Exchange rate API timeout. Using USD-only mode.<br>";
        return [
            'USD' => 1.0,
            'EUR' => 1.0,
            'GBP' => 1.0,
            'CAD' => 1.0
        ];
    }
    
    $data = json_decode($response, true);

    if ($data && isset($data['conversion_rates'])) {
        return $data['conversion_rates'];
    } else {
        echo "WARNING: Invalid exchange rate response. Using USD-only mode.<br>";
        return [
            'USD' => 1.0,
            'EUR' => 1.0,
            'GBP' => 1.0,
            'CAD' => 1.0
        ];
    }
}

function convertToUSD($amount, $currency, $exchangeRates)
{
    if ($currency === 'USD') {
        return number_format($amount, 2, '.', '');
    }

    if (isset($exchangeRates[$currency])) {
        return number_format($amount / $exchangeRates[$currency], 2, '.', '');
    }

    echo "⚠️ Missing exchange rate for $currency<br>";
    return $amount;
}

function cleanTitle($text)
{
    $pattern = '/[\x{1F600}-\x{1F64F}|\x{1F300}-\x{1F5FF}|\x{1F680}-\x{1F6FF}|\x{1F700}-\x{1F77F}|\x{1F780}-\x{1F7FF}|\x{1F800}-\x{1F8FF}|\x{1F900}-\x{1F9FF}|\x{1FA00}-\x{1FA6F}|\x{1FA70}-\x{1FAFF}|\x{2600}-\x{26FF}|\x{2700}-\x{27BF}]/u';
    $cleanText = preg_replace($pattern, '', $text);
    $cleanText = preg_replace('/[⭐🔥!]/u', '', $cleanText);
    $cleanText = preg_replace('/\s+/', ' ', $cleanText);
    return trim($cleanText);
}

function fetchRtCounter()
{
    $row = db_fetch_assoc("SELECT MAX(rtcounter) as maxval FROM tblproduct");
    return $row && $row['maxval'] ? $row['maxval'] + 1 : 1;
}

//// === Supporting Functions ===

function EbayCredentials()
{
    global $mysqli;

    // Check connection before query
    checkDatabaseConnection();

    $id = 3;
    $stmt = $mysqli->prepare("SELECT client_id, client_secret, access_token, refresh_token, expires_in FROM tblapis WHERE id = ?");
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $result = $stmt->get_result();

    if (!$result || $result->num_rows === 0) {
        echo "❌ No eBay credentials found for ID: $id<br>";
        return [];
    }

    $credentials = $result->fetch_assoc();
    $stmt->close();
    return $credentials;
}

function getAccessToken($authorizationCode)
{
    $tokenUrl = 'https://api.ebay.com/identity/v1/oauth2/token';
    $redirectUri = 'https://test.tecniquality.com/apis/ebay-callback';

    $credentials = EbayCredentials();
    if (!$credentials) {
        echo "❌ Failed to retrieve credentials for token request.<br>";
        return null;
    }

    $authHeader = base64_encode("{$credentials['client_id']}:{$credentials['client_secret']}");
    $data = http_build_query([
        'grant_type' => 'authorization_code',
        'code' => $authorizationCode,
        'redirect_uri' => $redirectUri,
    ]);

    $opts = [
        'http' => [
            'method' => 'POST',
            'header' => "Authorization: Basic $authHeader\r\nContent-Type: application/x-www-form-urlencoded",
            'content' => $data,
            'timeout' => 10,
        ]
    ];

    $context = stream_context_create($opts);
    $response = file_get_contents($tokenUrl, false, $context);

    if ($response === false) {
        echo "❌ Error during token request.<br>";
        return null;
    }

    $results = json_decode($response, true);
    if (isset($results['access_token'], $results['refresh_token'])) {
        saveTokens($results);
        return $results['access_token'];
    } else {
        echo "❌ Failed to obtain token:<br>";
        print_r($results);
        echo "<br>";
        return null;
    }
}

function saveTokens(array $tokens)
{
    global $mysqli;

    // Check connection before save
    checkDatabaseConnection();

    $stmt = $mysqli->prepare("UPDATE tblapis SET access_token=?, refresh_token=?, expires_in=?, updated_at=? WHERE id=3");
    $now = date('Y-m-d H:i:s');
    $stmt->bind_param("ssis", $tokens['access_token'], $tokens['refresh_token'], $tokens['expires_in'], $now);

    if ($stmt->execute()) {
        echo "✅ Tokens saved to DB.<br>";
    } else {
        echo "❌ Failed to save tokens: " . $stmt->error . "<br>";
    }

    $stmt->close();
}

function refreshEbayAccessToken($credentials)
{
    global $mysqli;

    // Check connection before query
    checkDatabaseConnection();

    $stmt = $mysqli->prepare("SELECT refresh_token FROM tblapis WHERE api_name = 'EBAY'");
    $stmt->execute();
    $result = $stmt->get_result();
    $apiRecord = $result ? $result->fetch_assoc() : null;
    $stmt->close();

    if (!$apiRecord || !$apiRecord['refresh_token']) {
        echo "❌ No refresh token found for EBAY.<br>";
        return null;
    }

    $tokenUrl = 'https://api.ebay.com/identity/v1/oauth2/token';
    $authHeader = base64_encode("{$credentials['client_id']}:{$credentials['client_secret']}");

    $data = http_build_query([
        'grant_type' => 'refresh_token',
        'refresh_token' => $apiRecord['refresh_token'],
        'scope' => implode(' ', [
            'https://api.ebay.com/oauth/api_scope',
            'https://api.ebay.com/oauth/api_scope/sell.marketing.readonly',
            'https://api.ebay.com/oauth/api_scope/sell.inventory.readonly',
            'https://api.ebay.com/oauth/api_scope/sell.account.readonly',
            'https://api.ebay.com/oauth/api_scope/sell.fulfillment.readonly',
        ])
    ]);

    $opts = [
        'http' => [
            'method' => 'POST',
            'header' => "Authorization: Basic $authHeader\r\nContent-Type: application/x-www-form-urlencoded",
            'content' => $data,
            'timeout' => 10,
        ]
    ];

    $context = stream_context_create($opts);
    $response = file_get_contents($tokenUrl, false, $context);

    if ($response === false) {
        echo "❌ Failed to contact eBay token server.<br>";
        return null;
    }

    $results = json_decode($response, true);
    if (isset($results['access_token'])) {
        $newAccessToken = $results['access_token'];
        $expiresIn = $results['expires_in'] ?? 3600;

        // Check connection before update
        checkDatabaseConnection();

        $stmt = $mysqli->prepare("UPDATE tblapis SET access_token = ?, updated_at = ? WHERE api_name = 'EBAY'");
        $now = date('Y-m-d H:i:s');
        $stmt->bind_param("ss", $newAccessToken, $now);
        $stmt->execute();
        $stmt->close();

        return $newAccessToken;
    } else {
        echo "❌ Token refresh failed:<br>";
        print_r($results);
        echo "<br>";
        return null;
    }
}


   /**
 * Check delivery status using 17track API (supports 1,700+ carriers globally)
 * Documentation: https://api.17track.net/en/doc
 */
function check17TrackDeliveryStatus($trackingNumber, $carrierCode = null)
{
    try {
        $apiKey = '5EC4C3FCD4929687DC76822C8D154C20';
        
        if (empty($apiKey)) {
            echo 'WARNING: 17track API key not configured<br>';
            return ['status' => 'Unknown', 'error' => 'API not configured'];
        }
        
        $headers = [
            '17token: ' . $apiKey,
            'Content-Type: application/json'
        ];
        
        // Step 1: Try to register tracking
        $registerUrl = 'https://api.17track.net/track/v2.2/register';
        
        $trackingData = [
            ['number' => $trackingNumber]
        ];
        
        if ($carrierCode !== null && is_int($carrierCode)) {
            $trackingData[0]['carrier'] = $carrierCode;
        }
        
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $registerUrl);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($trackingData));
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 15);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        
        $registerResponse = curl_exec($ch);
        $registerData = json_decode($registerResponse, true);
        
        $skipToGetInfo = false;
        
        if (isset($registerData['data']['rejected']) && !empty($registerData['data']['rejected'])) {
            $rejectedError = $registerData['data']['rejected'][0]['error'] ?? [];
            $errorCode = $rejectedError['code'] ?? 0;
            
            // -18019901 = "tracking number already exists" — this is FINE, just skip to gettrackinfo
            if ($errorCode == -18019901) {
                echo "   ✓ Tracking {$trackingNumber} already registered in 17track, skipping to gettrackinfo<br>";
                $skipToGetInfo = true;
            }
            // -18010011 = invalid carrier code — retry without carrier
            elseif ($errorCode == -18010011) {
                echo "   ⚠️ Invalid carrier code, retrying without carrier...<br>";
                $retryData = [['number' => $trackingNumber]];
                curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($retryData));
                $retryResponse = curl_exec($ch);
                $retryData = json_decode($retryResponse, true);
                
                // Check if retry also says "already exists"
                if (isset($retryData['data']['rejected']) && !empty($retryData['data']['rejected'])) {
                    $retryErrorCode = $retryData['data']['rejected'][0]['error']['code'] ?? 0;
                    if ($retryErrorCode == -18019901) {
                        echo "   ✓ Already registered, proceeding to gettrackinfo<br>";
                        $skipToGetInfo = true;
                    } else {
                        echo "   ❌ Registration failed even without carrier (error: {$retryErrorCode})<br>";
                        curl_close($ch);
                        return ['status' => 'Unknown', 'error' => 'Registration failed'];
                    }
                }
            }
            else {
                echo "   ❌ Unknown rejection error: {$errorCode}<br>";
                // Still try gettrackinfo — it might have been registered in a previous run
                $skipToGetInfo = true;
            }
        }
        
        // Step 2: Get tracking info (always do this)
        if (!$skipToGetInfo) {
            sleep(1); // Only delay if we just registered
        }
        
        $getTrackUrl = 'https://api.17track.net/track/v2.2/gettrackinfo';
        $getTrackPayload = json_encode([['number' => $trackingNumber]]);
        
        curl_setopt($ch, CURLOPT_URL, $getTrackUrl);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $getTrackPayload);
        
        $trackResponse = curl_exec($ch);
        $trackHttpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        if ($trackHttpCode !== 200 || !$trackResponse) {
            echo "   17track gettrackinfo error: HTTP {$trackHttpCode}<br>";
            return ['status' => 'Unknown', 'error' => 'API request failed'];
        }
        
        $trackData = json_decode($trackResponse, true);
        
        if (!isset($trackData['data']['accepted']) || empty($trackData['data']['accepted'])) {
            echo "   No tracking data found for {$trackingNumber}<br>";
            return ['status' => 'Unknown', 'error' => 'No tracking data found'];
        }
        
        $track = $trackData['data']['accepted'][0];
        $trackInfo = $track['track_info'] ?? [];
        $latestEvent = $trackInfo['latest_event'] ?? [];
        $latestStatus = $trackInfo['latest_status'] ?? [];
        
        $statusCode = $latestStatus['status'] ?? 0;
        $statusDescription = $latestEvent['description'] ?? 'Unknown';
        $eventTime = $latestEvent['time_iso'] ?? null;
        
        echo "   17track result for {$trackingNumber}: statusCode={$statusCode}, desc={$statusDescription}<br>";
        
        // Parse delivery date
        $actualDeliveredDate = null;
        if ($eventTime) {
            try {
                $dt = new DateTime($eventTime);
                $actualDeliveredDate = $dt->format('Y-m-d H:i:s');
            } catch (Exception $e) {
                echo "   ⚠️ Failed to parse date: {$eventTime}<br>";
            }
        }
        
        $carrierName = $track['provider_name'] ?? 'Unknown';
        $carrierCodeResult = $track['provider'] ?? null;
        
        switch ($statusCode) {
            case 40:
                return [
                    'status' => 'Delivered',
                    'delivered_date' => $actualDeliveredDate,
                    'raw_status' => $statusDescription,
                    'carrier' => $carrierName,
                    'source' => '17track_actual'
                ];
            case 10:
            case 20:
            case 30:
                return [
                    'status' => 'In Transit',
                    'raw_status' => $statusDescription,
                    'carrier' => $carrierName,
                    'source' => '17track'
                ];
            case 35:
            case 50:
                return [
                    'status' => 'Delivery Exception',
                    'raw_status' => $statusDescription,
                    'carrier' => $carrierName,
                    'source' => '17track'
                ];
            case 0:
                return [
                    'status' => 'Not Found',
                    'raw_status' => 'Tracking number not found',
                    'carrier' => $carrierName,
                    'source' => '17track'
                ];
            default:
                return [
                    'status' => 'Unknown',
                    'raw_status' => $statusDescription,
                    'carrier' => $carrierName,
                    'source' => '17track'
                ];
        }
        
    } catch (Exception $e) {
        echo "   17track exception: " . $e->getMessage() . "<br>";
        return ['status' => 'Unknown', 'error' => $e->getMessage()];
    }
}

/**
 * Convert carrier name from eBay to 17track carrier code
 * Documentation: https://res.17track.net/asset/carrier/info/apicarrier.all.json
 */
function getCarrierCodeFor17Track($carrierName)
{
    if (empty($carrierName)) {
        return null;
    }
    
    $carrierName = strtoupper(trim($carrierName));
    
    // Map common eBay carrier names to 17track carrier codes
    // ✅ CRITICAL: These MUST be integers
    $carrierMap = [
        // US Carriers
        'USPS' => 70001,
        'US POSTAL SERVICE' => 70001,
        'UNITED STATES POSTAL SERVICE' => 70001,
        'FEDEX' => 70002,
        'FEDERAL EXPRESS' => 70002,
        'FDX' => 70002,
        'UPS' => 70003,
        'UNITED PARCEL SERVICE' => 70003,
        'DHL' => 70004,
        'DHL EXPRESS' => 70004,
        'DHL ECOMMERCE' => 2159,
        
        // UK Carriers
        'ROYAL MAIL' => 60001,
        'PARCELFORCE' => 60002,
        
        // China Carriers
        'CHINA POST' => 20001,
        'EMS' => 20002,
        'YANWEN' => 2076,
        'SF EXPRESS' => 20021,
        'CHINA EMS' => 20002,
        
        // Other Popular
        'ONTRAC' => 2087,
        'LASERSHIP' => 2109,
        'AMAZON' => 2196,
        'AMAZON LOGISTICS' => 2196,
    ];
    
    // Try exact match first
    if (isset($carrierMap[$carrierName])) {
        $code = (int)$carrierMap[$carrierName]; // ✅ Force integer
        echo "   Carrier '{$carrierName}' mapped to code: {$code} (type: " . gettype($code) . ")<br>";
        return $code;
    }
    
    // Try partial match
    foreach ($carrierMap as $key => $code) {
        if (strpos($carrierName, $key) !== false) {
            $code = (int)$code; // ✅ Force integer
            echo "   Carrier '{$carrierName}' partially matched '{$key}' to code: {$code} (type: " . gettype($code) . ")<br>";
            return $code;
        }
    }
    
    // If no match, let 17track auto-detect
    echo "   No carrier mapping found for '{$carrierName}', using auto-detection<br>";
    return null;
}


/**
 * Get the most accurate delivery status using 17track (supports 1,700+ carriers)
 * Optimized to avoid unnecessary API calls for already-delivered/cancelled/refunded orders
 */
function getAccurateDeliveryStatusCombined($order, $accessToken)
{
    global $mysqli;
    
    $orderId = $order['order_id'];
    $trackingNumber = trim($order['tracking_number1'] ?? '');
    $carrier = trim($order['shipping_carrier'] ?? '');
    
    // ========================================
    // STEP 1: CHECK DATABASE FIRST (BEFORE API CALL)
    // ========================================
    checkDatabaseConnection();
    
    $checkStmt = $mysqli->prepare("
        SELECT delivery_status, datedelivered
        FROM tblproduct 
        WHERE rtid = ? 
        LIMIT 1
    ");
    
    if ($checkStmt) {
        $checkStmt->bind_param("s", $orderId);
        $checkStmt->execute();
        $result = $checkStmt->get_result();
        $existingRecord = $result->fetch_assoc();
        $checkStmt->close();
        
        if ($existingRecord && !empty($existingRecord['delivery_status'])) {
            $currentStatus = $existingRecord['delivery_status'];
            
            // ✅ If already in a final state, SKIP 17track API call completely
            $finalStatuses = ['Delivered', 'Cancelled', 'Refunded'];
            if (in_array($currentStatus, $finalStatuses)) {
                echo "✓ Order {$orderId} already has final status: <strong>{$currentStatus}</strong> - SKIPPING 17track API call<br>";
                
                // ✅ Return array with existing delivered date if available
                if (!empty($existingRecord['datedelivered']) && $existingRecord['datedelivered'] !== '0000-00-00 00:00:00') {
                    return [
                        'status' => $currentStatus,
                        'delivered_date' => $existingRecord['datedelivered'],
                        'source' => 'database_cache'
                    ];
                }
                
                return [
                    'status' => $currentStatus,
                    'source' => 'database_cache'
                ];
            } else {
                echo "Order {$orderId} current status: '{$currentStatus}' (not final, will check for updates)<br>";
            }
        } else {
            echo "Order {$orderId} has no delivery status yet, will check 17track<br>";
        }
    }
    
    // ========================================
    // STEP 2: Check eBay order status for Cancelled
    // ========================================
    $orderStatus = $order['order_status'] ?? '';
    
    if ($orderStatus === 'Cancelled') {
        echo "Order {$orderId} is Cancelled per eBay (no API call needed)<br>";
        return [
            'status' => 'Cancelled',
            'source' => 'ebay'
        ];
    }
    
    // ========================================
    // STEP 3: Call 17track API
    // ========================================
    if (!empty($trackingNumber)) {
        echo "🌐 Calling 17track API for Order {$orderId}: Tracking={$trackingNumber}, Carrier={$carrier}<br>";
        
        $carrierCode = getCarrierCodeFor17Track($carrier);
        
        if ($carrierCode) {
            echo "   Mapped carrier '{$carrier}' to 17track code: {$carrierCode}<br>";
        } else {
            echo "   No carrier code found for '{$carrier}', using auto-detection<br>";
        }
        
        $trackingStatus = check17TrackDeliveryStatusWithRateLimit($trackingNumber, $carrierCode);

        // ✅ CRITICAL FIX: Check the response properly
        if ($trackingStatus && is_array($trackingStatus)) {
            echo "   📦 17track response type: array<br>";
            echo "   📦 17track response: " . json_encode($trackingStatus) . "<br>";
            
            $status = $trackingStatus['status'] ?? 'Unknown';
            $hasError = isset($trackingStatus['error']);
            
            echo "   📦 Extracted status: '{$status}'<br>";
            echo "   📦 Has error: " . ($hasError ? 'YES' : 'NO') . "<br>";
            
            // ✅ Check if we got a valid status (not Unknown, Not Found, or error)
            if (!$hasError && $status !== 'Unknown' && $status !== 'Not Found') {
                echo "   ✅ Valid status from 17track: <strong>{$status}</strong><br>";
                
                if (isset($trackingStatus['delivered_date'])) {
                    echo "   📅 Delivered date: <strong>{$trackingStatus['delivered_date']}</strong><br>";
                }
                
                // ✅ RETURN THE FULL ARRAY
                return $trackingStatus;
            } else {
                echo "   ⚠️ Invalid or error status from 17track: {$status}<br>";
                if ($hasError) {
                    echo "      Error: {$trackingStatus['error']}<br>";
                }
            }
        } else {
            echo "   ⚠️ 17track response is not an array or is empty<br>";
        }
    } else {
        echo "No tracking number available for Order {$orderId}<br>";
    }
    
    // ========================================
    // STEP 4: Fall back to basic logic
    // ========================================
    echo "   🔄 Falling back to estimate logic<br>";
    
    if (!empty($order['shipped_time'])) {
        $shippedDate = new DateTime($order['shipped_time']);
        $now = new DateTime();
        $daysSinceShipped = $now->diff($shippedDate)->days;
        
        if ($daysSinceShipped >= 14) {
            echo "   → Estimated as Delivered (shipped {$daysSinceShipped} days ago)<br>";
            return [
                'status' => 'Delivered (Estimated)',
                'source' => 'estimate',
                'days_since_shipped' => $daysSinceShipped
            ];
        }
        
        echo "   → Status: In Transit (shipped {$daysSinceShipped} days ago)<br>";
        return [
            'status' => 'In Transit',
            'source' => 'estimate',
            'days_since_shipped' => $daysSinceShipped
        ];
    }
    
    if (!empty($order['paid_time']) && empty($order['shipped_time'])) {
        echo "   → Status: Awaiting Shipment<br>";
        return [
            'status' => 'Awaiting Shipment',
            'source' => 'ebay'
        ];
    }
    
    if (empty($order['paid_time'])) {
        echo "   → Status: Payment Pending<br>";
        return [
            'status' => 'Payment Pending',
            'source' => 'ebay'
        ];
    }
    
    echo "   → Status: Active (default)<br>";
    return [
        'status' => 'Active',
        'source' => 'default'
    ];
}


/**
 * Check delivery status with rate limiting
 */
function check17TrackDeliveryStatusWithRateLimit($trackingNumber, $carrierCode = null)
{
    // Simple file-based cache (1 hour)
    $cacheDir = '/home/imsv2/public_html/laravel_ims/automations/cache';
    if (!file_exists($cacheDir)) {
        mkdir($cacheDir, 0755, true);
    }
    
    $cacheKey = md5("track17_{$trackingNumber}");
    $cacheFile = $cacheDir . '/' . $cacheKey . '.json';
    
    // Check cache
    if (file_exists($cacheFile)) {
        $cacheData = json_decode(file_get_contents($cacheFile), true);
        $cacheAge = time() - filemtime($cacheFile);
        
        if ($cacheAge < 3600) { // 1 hour
            echo "Using cached 17track result for {$trackingNumber} (age: {$cacheAge}s)<br>";
            return $cacheData;
        }
    }
    
    // Rate limiting
    static $lastRequestTime = 0;
    $now = microtime(true);
    $timeSinceLastRequest = $now - $lastRequestTime;
    
    if ($timeSinceLastRequest < 1.0) {
        $sleepTime = 1.0 - $timeSinceLastRequest;
        usleep($sleepTime * 1000000);
    }
    
    $result = check17TrackDeliveryStatus($trackingNumber, $carrierCode);
    
    $lastRequestTime = microtime(true);
    
    // Cache the result
    if ($result && $result['status'] !== 'Unknown') {
        file_put_contents($cacheFile, json_encode($result));
    }
    
    return $result;
}


function batch17TrackLookup($orders)
{
    $apiKey = '5EC4C3FCD4929687DC76822C8D154C20';
    $headers = [
        '17token: ' . $apiKey,
        'Content-Type: application/json'
    ];
    
    echo "<br><div style='background: #fff3cd; padding: 15px; border: 2px solid #ffc107;'>";
    echo "<strong>📦 BATCH 17TRACK LOOKUP - DETAILED DEBUG</strong><br>";
    echo "===========================================<br>";
    
    // Collect unique tracking numbers
    $trackingMap = []; // trackingNumber => [orderIndices]
    $results = [];     // trackingNumber => status result
    
    foreach ($orders as $idx => $order) {
        $tracking = trim($order['tracking_number1'] ?? '');
        $orderId = $order['order_id'];
        
        if (empty($tracking)) {
            echo "⚠️ Order {$orderId}: NO TRACKING NUMBER<br>";
            continue;
        }
        
        echo "📋 Order {$orderId}: Tracking = <strong>{$tracking}</strong><br>";
        
        // Check DB for final status first
        $existingStatus = getExistingDeliveryStatus($orderId);
        
        if ($existingStatus && in_array($existingStatus['status'], ['Delivered', 'Cancelled', 'Refunded'])) {
            echo "   ✅ Already '{$existingStatus['status']}' in DB - SKIPPING 17track<br>";
            $results[$tracking] = $existingStatus;
            continue;
        }
        
        if (!isset($trackingMap[$tracking])) {
            $trackingMap[$tracking] = [];
        }
        $trackingMap[$tracking][] = $idx;
    }
    
    // Filter out already-resolved
    $toLookup = array_diff_key($trackingMap, $results);
    $trackingNumbers = array_keys($toLookup);
    
    echo "<br>📊 SUMMARY:<br>";
    echo "   Total orders: " . count($orders) . "<br>";
    echo "   With tracking: " . count($trackingMap) . "<br>";
    echo "   Already resolved: " . count($results) . "<br>";
    echo "   Need 17track lookup: <strong>" . count($trackingNumbers) . "</strong><br>";
    echo "</div><br>";
    
    if (empty($trackingNumbers)) {
        echo "✅ All tracking numbers already resolved from DB<br>";
        return $results;
    }
    
    // Process in batches of 40
    $batches = array_chunk($trackingNumbers, 40);
    
    foreach ($batches as $batchIdx => $batch) {
        echo "<div style='background: #d1ecf1; padding: 10px; margin: 10px 0;'>";
        echo "<strong>🌐 17TRACK BATCH " . ($batchIdx + 1) . "/" . count($batches) . "</strong><br>";
        echo "Processing " . count($batch) . " tracking numbers...<br>";
        
        // Step 1: Register
        $registerData = [];
        foreach ($batch as $tn) {
            $registerData[] = ['number' => $tn];
        }
        
        echo "<br>📤 REGISTERING with 17track...<br>";
        
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, 'https://api.17track.net/track/v2.2/register');
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($registerData));
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        
        $registerResponse = curl_exec($ch);
        $regData = json_decode($registerResponse, true);
        
        // Debug register response
        if (isset($regData['data']['accepted'])) {
            $acceptedCount = count($regData['data']['accepted']);
            echo "   ✅ Registered: {$acceptedCount}<br>";
        }
        
        if (isset($regData['data']['rejected'])) {
            foreach ($regData['data']['rejected'] as $rej) {
                $num = $rej['number'] ?? 'unknown';
                $errCode = $rej['error']['code'] ?? 0;
                $errMsg = $rej['error']['message'] ?? 'unknown';
                
                if ($errCode == -18019901) {
                    echo "   ℹ️ {$num}: Already registered (OK)<br>";
                } else {
                    echo "   ⚠️ {$num}: REJECTED - Code {$errCode}: {$errMsg}<br>";
                }
            }
        }
        
        // Step 2: Get tracking info
        echo "<br>📥 FETCHING TRACKING INFO...<br>";
        sleep(1);
        
        $getTrackData = [];
        foreach ($batch as $tn) {
            $getTrackData[] = ['number' => $tn];
        }
        
        curl_setopt($ch, CURLOPT_URL, 'https://api.17track.net/track/v2.2/gettrackinfo');
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($getTrackData));
        
        $trackResponse = curl_exec($ch);
        $trackHttpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        echo "   HTTP Response Code: {$trackHttpCode}<br>";
        
        if ($trackHttpCode !== 200) {
            echo "   ❌ 17track API returned HTTP {$trackHttpCode}<br>";
            echo "   Response: " . substr($trackResponse, 0, 200) . "...<br>";
            continue;
        }
        
        $trackData = json_decode($trackResponse, true);
        
        if (!isset($trackData['data']['accepted'])) {
            echo "   ⚠️ No 'accepted' data in response<br>";
            echo "   Raw response: " . substr($trackResponse, 0, 300) . "...<br>";
            continue;
        }
        
        $acceptedTracks = $trackData['data']['accepted'];
        echo "   📦 Received tracking info for " . count($acceptedTracks) . " numbers<br><br>";
        
        foreach ($acceptedTracks as $track) {
            $tn = $track['number'] ?? '';
            
            if (empty($tn)) {
                echo "   ⚠️ Track result missing tracking number<br>";
                continue;
            }
            
            $trackInfo = $track['track_info'] ?? [];
            $latestEvent = $trackInfo['latest_event'] ?? [];
            $latestStatus = $trackInfo['latest_status'] ?? [];
            $statusCode = $latestStatus['status'] ?? 0;
            $eventTime = $latestEvent['time_iso'] ?? null;
            $description = $latestEvent['description'] ?? 'Unknown';
            $carrierName = $track['provider_name'] ?? 'Unknown';
            
            echo "   🔍 <strong>{$tn}</strong>:<br>";
            echo "      Status Code: {$statusCode}<br>";
            echo "      Carrier: {$carrierName}<br>";
            echo "      Description: {$description}<br>";
            
            $deliveredDate = null;
            if ($eventTime) {
                try {
                    $deliveredDate = (new DateTime($eventTime))->format('Y-m-d H:i:s');
                    echo "      Event Time: {$deliveredDate}<br>";
                } catch (Exception $e) {
                    echo "      ⚠️ Failed to parse date: {$eventTime}<br>";
                }
            }
            
            // Map status code to status text
            switch ($statusCode) {
                case 40:
                    $results[$tn] = [
                        'status' => 'Delivered',
                        'delivered_date' => $deliveredDate,
                        'carrier' => $carrierName,
                        'source' => '17track_actual'
                    ];
                    echo "      ✅ <strong style='color: #28a745;'>DELIVERED</strong>";
                    if ($deliveredDate) {
                        echo " on {$deliveredDate}";
                    }
                    echo "<br>";
                    break;
                    
                case 10:
                case 20:
                case 30:
                    $results[$tn] = [
                        'status' => 'In Transit',
                        'carrier' => $carrierName,
                        'source' => '17track'
                    ];
                    echo "      📦 <strong style='color: #007bff;'>IN TRANSIT</strong><br>";
                    break;
                    
                case 35:
                case 50:
                    $results[$tn] = [
                        'status' => 'Delivery Exception',
                        'carrier' => $carrierName,
                        'source' => '17track'
                    ];
                    echo "      ⚠️ <strong style='color: #ffc107;'>DELIVERY EXCEPTION</strong><br>";
                    break;
                    
                case 0:
                    $results[$tn] = [
                        'status' => 'Not Found',
                        'carrier' => $carrierName,
                        'source' => '17track'
                    ];
                    echo "      ❓ <strong>NOT FOUND</strong><br>";
                    break;
                    
                default:
                    $results[$tn] = [
                        'status' => 'Unknown',
                        'carrier' => $carrierName,
                        'source' => '17track'
                    ];
                    echo "      ❓ <strong>UNKNOWN STATUS ({$statusCode})</strong><br>";
                    break;
            }
            
            echo "<br>";
        }
        
        echo "</div>";
        
        // Delay between batches
        if ($batchIdx < count($batches) - 1) {
            echo "⏳ Waiting 2 seconds before next batch...<br>";
            sleep(2);
        }
    }
    
    echo "<div style='background: #d4edda; padding: 15px; border: 2px solid #28a745;'>";
    echo "<strong>✅ BATCH 17TRACK LOOKUP COMPLETE</strong><br>";
    echo "Total results: " . count($results) . "<br>";
    
    // Show breakdown
    $statusCounts = [];
    foreach ($results as $result) {
        $status = $result['status'];
        if (!isset($statusCounts[$status])) {
            $statusCounts[$status] = 0;
        }
        $statusCounts[$status]++;
    }
    
    echo "<br>Status breakdown:<br>";
    foreach ($statusCounts as $status => $count) {
        echo "   {$status}: {$count}<br>";
    }
    echo "</div><br>";
    
    return $results;
}

/**
 * Helper: Check DB for existing delivery status
 */
function getExistingDeliveryStatus($orderId)
{
    global $mysqli;
    checkDatabaseConnection();
    
    $stmt = $mysqli->prepare("SELECT delivery_status, datedelivered FROM tblproduct WHERE rtid = ? LIMIT 1");
    if (!$stmt) return null;
    
    $stmt->bind_param("s", $orderId);
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result->fetch_assoc();
    $stmt->close();
    
    if ($row && !empty($row['delivery_status'])) {
        return [
            'status' => $row['delivery_status'],
            'delivered_date' => (!empty($row['datedelivered']) && $row['datedelivered'] !== '0000-00-00 00:00:00') 
                ? $row['datedelivered'] : null,
            'source' => 'database_cache'
        ];
    }
    
    return null;
}

/**
 * Simple fallback when no tracking number exists
 */
function getEstimateFallbackStatus($order)
{
    if (($order['order_status'] ?? '') === 'Cancelled') {
        return 'Cancelled';
    }
    
    if (!empty($order['shipped_time'])) {
        $shippedDate = new DateTime($order['shipped_time']);
        $daysSince = (new DateTime())->diff($shippedDate)->days;
        return $daysSince >= 14 ? 'Delivered (Estimated)' : 'In Transit';
    }
    
    if (!empty($order['paid_time']) && empty($order['shipped_time'])) {
        return 'Awaiting Shipment';
    }
    
    return 'Unknown';
}

function bulkUpdateDeliveryStatusByTracking($trackingResults)
{
    global $mysqli;
    
    if (empty($trackingResults)) {
        echo "No tracking results to bulk update<br>";
        return 0;
    }
    
    $totalUpdated = 0;
    $skippedFinal = 0;
    $skippedUnchanged = 0;
    
    $finalStatuses = ['Delivered', 'Cancelled', 'Refunded'];
    
    echo "<br>=== BULK TRACKING UPDATE: Updating ALL rows matching each tracking number ===<br>";
    
    foreach ($trackingResults as $trackingNumber => $result) {
        $newStatus = $result['status'] ?? 'Unknown';
        $deliveredDate = $result['delivered_date'] ?? null;
        
        // Skip if the result itself is Unknown or Not Found
        if ($newStatus === 'Unknown' || $newStatus === 'Not Found') {
            continue;
        }
        
        checkDatabaseConnection();
        
        // Find ALL rows with this tracking number (check all 4 tracking fields)
        $findStmt = $mysqli->prepare("
            SELECT ProductID, rtid, itemnumber, delivery_status, datedelivered, ProductModuleLoc
            FROM tblproduct 
            WHERE (trackingnumber = ? OR trackingnumber2 = ? OR trackingnumber3 = ? OR trackingnumber4 = ?)
        ");
        
        if (!$findStmt) {
            echo "   ❌ Failed to prepare find statement: " . $mysqli->error . "<br>";
            continue;
        }
        
        $findStmt->bind_param("ssss", $trackingNumber, $trackingNumber, $trackingNumber, $trackingNumber);
        $findStmt->execute();
        $findResult = $findStmt->get_result();
        $matchingRows = $findResult->fetch_all(MYSQLI_ASSOC);
        $findStmt->close();
        
        if (empty($matchingRows)) {
            continue; // No rows with this tracking number
        }
        
        $rowCount = count($matchingRows);
        if ($rowCount > 1) {
            echo "   📦 Tracking {$trackingNumber}: Found <strong>{$rowCount} rows</strong> sharing this tracking<br>";
        }
        
        foreach ($matchingRows as $row) {
            $productID = $row['ProductID'];
            $existingStatus = trim($row['delivery_status'] ?? '');
            $existingDelivered = trim($row['datedelivered'] ?? '');
            $moduleLoc = $row['ProductModuleLoc'] ?? '';
            
            // Skip non-Orders module
            if ($moduleLoc !== 'Orders') {
                continue;
            }
            
            // Skip if already in a final state
            if (in_array($existingStatus, $finalStatuses)) {
                $skippedFinal++;
                continue;
            }
            
            // Skip if status is the same
            if ($existingStatus === $newStatus) {
                $skippedUnchanged++;
                continue;
            }
            
            // Build update
            $updateFields = [];
            $updateValues = [];
            $updateTypes = "";
            
            // Update delivery status
            $updateFields[] = "delivery_status = ?";
            $updateValues[] = $newStatus;
            $updateTypes .= "s";
            
            // Update datedelivered ONLY if status is Delivered AND we have a date AND no existing date
            $isDelivered = ($newStatus === 'Delivered' || stripos($newStatus, 'Delivered') !== false);
            $hasNoExistingDate = (empty($existingDelivered) || $existingDelivered === '0000-00-00 00:00:00');
            
            if ($isDelivered && !empty($deliveredDate) && $hasNoExistingDate) {
                $updateFields[] = "datedelivered = ?";
                $updateValues[] = $deliveredDate;
                $updateTypes .= "s";
            }
            
            if (!empty($updateFields)) {
                checkDatabaseConnection();
                
                $updateSQL = "UPDATE tblproduct SET " . implode(", ", $updateFields) . " WHERE ProductID = ?";
                $updateValues[] = $productID;
                $updateTypes .= "i";
                
                $updateStmt = $mysqli->prepare($updateSQL);
                if (!$updateStmt) {
                    echo "   ❌ Failed to prepare update for ProductID {$productID}: " . $mysqli->error . "<br>";
                    continue;
                }
                
                $updateStmt->bind_param($updateTypes, ...$updateValues);
                
                if ($updateStmt->execute()) {
                    $affected = $updateStmt->affected_rows;
                    if ($affected > 0) {
                        $totalUpdated++;
                        echo "   ✅ ProductID {$productID} (Order: {$row['rtid']}): '{$existingStatus}' → '<strong>{$newStatus}</strong>'";
                        if ($isDelivered && !empty($deliveredDate) && $hasNoExistingDate) {
                            echo " | Date: {$deliveredDate}";
                        }
                        echo "<br>";
                    }
                } else {
                    echo "   ❌ Update failed for ProductID {$productID}: " . $updateStmt->error . "<br>";
                }
                $updateStmt->close();
            }
        }
    }
    
    echo "<br><div style='background: #e7f3ff; padding: 10px; border: 2px solid #007bff;'>";
    echo "<strong>=== BULK TRACKING UPDATE SUMMARY ===</strong><br>";
    echo "✅ Rows updated: <strong>{$totalUpdated}</strong><br>";
    echo "⏭️ Skipped (already final): <strong>{$skippedFinal}</strong><br>";
    echo "⏭️ Skipped (unchanged): <strong>{$skippedUnchanged}</strong><br>";
    echo "</div><br>";
    
    return $totalUpdated;
}
?>