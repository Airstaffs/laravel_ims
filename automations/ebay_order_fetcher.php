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
    
    if ($currentPage == $progress['current_page'] && !$progress['page_completed']) {
        $pageOrderIndex = $progress['page_order_index'];
        echo "Resuming page $currentPage from order index $pageOrderIndex<br>";
    } else {
        echo "Starting fresh processing for page $currentPage<br>";
    }
    
    echo "=== PROCESSING PAGE $currentPage ===<br>";
    echo "Total orders on this page: " . count($pageOrders) . "<br>";
    echo "Starting from index: $pageOrderIndex<br>";
    echo "MODE: SMART PROCESSING - Insert new, Update existing (NO tracking status updates)<br>";
    echo "====================================<br><br>";
    
    $currentProcessed = 0;
    $skippedCount = 0;
    $errorCount = 0;
    $newRecordsCount = 0;
    $existingRecordsUpdated = 0;
    $preventedDuplicates = 0;
    
    for ($i = $pageOrderIndex; $i < count($pageOrders); $i++) {
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

                // Check for existing record
                checkDatabaseConnection();
                $checkStmt = $mysqli->prepare("
                    SELECT ProductID, ProductModuleLoc, rtid, itemnumber,
                           trackingnumber, trackingnumber2, trackingnumber3, trackingnumber4,
                           carrier, shipdate, listedcondition, itemstatus
                    FROM tblproduct 
                    WHERE rtid = ? AND itemnumber = ?
                    LIMIT 1
                ");
                
                if (!$checkStmt) {
                    throw new Exception("Failed to prepare check statement: " . $mysqli->error);
                }
                
                $checkStmt->bind_param("ss", $orderID, $itemID);
                
                if (!$checkStmt->execute()) {
                    throw new Exception("Failed to execute check: " . $checkStmt->error);
                }
                
                $result = $checkStmt->get_result();
                $existingRecord = $result->fetch_assoc();
                $checkStmt->close();

                if ($existingRecord) {
                    // EXISTING RECORD
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
                        echo "<strong>📝 Orders Module - Applying Updates</strong><br>";
                        
                        // Extract data from current order
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
                        
                        // Update tracking numbers
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
                        
                        // ✅ REMOVED: ALL delivery_status and datedelivered update logic
                        
                        // Update estimated delivery date
                        $newEstimatedDate = isset($order['estimated_deliverydate']) ? $order['estimated_deliverydate'] : null;
                        
                        if (!empty($newEstimatedDate)) {
                            $updateFields[] = "estimated_deliverydate = ?";
                            $updateValues[] = $newEstimatedDate;
                            $updateTypes .= "s";
                            echo "   → 📅 Estimated Delivery: '{$newEstimatedDate}'<br>";
                        }
                        
                        // Other order fields
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
                            } else {
                                throw new Exception("Update failed: " . $updateStmt->error);
                            }
                            $updateStmt->close();
                        } else {
                            echo "   ℹ️ No fields to update<br>";
                        }
                        
                        echo "</div>";
                        $existingRecordsUpdated++;
                        
                    } else {
                        // NON-ORDERS MODULE
                        echo "<div style='background: #f8d7da; padding: 8px; margin-top: 5px;'>";
                        echo "<strong>⏭️ Non-Orders Module ('{$existingRecord['ProductModuleLoc']}') - NO UPDATES</strong><br>";
                        echo "</div>";
                        $skippedCount++;
                    }
                    
                } else {
                    // NO EXISTING RECORD
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
                        
                        insertNewRecord($order, $item, $orderID, $itemID, $title);
                        echo "<strong style='color: #28a745;'>✅ INSERT COMPLETED</strong><br>";
                        $newRecordsCount++;
                    }
                }
                
                echo "</div>";
                echo "<br>";
            }
            
            $currentProcessed++;
            $totalProcessed++;
            
            if ($currentProcessed % 5 == 0) {
                saveProgress($orderID, isset($itemID) ? $itemID : null, $currentPage, $totalProcessed, $i + 1, false);
                echo "💾 <strong>CHECKPOINT SAVED</strong><br><br>";
            }
            
            usleep(100000);
            
        } catch (Exception $e) {
            $errorCount++;
            echo "<div style='background: #f8d7da; padding: 10px; border: 2px solid #dc3545;'>";
            echo "<strong>❌ ERROR:</strong> " . $e->getMessage() . "<br>";
            echo "</div>";
            
            if (strpos($e->getMessage(), 'MySQL') !== false || 
                strpos($e->getMessage(), 'gone away') !== false ||
                strpos($e->getMessage(), 'Lost connection') !== false) {
                echo "🔄 MySQL connection issue detected - Reconnecting...<br>";
                checkDatabaseConnection();
            }
            
            $currentProcessed++;
            $totalProcessed++;
            saveProgress($orderID, isset($itemID) ? $itemID : null, $currentPage, $totalProcessed, $i + 1, false);
            continue;
        }
    }
    
    echo "<hr>";
    echo "✅ Page $currentPage processing completed<br>";
    saveProgress(null, null, $currentPage + 1, $totalProcessed, 0, true);
    
    echo "<br><div style='background: #e7f3ff; padding: 15px; border: 2px solid #007bff;'>";
    echo "<strong>=== PAGE $currentPage SUMMARY ===</strong><br>";
    echo "📊 Orders processed: <strong>{$currentProcessed}</strong><br>";
    echo "✅ Existing updated: <strong>{$existingRecordsUpdated}</strong><br>";
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

    checkDatabaseConnection();

    $stmt = $mysqli->prepare("
        SELECT img1,  img2,  img3,  img4,  img5,
               img6,  img7,  img8,  img9,  img10,
               img11, img12, img13, img14, img15
        FROM tblEbayOrderImages
        WHERE ProductID = ?
        LIMIT 1
    ");
    $stmt->bind_param("i", $productID);
    $stmt->execute();
    $result = $stmt->get_result();
    $row    = $result->fetch_assoc();
    $stmt->close();

    // No row at all → fetch
    if (!$row) return true;

    // Has at least one image → skip fetch
    foreach ($row as $val) {
        if (!empty($val)) return false;
    }

    return true; // All 15 slots empty → fetch
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
    
    $stmt = $mysqli->prepare("INSERT INTO tblproduct (rtid, itemnumber, ProductTitle, orderdate, total, quantity, price, Discount, priceshipping, tax, trackingnumber, trackingnumber2, trackingnumber3, trackingnumber4, carrier, listedcondition, seller, shipdate, paymentdate, rtcounter, description, notes, paymentmethod, estimated_deliverydate, itemstatus, conditionStatusApplied, fetchStatus, ProductModuleLoc, materialtype, Ebay_seller_location)
                      VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
    
    if (!$stmt) {
        throw new Exception("Failed to prepare insert statement: " . $mysqli->error);
    }
    
    $stmt->bind_param("ssssdiddddsssssssssissssssssss", 
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
        $estimatedDeliveryDate,// 24 - s - estimated_deliverydate (always store)
        $itemStatus,           // 25 - s - itemstatus
        $appliedCondition,     // 26 - s - conditionStatusApplied
        $fetchStatus,          // 27 - s - fetchStatus
        $moduleLoc,            // 28 - s - ProductModuleLoc
        $materialType,         // 29 - s - materialtype
        $locationdetails,      // 30 - s - Ebay_seller_location
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
    
    if (isset($orders['OrderID'])) {
        $orders = [$orders];
    }
    
    $processedOrders = [];
    $exchangeRates = fetchExchangeRates('f5d29ab775a644eca3f13e4c');

    echo "<br><div style='background: #e7f3ff; padding: 15px; border: 2px solid #007bff;'>";
    echo "<strong>=== PROCESSING EBAY ORDER DATA ===</strong><br>";
    echo "Total orders from eBay: " . count($orders) . "<br>";
    echo "</div><br>";
    
    foreach ($orders as $orderIdx => $order) {
        $orderId = $order['OrderID'] ?? 'UNKNOWN';
        echo "📦 Processing eBay order: <strong>{$orderId}</strong><br>";
        
        $currency = $order['AmountPaid']['@currencyID'] ?? 'USD';
        $amountPaid = $order['AmountPaid'] ?? 0;
        $amountPaidInUSD = convertToUSD($amountPaid, $currency, $exchangeRates);

        $preshippingServiceCost = $order['ShippingServiceSelected']['ShippingServiceCost'] ?? 0;
        $shipping_currency = $currency;
        $shippingServiceCost = convertToUSD($preshippingServiceCost, $shipping_currency, $exchangeRates);

        // Extract ESTIMATED delivery range from eBay
        $estimatedDeliveryMin = null;
        $estimatedDeliveryMax = null;
        $estimatedDeliveryRange = null;
        
        if (isset($order['TransactionArray']['Transaction']['ShippingServiceSelected']['ShippingPackageInfo']['EstimatedDeliveryTimeMin'])) {
            $estimatedDeliveryMin = $order['TransactionArray']['Transaction']['ShippingServiceSelected']['ShippingPackageInfo']['EstimatedDeliveryTimeMin'];
        }
        
        if (isset($order['TransactionArray']['Transaction']['ShippingServiceSelected']['ShippingPackageInfo']['EstimatedDeliveryTimeMax'])) {
            $estimatedDeliveryMax = $order['TransactionArray']['Transaction']['ShippingServiceSelected']['ShippingPackageInfo']['EstimatedDeliveryTimeMax'];
        }
        
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
                
                echo "   📅 Estimated delivery: {$estimatedDeliveryRange}<br>";
            } catch (Exception $e) {
                // Ignore
            }
        } elseif ($estimatedDeliveryMax) {
            try {
                $maxDate = new DateTime($estimatedDeliveryMax);
                $estimatedDeliveryRange = $maxDate->format('Y-m-d');
                echo "   📅 Estimated delivery (MAX): {$estimatedDeliveryRange}<br>";
            } catch (Exception $e) {}
        }

        // Collect ALL tracking numbers (1-4)
        $trackingNumber1 = $trackingNumber2 = $trackingNumber3 = $trackingNumber4 = '';
        $shippingCarrier = '';
        $items = [];
        $locationDetails = 'N/A';

        if (isset($order['TransactionArray']['Transaction']['ShippingDetails']['ShipmentTrackingDetails'])) {
            $trackingDetails = $order['TransactionArray']['Transaction']['ShippingDetails']['ShipmentTrackingDetails'];
            $isArray = isset($trackingDetails[0]);

            for ($i = 0; $i <= 3; $i++) {
                $trackingVar = 'trackingNumber' . ($i + 1);
                
                if ($isArray && isset($trackingDetails[$i]['ShipmentTrackingNumber'])) {
                    ${$trackingVar} = trim($trackingDetails[$i]['ShipmentTrackingNumber']);
                    echo "   🔢 Tracking" . ($i + 1) . ": " . ${$trackingVar} . "<br>";
                } elseif (!$isArray && $i === 0 && isset($trackingDetails['ShipmentTrackingNumber'])) {
                    $trackingNumber1 = trim($trackingDetails['ShipmentTrackingNumber']);
                    echo "   🔢 Tracking1: {$trackingNumber1}<br>";
                }
            }

            if ($isArray && isset($trackingDetails[0]['ShippingCarrierUsed'])) {
                $shippingCarrier = $trackingDetails[0]['ShippingCarrierUsed'];
                echo "   🚚 Carrier: {$shippingCarrier}<br>";
            } elseif (!$isArray && isset($trackingDetails['ShippingCarrierUsed'])) {
                $shippingCarrier = $trackingDetails['ShippingCarrierUsed'];
                echo "   🚚 Carrier: {$shippingCarrier}<br>";
            }
        }

        // Process transactions
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

        // Build processed order
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
        ];

        $processedOrders[] = $processedOrder;
        echo "<br>";
    }

    echo "<div style='background: #d4edda; padding: 15px; border: 2px solid #28a745;'>";
    echo "<strong>✅ Order processing complete: " . count($processedOrders) . " orders ready</strong><br>";
    echo "</div><br>";

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

