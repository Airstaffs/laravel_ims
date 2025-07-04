<?php

namespace App\Http\Controllers\Ebay;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Carbon\Carbon;


// require base_path('Helpers/ebay_helpers.php');
require base_path('app/Helpers/ebay_helpers.php');


class EbayController extends Controller
{
    protected $apiEndpoint = 'https://api.ebay.com/ws/api.dll';
    protected $exchangeApiKey = 'f5d29ab775a644eca3f13e4c'; // Replace with actual API key
    /**
     * Fetch orders from eBay API
     */


    public function fetchOrders(Request $request)
    {

        $serverconfig = env('EBAY_SERVER_CONFIG', 'LOCAL');
        $pageNumber = $request->input('page', 1);
        $credentials = EbayCredentials();

        if (!$credentials || empty($credentials['access_token'])) {
            Log::error('Failed to retrieve a valid access token.');
            return response()->json(['error' => 'Access token not found'], 500);
        }

        $accessToken = $credentials['access_token'];

        try {
            // Send API request
            $response = $this->sendEbayRequest($accessToken, $pageNumber);

            if (!$response) {
                Log::info("Raw eBay API Response:", ['response' => json_encode($response, JSON_PRETTY_PRINT)]);
                return response()->json(['error' => 'Failed to retrieve orders'], 500);
            }

            // Handle API errors
            if (!empty($response['Errors'])) {
                return $this->handleEbayErrors($response['Errors'], $serverconfig, $credentials, $request);
            }

            // Process the orders if the response is successful
            $processedOrders = $this->processOrders($response, $accessToken);

            // ✅ Proper function call
            $this->insertOrUpdate($processedOrders);

            return response()->json([
                'message' => 'Orders fetched and processed successfully',
                'processed_orders' => $processedOrders
            ], 200, [], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);

        } catch (\Exception $e) {
            Log::error('Exception in fetchOrders: ' . $e->getMessage());
            return response()->json(['error' => 'Internal server error'], 500);
        }
    }

    /**
     * Send API request to eBay
     */
    private function sendEbayRequest($accessToken, $pageNumber)
    {
        $createTimeFrom = (new \DateTime('-1 days', new \DateTimeZone('UTC')))->format(DATE_ATOM);
        $createTimeTo = (new \DateTime('now', new \DateTimeZone('UTC')))->format(DATE_ATOM);

        $requestBody = '<?xml version="1.0" encoding="utf-8"?>
        <GetOrdersRequest xmlns="urn:ebay:apis:eBLBaseComponents">
            <RequesterCredentials>
                <eBayAuthToken>' . $accessToken . '</eBayAuthToken>
            </RequesterCredentials>
            <CreateTimeFrom>' . $createTimeFrom . '</CreateTimeFrom>
            <CreateTimeTo>' . $createTimeTo . '</CreateTimeTo>
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
     * Handle eBay API errors
     */
    private function handleEbayErrors($errors, $serverconfig, $credentials, $request)
    {
        foreach ($errors as $error) {
            // Skip invalid error format
            if (!is_array($error)) {
                Log::warning('Unexpected error format: ' . json_encode($error));
                continue;
            }

            if (isset($error['ErrorCode']) && $error['ErrorCode'] == '931') {
                Log::error('eBay API error: Invalid auth token.');

                if ($serverconfig === 'LIVE') {
                    Log::info('Attempting to refresh eBay access token...');
                    $newAccessToken = refreshEbayAccessToken($credentials);

                    if (!$newAccessToken) {
                        Log::error('Failed to refresh eBay access token.');
                        return response()->json(['error' => 'Failed to refresh access token'], 500);
                    }

                    return $this->fetchOrders($request);
                }

                return response()->json(['error' => 'Invalid eBay access token'], 401);
            }

            if (isset($error['ErrorCode']) && $error['ErrorCode'] == '932') {
                Log::error('eBay API error: Auth token is hard expired.');
                return response()->json(['error' => 'Auth token is hard expired, please reauthorize the application'], 401);
            }
        }
    }

    /**
     * Send HTTP request to eBay API
     */
    private function sendRequest($requestBody, $apiCallName)
    {
        // Create headers dynamically with the API call name
        $apiHeaders = [
            'X-EBAY-API-SITEID: 0', // Replace with your actual Site ID
            'X-EBAY-API-COMPATIBILITY-LEVEL: 967', // API compatibility level
            'X-EBAY-API-CALL-NAME: ' . $apiCallName, // Dynamic API call name
            'Content-Type: text/xml',
        ];

        $curl = curl_init();
        curl_setopt($curl, CURLOPT_URL, $this->apiEndpoint);
        curl_setopt($curl, CURLOPT_POST, true);
        curl_setopt($curl, CURLOPT_POSTFIELDS, $requestBody);
        curl_setopt($curl, CURLOPT_HTTPHEADER, $apiHeaders);
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);

        $response = curl_exec($curl);
        $error = curl_error($curl);
        curl_close($curl);

        if ($error) {
            Log::error('cURL Error: ' . $error);
            return null;
        }

        Log::info("Raw XML Response for API Call: $apiCallName", ['response' => $response]);

        $xml = simplexml_load_string($response);
        if (!$xml) {
            Log::error('Invalid XML Response from eBay');
            return null;
        }

        return json_decode(json_encode($xml), true); // Convert XML to JSON array
    }

