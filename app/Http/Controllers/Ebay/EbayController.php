<?php

namespace App\Http\Controllers\Ebay;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Carbon\Carbon;

require base_path('app/Helpers/ebay_helpers.php');

class EbayController extends Controller
{
    protected $apiEndpoint = 'https://api.ebay.com/ws/api.dll';
    protected $exchangeApiKey = 'f5d29ab775a644eca3f13e4c';

    // NEW: Add configuration constants from V1
    const BATCH_SIZE = 50;
    const MAX_ORDERS_PER_RUN = 300;
    const API_CALL_DELAY = 1;
    const BATCH_PROCESSING_DELAY = 2;
    const MAX_PAGES_PER_RUN = 5;
    const MAX_EMPTY_PAGES = 2;

    /**
     * NEW: Progress tracking functions from V1
     */
    private function saveProgress($lastProcessedOrderId, $lastProcessedItemId, $currentPage, $totalProcessed)
    {
        $progressFile = storage_path('app/ebay_progress.json');
        
        $progressData = [
            'last_processed_order_id' => $lastProcessedOrderId,
            'last_processed_item_id' => $lastProcessedItemId,
            'current_page' => $currentPage,
            'total_processed' => $totalProcessed,
            'last_run_timestamp' => time(),
            'last_run_date' => date('Y-m-d H:i:s'),
            'optimized_mode' => true
        ];
        
        static $saveCounter = 0;
        $saveCounter++;
        
        if ($saveCounter % 3 == 0 || $saveCounter == 1) {
            if (!file_put_contents($progressFile, json_encode($progressData, JSON_PRETTY_PRINT))) {
                Log::warning("Unable to save progress to file");
            } else {
                Log::info("Progress saved: Page $currentPage, Total processed: $totalProcessed");
            }
        }
    }

    private function loadProgress()
    {
        $progressFile = storage_path('app/ebay_progress.json');
        
        if (!file_exists($progressFile)) {
            return [
                'last_processed_order_id' => null,
                'last_processed_item_id' => null,
                'current_page' => 1,
                'total_processed' => 0,
                'last_run_timestamp' => null
            ];
        }
        
        $progressData = json_decode(file_get_contents($progressFile), true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            Log::warning("Invalid JSON in progress file, starting from beginning");
            return [
                'last_processed_order_id' => null,
                'last_processed_item_id' => null,
                'current_page' => 1,
                'total_processed' => 0,
                'last_run_timestamp' => null
            ];
        }
        
        return $progressData;
    }

    private function performSmartReset($reason, $totalProcessedOverall)
    {
        $progressFile = storage_path('app/ebay_progress.json');
        
        Log::info("SMART RESET TRIGGERED: $reason");
        
        $resetProgress = [
            'last_processed_order_id' => null,
            'last_processed_item_id' => null,
            'current_page' => 1,
            'total_processed' => $totalProcessedOverall,
            'last_run_timestamp' => time(),
            'last_run_date' => date('Y-m-d H:i:s'),
            'optimized_mode' => true,
            'reset_reason' => $reason,
            'reset_timestamp' => time()
        ];
        
        if (file_put_contents($progressFile, json_encode($resetProgress, JSON_PRETTY_PRINT))) {
            Log::info("Reset completed - will start fresh from page 1 next run");
            Log::info("Total processed count preserved: $totalProcessedOverall");
            return true;
        } else {
            Log::warning("Failed to save reset progress");
            return false;
        }
    }