function saveEbayImages($productID, $imageUrls) {
    global $mysqli;

    checkDatabaseConnection();

    if (!is_array($imageUrls)) {
        $imageUrls = [$imageUrls];
    }

    $imageUrls = array_slice($imageUrls, 0, 15); // max 15

    $imageDir = '/home/imsv2/public_html/laravel_ims/public/images/thumbnails';
    if (!file_exists($imageDir)) {
        mkdir($imageDir, 0755, true);
    }

    if (!is_writable($imageDir)) {
        echo "Error: Image directory is not writable: $imageDir<br>";
        return false;
    }

    $savedImages  = [];   // index => filename  (only successfully saved)
    $successCount = 0;
    $totalCount   = count($imageUrls);

    foreach ($imageUrls as $index => $imageUrl) {
        try {
            if (empty($imageUrl) || !filter_var($imageUrl, FILTER_VALIDATE_URL)) {
                echo "Invalid image URL at index $index: $imageUrl<br>";
                continue;
            }

            $imageName    = $productID . ($index > 0 ? "_$index" : "") . ".jpg";
            $imagePath    = $imageDir . '/' . $imageName;

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
                if (file_exists($imagePath)) unlink($imagePath);
                continue;
            }

            if (getimagesize($imagePath) === false) {
                echo "Downloaded file is not a valid image: $imagePath<br>";
                unlink($imagePath);
                continue;
            }

            $savedImages[$index] = $imageName;
            $successCount++;
            echo "📷 Saved image $imageName for ProductID: $productID<br>";

        } catch (Exception $e) {
            echo "Exception while processing image $index for ProductID $productID: " . $e->getMessage() . "<br>";
        }
    }

    // --------------------------------------------------
    // Persist to tblEbayOrderImages (UPSERT)
    // --------------------------------------------------
    if ($successCount > 0) {
        checkDatabaseConnection();

        // Build named slots — use saved filename or NULL
        $img1  = $savedImages[0]  ?? null;
        $img2  = $savedImages[1]  ?? null;
        $img3  = $savedImages[2]  ?? null;
        $img4  = $savedImages[3]  ?? null;
        $img5  = $savedImages[4]  ?? null;
        $img6  = $savedImages[5]  ?? null;
        $img7  = $savedImages[6]  ?? null;
        $img8  = $savedImages[7]  ?? null;
        $img9  = $savedImages[8]  ?? null;
        $img10 = $savedImages[9]  ?? null;
        $img11 = $savedImages[10] ?? null;
        $img12 = $savedImages[11] ?? null;
        $img13 = $savedImages[12] ?? null;
        $img14 = $savedImages[13] ?? null;
        $img15 = $savedImages[14] ?? null;

        // INSERT … ON DUPLICATE KEY UPDATE handles both new and existing rows
        // (relies on the UNIQUE KEY on ProductID)
        $stmt = $mysqli->prepare("
            INSERT INTO tblEbayOrderImages
                (ProductID, img1, img2, img3, img4, img5, img6, img7, img8, img9, img10, img11, img12, img13, img14, img15)
            VALUES
                (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ON DUPLICATE KEY UPDATE
                img1  = COALESCE(VALUES(img1),  img1),
                img2  = COALESCE(VALUES(img2),  img2),
                img3  = COALESCE(VALUES(img3),  img3),
                img4  = COALESCE(VALUES(img4),  img4),
                img5  = COALESCE(VALUES(img5),  img5),
                img6  = COALESCE(VALUES(img6),  img6),
                img7  = COALESCE(VALUES(img7),  img7),
                img8  = COALESCE(VALUES(img8),  img8),
                img9  = COALESCE(VALUES(img9),  img9),
                img10 = COALESCE(VALUES(img10), img10),
                img11 = COALESCE(VALUES(img11), img11),
                img12 = COALESCE(VALUES(img12), img12),
                img13 = COALESCE(VALUES(img13), img13),
                img14 = COALESCE(VALUES(img14), img14),
                img15 = COALESCE(VALUES(img15), img15),
                updated_at = CURRENT_TIMESTAMP
        ");

        if (!$stmt) {
            echo "Error preparing image upsert: " . $mysqli->error . "<br>";
            return false;
        }

        $stmt->bind_param("isssssssssssssss",
            $productID,
            $img1, $img2, $img3, $img4, $img5,
            $img6, $img7, $img8, $img9, $img10,
            $img11, $img12, $img13, $img14, $img15
        );

        if ($stmt->execute()) {
            echo "✅ tblEbayOrderImages updated for ProductID: $productID<br>";
        } else {
            echo "Error upserting into tblEbayOrderImages: " . $stmt->error . "<br>";
        }

        $stmt->close();
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

?>