    /**
     * Process the orders retrieved from eBay
     */
    private function processOrders($response, $accessToken)
    {
        if (empty($response['OrderArray']['Order'])) {
            Log::info('No orders found in response.');
            return [];
        }

        $orders = $response['OrderArray']['Order'];

        echo "<br> Order Primary<br>";
        echo "<pre>";
        print_r($response['OrderArray']);
        echo "</pre>";
        $processedOrders = [];
        $exchangeRates = $this->fetchExchangeRates($this->exchangeApiKey); // Fetch exchange rates

        foreach ($orders as $order) {
            $currency = $order['AmountPaid']['@currencyID'] ?? 'USD';
            $ebayorderid = $order['OrderID'] ?? null;
            $amountPaid = $order['AmountPaid'] ?? 0;
            $amountPaidInUSD = $this->convertToUSD($amountPaid, $currency, $exchangeRates);

            $preshippingServiceCost = $order['ShippingServiceSelected']['ShippingServiceCost'] ?? 0;
            $shipping_currency = $order['AmountPaid']['@currencyID'] ?? 'USD';
            $shippingServiceCost = $this->convertToUSD($preshippingServiceCost, $shipping_currency, $exchangeRates);

            // Initialize tracking numbers
            $trackingNumber1 = '';
            $trackingNumber2 = '';
            $trackingNumber3 = '';
            $trackingNumber4 = '';
            $trackingNumber5 = '';
            $shippingCarrier = '';

            // Extract tracking numbers
            if (isset($order['TransactionArray']['Transaction']['ShippingDetails']['ShipmentTrackingDetails'])) {
                $trackingDetails = $order['TransactionArray']['Transaction']['ShippingDetails']['ShipmentTrackingDetails'];

                // Check if it's a single tracking detail or an array
                if (isset($trackingDetails[0])) {
                    // It's an array of tracking details
                    if (isset($trackingDetails[0]['ShipmentTrackingNumber'])) {
                        $trackingNumber1 = $trackingDetails[0]['ShipmentTrackingNumber'];
                    }
                    if (isset($trackingDetails[1]['ShipmentTrackingNumber'])) {
                        $trackingNumber2 = $trackingDetails[1]['ShipmentTrackingNumber'];
                    }
                    if (isset($trackingDetails[2]['ShipmentTrackingNumber'])) {
                        $trackingNumber3 = $trackingDetails[2]['ShipmentTrackingNumber'];
                    }
                    if (isset($trackingDetails[3]['ShipmentTrackingNumber'])) {
                        $trackingNumber4 = $trackingDetails[3]['ShipmentTrackingNumber'];
                    }
                    if (isset($trackingDetails[4]['ShipmentTrackingNumber'])) {
                        $trackingNumber5 = $trackingDetails[4]['ShipmentTrackingNumber'];
                    }
                    // Get shipping carrier from the first tracking detail if available
                    if (isset($trackingDetails[0]['ShippingCarrierUsed'])) {
                        $shippingCarrier = $trackingDetails[0]['ShippingCarrierUsed'];
                    }
                } else {
                    // It's a single tracking detail
                    if (isset($trackingDetails['ShipmentTrackingNumber'])) {
                        $trackingNumber1 = $trackingDetails['ShipmentTrackingNumber'];
                    }
                    if (isset($trackingDetails['ShippingCarrierUsed'])) {
                        $shippingCarrier = $trackingDetails['ShippingCarrierUsed'];
                    }
                }
            }

            // Fetch item details
            $items = [];



            if (!empty($order['TransactionArray']['Transaction'])) {
                // Ensure transactions are always an array
                $transactions = $order['TransactionArray']['Transaction'];

                if (!isset($transactions[0])) { // If it's a single object, wrap it in an array
                    $transactions = [$transactions];
                }

                // Log::info("Raw Transaction Data for Order ID: " . ($order['OrderID'] ?? 'Unknown'), ['transactions' => $transactions]);

                foreach ($transactions as $transaction) {
                    if (empty($trackingNumber1) && isset($transaction['ShippingDetails']['ShipmentTrackingDetails'])) {
                        $transTrackingDetails = $transaction['ShippingDetails']['ShipmentTrackingDetails'];

                        if (isset($transTrackingDetails[0])) {
                            // It's an array of tracking details
                            if (isset($transTrackingDetails[0]['ShipmentTrackingNumber'])) {
                                $trackingNumber1 = $transTrackingDetails[0]['ShipmentTrackingNumber'];
                            }
                            if (isset($transTrackingDetails[1]['ShipmentTrackingNumber'])) {
                                $trackingNumber2 = $transTrackingDetails[1]['ShipmentTrackingNumber'];
                            }
                            if (isset($transTrackingDetails[2]['ShipmentTrackingNumber'])) {
                                $trackingNumber3 = $transTrackingDetails[2]['ShipmentTrackingNumber'];
                            }
                            if (isset($transTrackingDetails[3]['ShipmentTrackingNumber'])) {
                                $trackingNumber4 = $transTrackingDetails[3]['ShipmentTrackingNumber'];
                            }
                            if (isset($transTrackingDetails[4]['ShipmentTrackingNumber'])) {
                                $trackingNumber5 = $transTrackingDetails[4]['ShipmentTrackingNumber'];
                            }
                            // Get shipping carrier
                            if (isset($transTrackingDetails[0]['ShippingCarrierUsed'])) {
                                $shippingCarrier = $transTrackingDetails[0]['ShippingCarrierUsed'];
                            }
                        } else {
                            // It's a single tracking detail
                            if (isset($transTrackingDetails['ShipmentTrackingNumber'])) {
                                $trackingNumber1 = $transTrackingDetails['ShipmentTrackingNumber'];
                            }
                            if (isset($transTrackingDetails['ShippingCarrierUsed'])) {
                                $shippingCarrier = $transTrackingDetails['ShippingCarrierUsed'];
                            }
                        }
                    }

                    if (!is_array($transaction) || !isset($transaction['Item'])) {
                        Log::error("fetchItemDetails: Transaction is not structured correctly", ['transaction' => $transaction]);
                        continue; // Skip incorrect structures
                    }

                    $itemId = $transaction['Item']['ItemID'] ?? null;

                    if (!$itemId) {
                        Log::error("fetchItemDetails: Item ID is missing for Transaction ID: " . ($transaction['TransactionID'] ?? 'Unknown'));
                        continue; // Skip this transaction if no item ID is found
                    }

                    $itemDetails = $this->fetchItemDetails($itemId, $accessToken);

                    echo "<br> Order Info Details<br>";
                    echo "<pre>";
                    print_r($itemDetails);
                    echo "</pre>";

                    $items[] = [
                        'transaction_id' => $transaction['TransactionID'] ?? null,
                        'item_id' => $itemId,
                        'title' => $transaction['Item']['Title'] ?? null,
                        'quantity_purchased' => $transaction['QuantityPurchased'] ?? null,
                        'item_details' => $itemDetails
                    ];
                }
            }

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
                'tracking_number1' => $trackingNumber1,  // Added first tracking number
                'tracking_number2' => $trackingNumber2,  // Added second tracking number
                'tracking_number3' => $trackingNumber3,  // Added third tracking number
                'tracking_number4' => $trackingNumber4,  // Added fourth tracking number
                'tracking_number5' => $trackingNumber5,  // Added fifth tracking number
                'shipping_carrier' => $shippingCarrier,  // Added shipping carrier
                'items' => $items
            ];

            // Debug the processed order
            echo "<br> Processed Order:<br>";
            echo "<pre>";
            print_r($processedOrder);
            echo "</pre>";

            $processedOrders[] = $processedOrder;
        }