    /**
     * ENHANCED: Fetch orders with ModTime filter and better error handling
     */
    public function fetchOrders(Request $request)
    {
        $serverconfig = env('EBAY_SERVER_CONFIG', 'LOCAL');
        $credentials = EbayCredentials();

        if (!$credentials || empty($credentials['access_token'])) {
            Log::error('Failed to retrieve a valid access token.');
            return response()->json(['error' => 'Access token not found'], 500);
        }

        try {
            // NEW: Use progress tracking
            $progress = $this->loadProgress();
            $pageNumber = $progress['current_page'];
            $allOrders = [];
            $pagesProcessed = 0;
            $totalOrdersFound = 0;
            $emptyPagesCount = 0;
            $exchangeRates = $this->fetchExchangeRates($this->exchangeApiKey);

            Log::info("Starting from page: $pageNumber");

            // NEW: Smart page fetching with proper reset logic
            while ($pagesProcessed < self::MAX_PAGES_PER_RUN) {
                Log::info("Fetching page: $pageNumber");
                
                $response = $this->sendEbayRequestEnhanced($credentials['access_token'], $pageNumber);
                
                if ($response === 'API_LIMIT_REACHED') {
                    Log::info("API limit reached. Stopping for today.");
                    break;
                }
                
                if (!$response) {
                    Log::error("Error fetching page $pageNumber. Stopping.");
                    break;
                }

                if (!empty($response['Errors'])) {
                    $errorHandled = $this->handleEbayErrors($response['Errors'], $serverconfig, $credentials, $request);
                    if ($errorHandled) {
                        return $errorHandled;
                    }
                    break;
                }

                $orders = $this->processOrdersEnhanced($response, $exchangeRates);
                
                if (!empty($orders)) {
                    $allOrders = array_merge($allOrders, $orders);
                    $totalOrdersFound += count($orders);
                    $emptyPagesCount = 0;
                    Log::info("Page $pageNumber: " . count($orders) . " orders found");
                } else {
                    $emptyPagesCount++;
                    Log::info("Page $pageNumber: No orders found (empty page $emptyPagesCount/" . self::MAX_EMPTY_PAGES . ")");
                }
                
                $pageNumber++;
                $pagesProcessed++;
                
                // NEW: Smart reset conditions
                if ($emptyPagesCount >= self::MAX_EMPTY_PAGES) {
                    Log::info("RESET CONDITION MET: $emptyPagesCount consecutive empty pages");
                    $this->performSmartReset("Consecutive empty pages reached", $progress['total_processed']);
                    $pageNumber = 1;
                    break;
                }
                
                if ($pageNumber > 10 && $totalOrdersFound < 10 && $pagesProcessed >= 3) {
                    Log::info("RESET CONDITION MET: High page number ($pageNumber) with minimal results ($totalOrdersFound orders)");
                    $this->performSmartReset("High page number with minimal results", $progress['total_processed']);
                    $pageNumber = 1;
                    break;
                }
            }

            // Update progress only if we're not resetting
            if ($emptyPagesCount < self::MAX_EMPTY_PAGES) {
                $progress['current_page'] = $pageNumber;
                file_put_contents(storage_path('app/ebay_progress.json'), json_encode($progress, JSON_PRETTY_PRINT));
                Log::info("Progress updated to next page: $pageNumber");
            }

            // Process orders with enhanced logic
            if (!empty($allOrders)) {
                Log::info("Processing " . count($allOrders) . " orders with enhanced logic");
                $processedCount = $this->processOrdersWithResume($allOrders);
            } else {
                Log::info("No orders to process this run");
                $processedCount = 0;
            }

            return response()->json([
                'message' => 'Orders fetched and processed successfully with enhanced logic',
                'total_orders_found' => $totalOrdersFound,
                'orders_processed' => $processedCount,
                'next_page' => $emptyPagesCount >= self::MAX_EMPTY_PAGES ? "1 (reset applied)" : $pageNumber,
                'empty_pages_encountered' => $emptyPagesCount
            ], 200);

        } catch (\Exception $e) {
            Log::error('Exception in fetchOrders: ' . $e->getMessage());
            return response()->json(['error' => 'Internal server error'], 500);
        }
    }

    /**
     * ENHANCED: Send API request with ModTime filter for tracking updates
     */
    private function sendEbayRequestEnhanced($accessToken, $pageNumber)
    {
        // NEW: Added ModTime filter to catch recently modified orders (last 7 days)
        $createTimeFrom = (new \DateTime('-30 days', new \DateTimeZone('UTC')))->format(DATE_ATOM);
        $createTimeTo = (new \DateTime('now', new \DateTimeZone('UTC')))->format(DATE_ATOM);
        $modTimeFrom = (new \DateTime('-7 days', new \DateTimeZone('UTC')))->format(DATE_ATOM);

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
            <OutputSelector>OrderArray.Order.TransactionArray.Transaction.ShippingServiceSelected.ShippingPackageInfo.EstimatedDeliveryTimeMax</OutputSelector>
            <OutputSelector>OrderArray.Order.CheckoutStatus.PaymentMethod</OutputSelector>
        </GetOrdersRequest>';

        return $this->sendRequest($requestBody, 'GetOrders');
    }

    /**
     * ENHANCED: Process orders with better error handling and currency conversion
     */
    private function processOrdersEnhanced($response, $exchangeRates)
    {
        if (empty($response['OrderArray']['Order'])) {
            Log::info('No orders found in response.');
            return [];
        }

        $orders = $response['OrderArray']['Order'];
        if (!isset($orders[0])) {
            $orders = [$orders]; // Ensure it's always an array
        }

        $processedOrders = [];

        foreach ($orders as $order) {
            try {
                // NEW: Enhanced currency conversion
                $currency = $order['AmountPaid']['@currencyID'] ?? 'USD';
                $amountPaid = $order['AmountPaid'] ?? 0;
                $amountPaidInUSD = $this->convertToUSD($amountPaid, $currency, $exchangeRates);

                $shippingCost = $order['ShippingServiceSelected']['ShippingServiceCost'] ?? 0;
                $shippingCurrency = $order['ShippingServiceSelected']['ShippingServiceCost']['@currencyID'] ?? $currency;
                $shippingCostUSD = $this->convertToUSD($shippingCost, $shippingCurrency, $exchangeRates);

                // NEW: Enhanced tracking extraction (up to 5 tracking numbers)
                $trackingNumbers = $this->extractTrackingNumbers($order);

                // Process items with enhanced error handling
                $items = [];
                if (!empty($order['TransactionArray']['Transaction'])) {
                    $transactions = $order['TransactionArray']['Transaction'];
                    if (!isset($transactions[0])) {
                        $transactions = [$transactions];
                    }

                    foreach ($transactions as $transaction) {
                        if (!is_array($transaction) || !isset($transaction['Item'])) {
                            Log::error("Transaction is not structured correctly", ['transaction' => $transaction]);
                            continue;
                        }

                        $itemId = $transaction['Item']['ItemID'] ?? null;
                        if (!$itemId) {
                            Log::error("Item ID is missing for Transaction ID: " . ($transaction['TransactionID'] ?? 'Unknown'));
                            continue;
                        }

                        // NEW: Enhanced item processing with better error handling
                        $items[] = [
                            'transaction_id' => $transaction['TransactionID'] ?? null,
                            'item_id' => $itemId,
                            'title' => $transaction['Item']['Title'] ?? null,
                            'quantity_purchased' => $transaction['QuantityPurchased'] ?? 1,
                            'transaction_price' => $transaction['TransactionPrice'] ?? 0,
                            'discount_amount' => $transaction['SellerDiscounts']['SellerDiscount']['ItemDiscountAmount'] ?? 0,
                        ];
                    }
                }

                $processedOrder = [
                    'order_id' => $order['OrderID'] ?? null,
                    'order_status' => $order['OrderStatus'] ?? null,
                    'paid_time' => $order['PaidTime'] ?? null,
                    'amount_paid' => $amountPaidInUSD,
                    'created_time' => $order['CreatedTime'] ?? null,
                    'shipping_cost' => $shippingCostUSD,
                    'subtotal' => $order['Subtotal'] ?? null,
                    'total' => $order['Total'] ?? null,
                    'tax' => $order['TransactionArray']['Transaction']['Taxes']['TotalTaxAmount'] ?? 0,
                    'seller_user_id' => $order['SellerUserID'] ?? null,
                    'seller_email' => $order['SellerEmail'] ?? null,
                    'shipped_time' => $order['ShippedTime'] ?? null,
                    'shipping_address' => isset($order['ShippingAddress']) ? json_encode($order['ShippingAddress']) : null,
                    'payment_method' => $order['CheckoutStatus']['PaymentMethod'] ?? 'eBay',
                    // NEW: Enhanced tracking fields
                    'tracking_number1' => $trackingNumbers['tracking_number1'],
                    'tracking_number2' => $trackingNumbers['tracking_number2'],
                    'tracking_number3' => $trackingNumbers['tracking_number3'],
                    'tracking_number4' => $trackingNumbers['tracking_number4'],
                    'tracking_number5' => $trackingNumbers['tracking_number5'],
                    'shipping_carrier' => $trackingNumbers['shipping_carrier'],
                    'items' => $items,
                ];

                $processedOrders[] = $processedOrder;

            } catch (\Exception $e) {
                Log::error("Error processing order: " . $e->getMessage(), ['order' => $order]);
                continue;
            }
        }

        return $processedOrders;
    }

    /**
     * NEW: Enhanced tracking number extraction
     */
    private function extractTrackingNumbers($order)
    {
        $trackingNumbers = [
            'tracking_number1' => '',
            'tracking_number2' => '',
            'tracking_number3' => '',
            'tracking_number4' => '',
            'tracking_number5' => '',
            'shipping_carrier' => ''
        ];

        // Check order level tracking first
        if (isset($order['TransactionArray']['Transaction']['ShippingDetails']['ShipmentTrackingDetails'])) {
            $trackingDetails = $order['TransactionArray']['Transaction']['ShippingDetails']['ShipmentTrackingDetails'];
            
            if (isset($trackingDetails[0])) {
                // Multiple tracking details
                for ($i = 0; $i < min(5, count($trackingDetails)); $i++) {
                    $trackingKey = 'tracking_number' . ($i + 1);
                    if (isset($trackingDetails[$i]['ShipmentTrackingNumber'])) {
                        $trackingNumbers[$trackingKey] = $trackingDetails[$i]['ShipmentTrackingNumber'];
                    }
                    
                    if ($i === 0 && isset($trackingDetails[$i]['ShippingCarrierUsed'])) {
                        $trackingNumbers['shipping_carrier'] = $trackingDetails[$i]['ShippingCarrierUsed'];
                    }
                }
            } else {
                // Single tracking detail
                if (isset($trackingDetails['ShipmentTrackingNumber'])) {
                    $trackingNumbers['tracking_number1'] = $trackingDetails['ShipmentTrackingNumber'];
                }
                if (isset($trackingDetails['ShippingCarrierUsed'])) {
                    $trackingNumbers['shipping_carrier'] = $trackingDetails['ShippingCarrierUsed'];
                }
            }
        }

        return $trackingNumbers;
    }