        // Debug all processed orders
        echo "<br> All Processed Orders:<br>";
        echo "<pre>";
        print_r($processedOrders);
        echo "</pre>";

        return $processedOrders;
    }

    private function insertOrUpdate($processedOrders)
    {
        foreach ($processedOrders as $order) {
            if ($order['order_status'] === 'Completed') {
                $orderID = $order['order_id'];
                $createdTime = isset($order['created_time']) ? Carbon::parse($order['created_time'])->format('Y-m-d H:i:s') : null;
                $shippedTime = isset($order['shipped_time']) ? Carbon::parse($order['shipped_time'])->format('Y-m-d H:i:s') : null;
                $paymentDate = isset($order['paid_time']) ? Carbon::parse($order['paid_time'])->format('Y-m-d H:i:s') : null;
                $DeliverDate = isset($order['latest_delivery_date']) ? Carbon::parse($order['latest_delivery_date'])->format('Y-m-d H:i:s') : null;

                $total = $order['total'] ?? 0.00;
                $sellerName = $order['seller_user_id'];
                $moduleLoc = 'Orders';
                $fetchStatus = 'Pending';

                foreach ($order['items'] as $item) {

                    $itemID = $item['item_id'];
                    // $title = $item['title'];

                    $originalTitle = $item['title'];

                    // Clean the title - remove emojis and special characters
                    $title = $this->cleanTitle($originalTitle);



                    $quantityPurchased = $item['quantity_purchased'];
                    $transactionPrice = $item['item_details']['item']['SellingStatus']['CurrentPrice'] ?? 0.00;
                    $conditionDisplay = $item['item_details']['Item']['ConditionDisplayName'] ?? 'Unknown';
                    $materialType = 'Default';

                    // ✅ Tracking and shipping details
                    $trackingNumber1 = $order['tracking_number1'] ?? null;
                    $trackingNumber2 = $order['tracking_number2'] ?? null;
                    $trackingNumber3 = $order['tracking_number3'] ?? null;
                    $trackingNumber4 = $order['tracking_number4'] ?? null;
                    $shippingCarrierUsed = $order['shipping_carrier'] ?? null;
                    $PaymentMethod = $order['payment_method'] ?? 'eBay';
                    $itemStatus = $order['item_status'] ?? 'Shipped';
                    $appliedCondition = $order['condition_status_applied'] ?? 'Applied';

                    $tax = 0.00;
                    $DiscountedPrice = 0.00;
                    $shippingPrice = $order['shipping_cost'] ?? 0.00;
                    $sellerNotes = '';


                    // ✅ Fetch keywords from tblItemstatus
                    $keywords = DB::table('tblItemstatus')->pluck('descriptionStatus')->toArray();

                    // ✅ Ensure keywords are properly formatted
                    $keywords = array_map('strtolower', $keywords); // Convert to lowercase for case-insensitive comparison
                    $itemDescription = '';
                    if (!empty($item['item_details']['Item']['Description'])) {
                        $htmlDescription = (string) $item['item_details']['Item']['Description'];
                        $itemDescription = strip_tags($htmlDescription); // Remove HTML tags
                        $itemDescription = str_replace(["'", '"', "\n", "\r"], "", $itemDescription); // Remove apostrophes, quotes, and new lines
                        $itemDescription = trim($itemDescription);

                        // ✅ Get Seller Notes Safely
                        $sellerNotes = isset($item['item_details']['Item']['ConditionDescription'])
                            ? str_replace(["'", '"'], "", (string) $item['item_details']['Item']['ConditionDescription'])
                            : "N/A";

                        // ✅ Split Description (80% Top-Middle, 20% Bottom)
                        $descriptionLength = strlen($itemDescription);
                        $bottomThreshold = 0.8;
                        $minDescriptionLength = 150;
                        $topMiddleDescription = $itemDescription;

                        if ($conditionDisplay === 'For parts or not working') {
                            $itemStatus = 'Not Working';
                        } else {
                            if ($descriptionLength >= $minDescriptionLength) {
                                $cutOffPoint = (int) ($descriptionLength * $bottomThreshold);
                                $topMiddleDescription = substr($itemDescription, 0, $cutOffPoint);
                                $appliedCondition = '80% applied';
                            } else {
                                $appliedCondition = '80% not applied';
                            }

                            // ✅ Check for "Not Working" Keywords
                            foreach ($keywords as $keyword) {
                                if (
                                    stripos($title, $keyword) !== false ||
                                    stripos($topMiddleDescription, $keyword) !== false
                                ) {
                                    $itemStatus = 'Not Working';
                                    break;
                                }
                            }
                        }
                    }



                    try {
                        // ✅ Check if order item exists
                        $existingProduct = DB::table('tblproduct')
                            ->where('rtid', $orderID)
                            ->where('itemnumber', $itemID)
                            ->where('ProductModuleLoc', $moduleLoc)
                            ->first();
                        if ($existingProduct) {
                            $productID = $existingProduct->ProductID; // Get existing ProductID
                            // **UPDATE**
                            DB::table('tblproduct')
                                ->where('ProductID', $productID)
                                ->update([
                                    'ProductTitle' => $title,
                                    'orderdate' => $createdTime,
                                    'trackingnumber' => $trackingNumber1,
                                    'trackingnumber2' => $trackingNumber2,
                                    'trackingnumber3' => $trackingNumber3,
                                    'trackingnumber4' => $trackingNumber4,
                                    'carrier' => $shippingCarrierUsed,
                                    'listedcondition' => $conditionDisplay,
                                    'seller' => $sellerName,
                                    'shipdate' => $shippedTime,
                                    'paymentdate' => $paymentDate,
                                    'paymentmethod' => $PaymentMethod,
                                    'itemstatus' => $itemStatus,
                                    'conditionStatusApplied' => $appliedCondition,
                                    'datedelivered' => $DeliverDate,
                                    'total' => $total,
                                    'quantity' => $quantityPurchased,
                                    'price' => $transactionPrice,
                                    'Discount' => $DiscountedPrice,
                                    'priceshipping' => $shippingPrice,
                                    'tax' => $tax,
                                ]);
                            Log::info("Updated Order ID: $orderID (Item ID: $itemID) - ProductID: $productID");
                        } else {
                            // **INSERT**
                            $nextRtNumber = fetchRtCounter();
                            $productID = DB::table('tblproduct')->insertGetId([
                                'rtid' => $orderID,
                                'itemnumber' => $itemID,
                                'ProductTitle' => $title,
                                'orderdate' => $createdTime,
                                'total' => $total,
                                'quantity' => $quantityPurchased,
                                'price' => $transactionPrice,
                                'Discount' => $DiscountedPrice,
                                'priceshipping' => $shippingPrice,
                                'tax' => $tax,
                                'trackingnumber' => $trackingNumber1,
                                'trackingnumber2' => $trackingNumber2,
                                'trackingnumber3' => $trackingNumber3,
                                'trackingnumber4' => $trackingNumber4,
                                'carrier' => $shippingCarrierUsed,
                                'listedcondition' => $conditionDisplay,
                                'seller' => $sellerName,
                                'shipdate' => $shippedTime,
                                'paymentdate' => $paymentDate,
                                'rtcounter' => $nextRtNumber,
                                'description' => $itemDescription,
                                'notes' => $sellerNotes,
                                'paymentmethod' => $PaymentMethod,
                                'datedelivered' => $DeliverDate,
                                'itemstatus' => $itemStatus,
                                'conditionStatusApplied' => $appliedCondition,
                                'fetchStatus' => $fetchStatus,
                                'ProductModuleLoc' => $moduleLoc,
                                'materialtype' => $materialType,
                                'validation' => ''
                            ]);

                            Log::info("Inserted Order ID: $orderID (Item ID: $itemID) - ProductID: $productID");

                            // ✅ Download Images Using `ProductID`
                            if (isset($item['item_details']['Item']['PictureDetails']['PictureURL'])) {
                                // Create directory if it doesn't exist
                                $imageDir = public_path('images/thumbnails');
                                if (!file_exists($imageDir)) {
                                    mkdir($imageDir, 0755, true);
                                }

                                // Get images (limit to 5 to prevent timeout)
                                $itemImages = array_slice($item['item_details']['Item']['PictureDetails']['PictureURL'], 0, 5);

                                foreach ($itemImages as $index => $imageUrl) {
                                    // Build file names
                                    $imageName = "{$productID}";
                                    if ($index > 0) {
                                        $imageName .= "_{$index}";
                                    }
                                    $imageName .= ".jpg";

                                    // Full path to save the image
                                    $imagePath = $imageDir . '/' . $imageName;

                                    // Create a context with timeout to prevent hanging
                                    $context = stream_context_create([
                                        'http' => [
                                            'timeout' => 10 // 10 seconds timeout
                                        ]
                                    ]);

                                    // Save image with proper error handling
                                    try {
                                        Log::info("Downloading image from: $imageUrl");
                                        $imageData = @file_get_contents($imageUrl, false, $context);

                                        if ($imageData === false) {
                                            Log::error("Failed to download image from: $imageUrl");
                                            continue;
                                        }

                                        // Ensure directory exists
                                        if (!file_exists(dirname($imagePath))) {
                                            mkdir(dirname($imagePath), 0755, true);
                                        }

                                        // Save the image
                                        if (file_put_contents($imagePath, $imageData) === false) {
                                            Log::error("Failed to save image to: $imagePath");
                                            continue;
                                        }

                                        // Update database with image filename
                                        $imgField = "img" . ($index + 1); // img1, img2, etc.

                                        DB::table('tblproduct')
                                            ->where('ProductID', $productID)
                                            ->update([
                                                $imgField => $imageName
                                            ]);

                                        Log::info("Successfully saved image $index as $imageName for ProductID: $productID");
                                    } catch (\Exception $e) {
                                        Log::error("Error processing image $index for ProductID $productID: " . $e->getMessage());
                                    }
                                }
                            }
                        }

                    } catch (\Exception $e) {
                        Log::error("Error processing Order ID $orderID, Item ID $itemID: " . $e->getMessage());
                    }
                }
            }
        }
    }

    function getItemLocation($itemID)
    {
        global $apiEndpoint;

        $apiHeaders = [
            'X-EBAY-API-SITEID: 0',
            'X-EBAY-API-COMPATIBILITY-LEVEL: 967',
            'X-EBAY-API-CALL-NAME: GetItem',
        ];

        $requestBody = '<?xml version="1.0" encoding="utf-8"?>
    <GetItemRequest xmlns="urn:ebay:apis:eBLBaseComponents">
        <RequesterCredentials>
            <eBayAuthToken>' . getAccessToken() . '</eBayAuthToken>
        </RequesterCredentials>
        <ItemID>' . $itemID . '</ItemID>
        <DetailLevel>ReturnAll</DetailLevel>
    </GetItemRequest>';

        $curl = curl_init();
        curl_setopt($curl, CURLOPT_URL, $apiEndpoint);
        curl_setopt($curl, CURLOPT_POST, true);
        curl_setopt($curl, CURLOPT_POSTFIELDS, $requestBody);
        curl_setopt($curl, CURLOPT_HTTPHEADER, $apiHeaders);
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
        $response = curl_exec($curl);
        curl_close($curl);

        if ($response === false) {
            return "N/A";
        }

        $xml = simplexml_load_string($response);
        if ($xml === false) {
            return "N/A";
        }

        $location = "N/A";

        // Get item location
        if (isset($xml->Item->Location)) {
            $itemLocation = (string) $xml->Item->Location;
            $itemCountry = isset($xml->Item->Country) ? (string) $xml->Item->Country : "";

            if (!empty($itemLocation)) {
                if (!empty($itemCountry) && stripos($itemLocation, $itemCountry) === false) {
                    $location = $itemLocation . ", " . $itemCountry;
                } else {
                    $location = $itemLocation;
                }
            }
        }

        return $location;
    }


    function cleanTitle($text)
    {
        // Pattern to match emoji characters
        $pattern = '/[\x{1F600}-\x{1F64F}|\x{1F300}-\x{1F5FF}|\x{1F680}-\x{1F6FF}|\x{1F700}-\x{1F77F}|\x{1F780}-\x{1F7FF}|\x{1F800}-\x{1F8FF}|\x{1F900}-\x{1F9FF}|\x{1FA00}-\x{1FA6F}|\x{1FA70}-\x{1FAFF}|\x{2600}-\x{26FF}|\x{2700}-\x{27BF}]/u';

        // Remove emojis
        $cleanText = preg_replace($pattern, '', $text);

        // Also remove special characters like stars, fire, etc.
        $cleanText = preg_replace('/[⭐🔥!]/u', '', $cleanText);

        // Remove multiple spaces that might be left after emoji removal
        $cleanText = preg_replace('/\s+/', ' ', $cleanText);

        // Trim any leading/trailing whitespace
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

        // Log::info("Raw response from eBay for Item ID: $itemId", ['response' => $response]);

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


}

function fetchRtCounter()
{
    $maxRtCounter = DB::table('tblproduct')->max('rtcounter');

    return $maxRtCounter ? $maxRtCounter + 1 : 1; // If NULL, start from 1
}