    /**
     * NEW: Enhanced order processing with resume functionality and tracking updates
     */
    private function processOrdersWithResume($allOrders)
    {
        $progress = $this->loadProgress();
        $totalProcessed = $progress['total_processed'];
        $lastProcessedOrderId = $progress['last_processed_order_id'];
        $lastProcessedItemId = $progress['last_processed_item_id'];
        
        Log::info("=== RESUME INFORMATION ===");
        Log::info("Total previously processed: $totalProcessed");
        if ($lastProcessedOrderId) {
            Log::info("Last processed: Order ID $lastProcessedOrderId, Item ID $lastProcessedItemId");
        } else {
            Log::info("Starting fresh - no previous progress found");
        }
        Log::info("Total orders to process: " . count($allOrders));
        
        // Limit orders per run for stability
        $ordersToProcess = array_slice($allOrders, 0, self::MAX_ORDERS_PER_RUN);
        Log::info("Processing limited to " . count($ordersToProcess) . " orders this run");
        
        $currentProcessed = 0;
        $skippedCount = 0;
        $errorCount = 0;
        $trackingUpdatedCount = 0;
        
        $resumeProcessing = ($lastProcessedOrderId === null);
        $foundResumePoint = false;
        
        // Process in batches
        $batches = array_chunk($ordersToProcess, self::BATCH_SIZE);
        
        foreach ($batches as $batchIndex => $batch) {
            Log::info("Processing batch " . ($batchIndex + 1) . " of " . count($batches) . " (" . count($batch) . " orders)");
            
            foreach ($batch as $order) {
                foreach ($order['items'] as $item) {
                    $orderID = $order['order_id'];
                    $itemID = $item['item_id'];
                    
                    try {
                        // Resume logic
                        if (!$resumeProcessing && !$foundResumePoint) {
                            if ($orderID === $lastProcessedOrderId && $itemID === $lastProcessedItemId) {
                                $foundResumePoint = true;
                                Log::info("RESUME POINT FOUND: Order ID $orderID, Item ID $itemID");
                            }
                            $skippedCount++;
                            continue;
                        } else if (!$resumeProcessing && $foundResumePoint) {
                            $resumeProcessing = true;
                            Log::info("RESUMING from next item after: Order ID $orderID, Item ID $itemID");
                        }
                        
                        if (!$resumeProcessing) {
                            $skippedCount++;
                            continue;
                        }
                        
                        Log::info("Processing Order ID: $orderID, Item ID: $itemID");
                        
                        // NEW: Check for existing records and handle tracking updates
                        $existingRecord = DB::table('tblproduct')
                            ->where('rtid', $orderID)
                            ->where('itemnumber', $itemID)
                            ->first();
                        
                        if ($existingRecord) {
                            // NEW: Check for tracking updates
                            $trackingUpdated = $this->updateTrackingIfNeeded($existingRecord, $order, $orderID, $itemID);
                            if ($trackingUpdated) {
                                $trackingUpdatedCount++;
                            }
                            
                            // Only do full processing for Orders module records
                            if ($existingRecord->ProductModuleLoc !== 'Orders') {
                                Log::info("Record in '{$existingRecord->ProductModuleLoc}' module - tracking updated, skipping full processing");
                                $currentProcessed++;
                                $totalProcessed++;
                                
                                if ($currentProcessed % 5 == 0) {
                                    $this->saveProgress($orderID, $itemID, $progress['current_page'], $totalProcessed);
                                }
                                continue;
                            }
                        }
                        
                        // Only process completed orders for new records
                        if (!$existingRecord && $order['order_status'] !== 'Completed') {
                            Log::info("Skipping new Order ID $orderID - Status: {$order['order_status']} (not completed)");
                            continue;
                        }
                        
                        // NEW: Enhanced item processing with detailed analysis
                        $processedSuccessfully = $this->processOrderItem($order, $item, $existingRecord);
                        
                        if ($processedSuccessfully) {
                            $currentProcessed++;
                            $totalProcessed++;
                            
                            // Save progress periodically
                            if ($currentProcessed % 3 == 0) {
                                $this->saveProgress($orderID, $itemID, $progress['current_page'], $totalProcessed);
                                Log::info("CHECKPOINT: Saved progress at Order ID: $orderID, Item ID: $itemID");
                            }
                        }
                        
                        // Small delay between items
                        usleep(100000); // 0.1 second delay
                        
                    } catch (\Exception $e) {
                        $errorCount++;
                        Log::error("ERROR processing Order ID $orderID, Item ID $itemID: " . $e->getMessage());
                        
                        $currentProcessed++;
                        $totalProcessed++;
                        
                        if ($currentProcessed % 3 == 0) {
                            $this->saveProgress($orderID, $itemID, $progress['current_page'], $totalProcessed);
                        }
                        
                        continue;
                    }
                }
            }
            
            // Delay between batches
            if ($batchIndex < count($batches) - 1) {
                Log::info("Batch " . ($batchIndex + 1) . " completed. Taking " . self::BATCH_PROCESSING_DELAY . " second break...");
                sleep(self::BATCH_PROCESSING_DELAY);
            }
        }
        
        Log::info("=== PROCESSING SUMMARY ===");
        Log::info("Skipped (already processed): $skippedCount");
        Log::info("Tracking updates applied: $trackingUpdatedCount");
        Log::info("Newly processed: $currentProcessed");
        Log::info("Errors encountered: $errorCount");
        Log::info("Total processed overall: $totalProcessed");
        
        // Save final progress
        if (!empty($ordersToProcess)) {
            $lastOrder = end($ordersToProcess);
            $lastItem = end($lastOrder['items']);
            $this->saveProgress($lastOrder['order_id'], $lastItem['item_id'], $progress['current_page'], $totalProcessed);
            Log::info("Final progress saved");
        }
        
        return $currentProcessed;
    }

    /**
     * NEW: Update tracking information if needed
     */
    private function updateTrackingIfNeeded($existingRecord, $order, $orderID, $itemID)
    {
        $needsUpdate = false;
        $updateData = [];
        
        // Check each tracking field for updates
        $trackingFields = [
            'tracking_number1' => 'trackingnumber',
            'tracking_number2' => 'trackingnumber2', 
            'tracking_number3' => 'trackingnumber3',
            'tracking_number4' => 'trackingnumber4',
            'tracking_number5' => 'trackingnumber5'
        ];
        
        foreach ($trackingFields as $orderField => $dbField) {
            $newValue = trim($order[$orderField] ?? '');
            $existingValue = $existingRecord->$dbField ?? '';
            
            if (!empty($newValue) && $newValue !== $existingValue) {
                $updateData[$dbField] = $newValue;
                $needsUpdate = true;
                Log::info("$dbField UPDATE: '$existingValue' -> '$newValue'");
            }
        }
        
        // Check carrier
        $newCarrier = trim($order['shipping_carrier'] ?? '');
        if (!empty($newCarrier) && $newCarrier !== ($existingRecord->carrier ?? '')) {
            $updateData['carrier'] = $newCarrier;
            $needsUpdate = true;
            Log::info("Carrier UPDATE: '{$existingRecord->carrier}' -> '$newCarrier'");
        }
        
        // Check ship date
        $newShipDate = $order['shipped_time'] ? Carbon::parse($order['shipped_time'])->format('Y-m-d H:i:s') : null;
        if ($newShipDate && $newShipDate !== $existingRecord->shipdate) {
            $updateData['shipdate'] = $newShipDate;
            $needsUpdate = true;
            Log::info("Ship Date UPDATE: '{$existingRecord->shipdate}' -> '$newShipDate'");
        }
        
        if ($needsUpdate) {
            DB::table('tblproduct')
                ->where('rtid', $orderID)
                ->where('itemnumber', $itemID)
                ->update($updateData);
            
            Log::info("✓ TRACKING UPDATED for Order ID: $orderID, Item ID: $itemID (Module: {$existingRecord->ProductModuleLoc})");
            return true;
        }
        
        return false;
    }

    /**
     * NEW: Enhanced item processing with detailed analysis
     */
    private function processOrderItem($order, $item, $existingRecord = null)
    {
        $orderID = $order['order_id'];
        $itemID = $item['item_id'];
        
        // Fetch detailed item information
        $credentials = EbayCredentials();
        $itemDetails = $this->fetchItemDetails($itemID, $credentials['access_token']);
        $locationDetails = $this->getItemLocation($itemID, $credentials['access_token']);
        
        // Clean and prepare data
        $title = $this->cleanTitle($item['title'] ?? '');
        $conditionDisplay = 'Unknown';
        $itemDescription = '';
        $sellerNotes = 'N/A';
        
        if ($itemDetails && isset($itemDetails['Item'])) {
            $conditionDisplay = $itemDetails['Item']['ConditionDisplayName'] ?? 'Unknown';
            
            if (!empty($itemDetails['Item']['Description'])) {
                $htmlDescription = (string) $itemDetails['Item']['Description'];
                $itemDescription = strip_tags($htmlDescription);
                $itemDescription = str_replace(["'", '"', "\n", "\r"], "", $itemDescription);
                $itemDescription = trim($itemDescription);
            }
            
            $sellerNotes = isset($itemDetails['Item']['ConditionDescription'])
                ? str_replace(["'", '"'], "", (string) $itemDetails['Item']['ConditionDescription'])
                : "N/A";
        }
        
        // NEW: Enhanced status determination logic from V1
        $itemStatus = $this->determineItemStatus($title, $itemDescription, $conditionDisplay);
        
        // Prepare dates
        $createdTime = $order['created_time'] ? Carbon::parse($order['created_time'])->format('Y-m-d H:i:s') : null;
        $shippedTime = $order['shipped_time'] ? Carbon::parse($order['shipped_time'])->format('Y-m-d H:i:s') : null;
        $paymentDate = $order['paid_time'] ? Carbon::parse($order['paid_time'])->format('Y-m-d H:i:s') : null;
        
        $orderData = [
            'ProductTitle' => $title,
            'orderdate' => $createdTime,
            'total' => $order['total'] ?? 0,
            'quantity' => $item['quantity_purchased'] ?? 1,
            'price' => $item['transaction_price'] ?? 0,
            'Discount' => $item['discount_amount'] ?? 0,
            'priceshipping' => $order['shipping_cost'] ?? 0,
            'tax' => $order['tax'] ?? 0,
            'trackingnumber' => $order['tracking_number1'] ?? null,
            'trackingnumber2' => $order['tracking_number2'] ?? null,
            'trackingnumber3' => $order['tracking_number3'] ?? null,
            'trackingnumber4' => $order['tracking_number4'] ?? null,
            'trackingnumber5' => $order['tracking_number5'] ?? null,
            'carrier' => $order['shipping_carrier'] ?? null,
            'listedcondition' => $conditionDisplay,
            'seller' => $order['seller_user_id'] ?? 'N/A',
            'shipdate' => $shippedTime,
            'paymentdate' => $paymentDate,
            'description' => $itemDescription,
            'notes' => $sellerNotes,
            'paymentmethod' => $order['payment_method'] ?? 'eBay',
            'itemstatus' => $itemStatus['status'],
            'conditionStatusApplied' => $itemStatus['applied_condition'],
            'Ebay_seller_location' => $locationDetails,
        ];
        
        if ($existingRecord) {
            // Update existing record
            DB::table('tblproduct')
                ->where('ProductID', $existingRecord->ProductID)
                ->update($orderData);
            
            Log::info("Updated Order ID: $orderID (Item ID: $itemID) - ProductID: {$existingRecord->ProductID}");
            
            // Download images if we have item details
            if ($itemDetails && isset($itemDetails['Item']['PictureDetails']['PictureURL'])) {
                $this->saveItemImages($itemDetails['Item']['PictureDetails']['PictureURL'], $existingRecord->ProductID, $itemID);
            }
            
        } else {
            // Insert new record
            $orderData['rtid'] = $orderID;
            $orderData['itemnumber'] = $itemID;
            $orderData['rtcounter'] = fetchRtCounter();
            $orderData['fetchStatus'] = 'Pending';
            $orderData['ProductModuleLoc'] = 'Orders';
            $orderData['materialtype'] = 'Default';
            $orderData['validation'] = '';
            
            $productID = DB::table('tblproduct')->insertGetId($orderData);
            
            Log::info("Inserted Order ID: $orderID (Item ID: $itemID) - ProductID: $productID");
            
            // Download images for new record
            if ($itemDetails && isset($itemDetails['Item']['PictureDetails']['PictureURL'])) {
                $this->saveItemImages($itemDetails['Item']['PictureDetails']['PictureURL'], $productID, $itemID);
            }
        }
        
        return true;
    }

    /**
     * NEW: Enhanced status determination from V1
     */
    private function determineItemStatus($title, $itemDescription, $conditionDisplay)
    {
        $keywords = DB::table('tblItemstatus')->pluck('descriptionStatus')->toArray();
        $keywords = array_map('strtolower', $keywords);
        
        $itemStatus = 'Working';
        $appliedCondition = '';
        
        if ($conditionDisplay === 'For parts or not working') {
            $itemStatus = 'Not Working';
            $appliedCondition = 'Condition based';
        } else {
            // Check title for keywords
            foreach ($keywords as $keyword) {
                if (stripos($title, $keyword) !== false) {
                    $itemStatus = 'Not Working';
                    $appliedCondition = 'Title keyword match';
                    break;
                }
            }
            
            // Check description if status is still Working
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
                
                foreach ($keywords as $keyword) {
                    if (stripos($topMiddleDescription, $keyword) !== false) {
                        $itemStatus = 'Not Working';
                        $appliedCondition .= ' - Description keyword match';
                        break;
                    }
                }
            }
        }
        
        return [
            'status' => $itemStatus,
            'applied_condition' => $appliedCondition
        ];
    }

    /**
     * ENHANCED: Save item images with better error handling
     */
    private function saveItemImages($imageUrls, $productID, $itemID)
    {
        if (!is_array($imageUrls)) {
            $imageUrls = [$imageUrls];
        }
        
        $imageDir = public_path('images/thumbnails');
        if (!file_exists($imageDir)) {
            mkdir($imageDir, 0755, true);
        }
        
        $successCount = 0;
        $imageUrls = array_slice($imageUrls, 0, 15); // Limit to 15 images
        
        foreach ($imageUrls as $index => $imageUrl) {
            try {
                if (empty($imageUrl) || !filter_var($imageUrl, FILTER_VALIDATE_URL)) {
                    Log::warning("Invalid image URL at index $index: $imageUrl");
                    continue;
                }
                
                $imageName = "{$productID}";
                if ($index > 0) {
                    $imageName .= "_{$index}";
                }
                $imageName .= ".jpg";
                
                $imagePath = $imageDir . '/' . $imageName;
                
                // Download with timeout and retry
                $imageContent = $this->downloadImageWithRetry($imageUrl, 2, 20);
                
                if ($imageContent === false || strlen($imageContent) < 100) {
                    Log::warning("Failed to download or image too small: $imageUrl");
                    continue;
                }
                
                if (file_put_contents($imagePath, $imageContent) === false) {
                    Log::warning("Error writing image file: $imagePath");
                    continue;
                }
                
                // Verify it's a valid image
                $imageInfo = getimagesize($imagePath);
                if ($imageInfo === false) {
                    Log::warning("Downloaded file is not a valid image: $imagePath");
                    unlink($imagePath);
                    continue;
                }
                
                // Update database with image filename
                $imgField = "img" . ($index + 1);
                DB::table('tblproduct')
                    ->where('ProductID', $productID)
                    ->update([
                        $imgField => $imageName
                    ]);
                
                $successCount++;
                Log::info("Successfully saved image " . ($index + 1) . " for Item ID: $itemID");
                
            } catch (\Exception $e) {
                Log::error("Exception while processing image $index for Item ID $itemID: " . $e->getMessage());
                continue;
            }
        }
        
        Log::info("Image processing complete for Item ID $itemID: $successCount/" . count($imageUrls) . " images saved successfully");
        return $successCount > 0;
    }

    /**
     * NEW: Download image with retry logic
     */
    private function downloadImageWithRetry($url, $maxRetries = 2, $timeout = 20)
    {
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
                    throw new \Exception("cURL error: $curlError");
                }
                
                if ($httpCode !== 200) {
                    throw new \Exception("HTTP error: $httpCode");
                }
                
                return $imageContent;
                
            } catch (\Exception $e) {
                $retryCount++;
                Log::warning("Attempt $retryCount failed for $url: " . $e->getMessage());
                
                if ($retryCount < $maxRetries) {
                    sleep(1);
                }
            }
        }
        
        return false;
    }

    // Keep your existing methods but enhance them:
    
    private function handleEbayErrors($errors, $serverconfig, $credentials, $request)
    {
        foreach ($errors as $error) {
            if (!is_array($error)) {
                Log::warning('Unexpected error format: ' . json_encode($error));
                continue;
            }

            if (isset($error['ErrorCode'])) {
                switch ($error['ErrorCode']) {
                    case '931':
                        Log::error('eBay API error: Invalid auth token.');
                        if ($serverconfig === 'LIVE') {
                            Log::info('Attempting to refresh eBay access token...');
                            $newAccessToken = refreshEbayAccessToken($credentials);
                            if ($newAccessToken) {
                                return $this->fetchOrders($request);
                            }
                        }
                        return response()->json(['error' => 'Invalid eBay access token'], 401);
                        
                    case '932':
                        Log::error('eBay API error: Auth token is hard expired.');
                        return response()->json(['error' => 'Auth token is hard expired, please reauthorize the application'], 401);
                        
                    case '21916653':
                        Log::error('eBay API error: Application request limit exceeded.');
                        return 'API_LIMIT_REACHED';
                        
                    default:
                        Log::error("eBay API error: Code {$error['ErrorCode']} - " . ($error['ShortMessage'] ?? 'Unknown error'));
                        break;
                }
            }
        }
        
        return null;
    }

    private function sendRequest($requestBody, $apiCallName)
    {
        $apiHeaders = [
            'X-EBAY-API-SITEID: 0',
            'X-EBAY-API-COMPATIBILITY-LEVEL: 967',
            'X-EBAY-API-CALL-NAME: ' . $apiCallName,
            'Content-Type: text/xml',
        ];

        $curl = curl_init();
        curl_setopt($curl, CURLOPT_URL, $this->apiEndpoint);
        curl_setopt($curl, CURLOPT_POST, true);
        curl_setopt($curl, CURLOPT_POSTFIELDS, $requestBody);
        curl_setopt($curl, CURLOPT_HTTPHEADER, $apiHeaders);
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($curl, CURLOPT_TIMEOUT, 60);
        curl_setopt($curl, CURLOPT_CONNECTTIMEOUT, 30);

        $response = curl_exec($curl);
        $error = curl_error($curl);
        $httpCode = curl_getinfo($curl, CURLINFO_HTTP_CODE);
        curl_close($curl);

        if ($error) {
            Log::error('cURL Error: ' . $error);
            return false;
        }
        
        if ($httpCode !== 200) {
            Log::error("HTTP Error $httpCode for API Call: $apiCallName");
            return false;
        }

        $xml = simplexml_load_string($response);
        if (!$xml) {
            Log::error('Invalid XML Response from eBay');
            return false;
        }

        return json_decode(json_encode($xml), true);
    }

    // Keep all your other existing methods unchanged...
    
    private function getItemLocation($itemId, $accessToken)
    {
        if (!$itemId) {
            Log::error("getItemLocation: Item ID is missing.");
            return "N/A";
        }

        $requestBody = '<?xml version="1.0" encoding="utf-8"?>
    <GetItemRequest xmlns="urn:ebay:apis:eBLBaseComponents">
        <RequesterCredentials>
            <eBayAuthToken>' . $accessToken . '</eBayAuthToken>
        </RequesterCredentials>
        <ItemID>' . $itemId . '</ItemID>
        <DetailLevel>ReturnAll</DetailLevel>
    </GetItemRequest>';

        $response = $this->sendRequest($requestBody, 'GetItem');

        if (!$response || !isset($response['Item']['Location'])) {
            Log::warning("getItemLocation: Could not retrieve location for item ID: $itemId");
            return "N/A";
        }

        $itemLocation = $response['Item']['Location'] ?? '';
        $itemCountry = $response['Item']['Country'] ?? '';

        if (!empty($itemLocation)) {
            if (!empty($itemCountry) && stripos($itemLocation, $itemCountry) === false) {
                return $itemLocation . ', ' . $itemCountry;
            } else {
                return $itemLocation;
            }
        }

        return "N/A";
    }

    function cleanTitle($text)
    {
        $pattern = '/[\x{1F600}-\x{1F64F}|\x{1F300}-\x{1F5FF}|\x{1F680}-\x{1F6FF}|\x{1F700}-\x{1F77F}|\x{1F780}-\x{1F7FF}|\x{1F800}-\x{1F8FF}|\x{1F900}-\x{1F9FF}|\x{1FA00}-\x{1FA6F}|\x{1FA70}-\x{1FAFF}|\x{2600}-\x{26FF}|\x{2700}-\x{27BF}]/u';
        $cleanText = preg_replace($pattern, '', $text);
        $cleanText = preg_replace('/[⭐🔥!]/u', '', $cleanText);
        $cleanText = preg_replace('/\s+/', ' ', $cleanText);
        return trim($cleanText);
    }

    private function fetchItemDetails($itemId, $accessToken)
    {
        if (!$itemId) {
            Log::error("fetchItemDetails: Item ID is missing.");
            return null;
        }

        $requestBody = '<?xml version="1.0" encoding="utf-8"?>
        <GetItemRequest xmlns="urn:ebay:apis:eBLBaseComponents">
            <RequesterCredentials>
                <eBayAuthToken>' . $accessToken . '</eBayAuthToken>
            </RequesterCredentials>
            <ItemID>' . $itemId . '</ItemID>
            <DetailLevel>ReturnAll</DetailLevel>
        </GetItemRequest>';

        $response = $this->sendRequest($requestBody, 'GetItem');

        if (!$response) {
            Log::error("fetchItemDetails: No response received from eBay for Item ID: $itemId");
            return null;
        }

        return $response;
    }

    private function fetchExchangeRates($apiKey)
    {
        $url = "https://v6.exchangerate-api.com/v6/$apiKey/latest/USD";
        $response = file_get_contents($url);
        $data = json_decode($response, true);

        if ($data && isset($data['conversion_rates'])) {
            return $data['conversion_rates'];
        } else {
            Log::error("Error fetching exchange rates: " . json_encode($data));
            return [];
        }
    }

    private function convertToUSD($amount, $currency, $exchangeRates)
    {
        if ($currency == 'USD') {
            return number_format($amount, 2, '.', '');
        } elseif (isset($exchangeRates[$currency])) {
            return number_format($amount / $exchangeRates[$currency], 2, '.', '');
        } else {
            Log::error("Exchange rate for currency $currency not found.");
            return $amount;
        }
    }

    // Keep the existing insertOrUpdate method for backward compatibility
    private function insertOrUpdate($processedOrders)
    {
        // This method can remain as a fallback or be removed if you prefer
        // the new processOrdersWithResume method
        foreach ($processedOrders as $order) {
            if ($order['order_status'] === 'Completed') {
                // Your existing logic here...
            }
        }
    }
}

function fetchRtCounter()
{
    $maxRtCounter = DB::table('tblproduct')->max('rtcounter');
    return $maxRtCounter ? $maxRtCounter + 1 : 1;
}