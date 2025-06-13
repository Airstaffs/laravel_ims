<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
session_start();
date_default_timezone_set('America/Los_Angeles');


// exit("RAWR");

$success = false;
$strname = "RT";
$platform = "Amazon";

$db = dbDatabase();

// Handles handles timer if true then it will update the orders table!
if (true) {


    $row = array();

    $stores = ['Renovar Tech', 'All Renewed'];

    foreach ($stores as $store) {

        echo "<strong> $store </strong><br>";

        // Retrieve the credentials
        $credentials = getAWSCredentials($db, $store);

        // Fetch the access token using the credentials
        $accessToken = fetchAccessToken($credentials);

        // Global configuration
        $endpoint = 'https://sellingpartnerapi-na.amazon.com';
        $path = '/orders/v0/orders';
        $service = 'execute-api';
        $region = 'us-east-1';

        // Ensure necessary keys exist
        if (!isset($credentials['client_id']) || !isset($credentials['client_secret'])) {
            die("Invalid keys in database.");
        }

        $edited = "FALSE";
        $shippedtocustomerupdater = "";

        $rdtResponse = getRestrictedDataToken($credentials, $region, $accessToken);

        echo "<pre>";
        // print_r($rdtResponse);
        echo "</pre>";

        // gets the orders!
        $orders = fetchDataFromAPI($credentials, $rdtResponse['restrictedDataToken']);
        echo "<pre>";
        //print_r($orders);
        echo "</pre>";
        $try = 0;

        if (isset($orders)) {
            foreach ($orders as $order) {

                $amazonOrderId = $db->real_escape_string($order['AmazonOrderId'] ?? '');
                echo "<br>AmazonOrderId Query Data: " . $amazonOrderId;

                // Prepare an SQL INSERT statement to insert order data into the database.
                $amazonOrderId = $db->real_escape_string($order['AmazonOrderId'] ?? '');
                $AmazonOrderId = $amazonOrderId;
                $purchaseDate = $db->real_escape_string($order['PurchaseDate'] ?? '');
                $lastUpdateDate = $db->real_escape_string($order['LastUpdateDate'] ?? '');
                $orderStatus = $db->real_escape_string($order['OrderStatus'] ?? '');
                $fulfillmentChannel = $db->real_escape_string($order['FulfillmentChannel'] ?? '');
                if ($fulfillmentChannel == 'MFN') {
                    $fulfillmentChannel = 'FBM';
                } else if ($fulfillmentChannel == 'AFN') {
                    $fulfillmentChannel = 'FBA';
                }
                $itemsShipped = $db->real_escape_string($order['NumberOfItemsShipped'] ?? '');
                $itemsUnshipped = $db->real_escape_string($order['NumberOfItemsUnshipped'] ?? '');
                $AddressLine1 = $db->real_escape_string($order['ShippingAddress']['AddressLine1'] ?? '');
                $state = $db->real_escape_string($order['ShippingAddress']['StateOrRegion'] ?? '');
                $postalcode = $db->real_escape_string($order['ShippingAddress']['PostalCode'] ?? '');
                $city = $db->real_escape_string($order['ShippingAddress']['City'] ?? '');
                $countrycode = $db->real_escape_string($order['ShippingAddress']['CountryCode'] ?? '');
                $paymentMethod = $db->real_escape_string($order['PaymentMethod'] ?? '');
                $BuyerName = $db->real_escape_string($order['BuyerInfo']['BuyerName'] ?? '');
                $buyerEmail = $db->real_escape_string($order['BuyerInfo']['BuyerEmail'] ?? '');
                $purchaseDate = isoToMysqlDatetime($order['PurchaseDate'] ?? '');
                $earliestShipDate = isoToMysqlDatetime($order['EarliestShipDate'] ?? '');
                $latestShipDate = isoToMysqlDatetime($order['LatestShipDate'] ?? '');
                $earliestDeliveryDate = isoToMysqlDatetime($order['EarliestDeliveryDate'] ?? '');
                $latestDeliveryDate = isoToMysqlDatetime($order['LatestDeliveryDate'] ?? '');
                $ship_to_name = $db->real_escape_string($order['ShippingAddress']['Name'] ?? '');
                $shipmentservice = $db->real_escape_string($order['ShipmentServiceLevelCategory'] ?? '');
                $orderStatus = $db->real_escape_string($order['OrderStatus'] ?? '');
                $replacementOrder = $db->real_escape_string($order['IsReplacementOrder'] ?? '');



                $ordertype = $db->real_escape_string($order['OrderType'] ?? '');

                // Step 1: Check if order exists
                $outboundorders_stmt = $db->prepare("SELECT platform_order_id FROM tbloutboundorders WHERE platform_order_id = ? AND platform = ? AND storename = ?");
                $outboundorders_stmt->bind_param("sss", $AmazonOrderId, $platform, $store);
                $outboundorders_stmt->execute();
                $outboundorders_stmt->store_result();

                echo "$AmazonOrderId <br>";

                if ($outboundorders_stmt->num_rows > 0) {
                    $value_sheesh = [];
                    // Step 2: Update if exists
                    $updateStmt_outboundorders = $db->prepare("UPDATE tbloutboundorders SET 
                        address_line1 = ?, 
                        StateOrRegion = ?,
                        postal_code = ?,
                        city = ?,
                        CountryCode = ?,
                        PaymentMethod = ?,
                        BuyerName = ?,
                        BuyerEmail = ?,
                        PurchaseDate = ?,
                        EarliestShipDate = ?,
                        LatestShipDate = ?,
                        EarliestDeliveryDate = ?,
                        LatestDeliveryDate = ?,
                        ShipmentServiceLevelCategory = ?,
                        OrderType = ?,
                        IsReplacementOrder = ?,
                        FulfillmentChannel = ?,
                        ShiptoName = ?,
                        NumberOfItemsUnshipped = ?,
                        NumberOfItemsShipped = ?
                        WHERE platform_order_id = ? 
                        AND platform = ? 
                        AND storename = ?");

                    $value_sheesh = [
                        $AddressLine1,
                        $state,
                        $postalcode,
                        $city,
                        $countrycode,
                        $paymentMethod,
                        $BuyerName,
                        $buyerEmail,
                        $purchaseDate,
                        $earliestShipDate,
                        $latestShipDate,
                        $earliestDeliveryDate,
                        $latestDeliveryDate,
                        $shipmentservice,
                        $ordertype,
                        $replacementOrder,
                        $fulfillmentChannel,
                        $ship_to_name,
                        $itemsUnshipped,
                        $itemsShipped,
                        $AmazonOrderId,
                        $platform,
                        $store
                    ];

                    if (count($value_sheesh) !== 23) {
                        echo "❌ Expected 23 values for outbound order UPDATE. Got: " . count($value_sheesh);
                        print_r($value_sheesh);
                        exit;
                    }

                    $updateStmt_outboundorders->bind_param('ssssssssssssssssssiisss', ...$value_sheesh);
                    $updateStmt_outboundorders->execute();
                    echo "--- Order updated. <br>";
                    $updateStmt_outboundorders->close();

                } else {
                    // Step 3: Insert if not exists
                    $insertStmt = $db->prepare("INSERT INTO tbloutboundorders (
                        platform,
                        storename,
                        platform_order_id,
                        address_line1,
                        StateOrRegion,
                        postal_code,
                        city,
                        CountryCode,
                        PaymentMethod,
                        BuyerName,
                        BuyerEmail,
                        PurchaseDate,
                        EarliestShipDate,
                        LatestShipDate,
                        EarliestDeliveryDate,
                        LatestDeliveryDate,
                        ShipmentServiceLevelCategory,
                        OrderType,
                        IsReplacementOrder,
                        FulfillmentChannel,
                        NumberOfItemsShipped,
                        NumberOfItemsUnshipped,
                        ShiptoName
                    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");

                    $value_sheesh = [
                        $platform,
                        $store,
                        $AmazonOrderId,
                        $AddressLine1,
                        $state,
                        $postalcode,
                        $city,
                        $countrycode,
                        $paymentMethod,
                        $BuyerName,
                        $buyerEmail,
                        $purchaseDate,
                        $earliestShipDate,
                        $latestShipDate,
                        $earliestDeliveryDate,
                        $latestDeliveryDate,
                        $shipmentservice,
                        $ordertype,
                        $replacementOrder,
                        $fulfillmentChannel,
                        $itemsShipped,
                        $itemsUnshipped,
                        $ship_to_name,
                    ];

                    $insertStmt->bind_param(str_repeat("s", count($value_sheesh)), ...$value_sheesh);
                    $insertStmt->execute();
                    echo "--- New order inserted. <br>";
                    $insertStmt->close();
                }

                $outboundorders_stmt->close();


                $orderItems = fetchOrderItems($credentials, $accessToken, $amazonOrderId);

                if (isset($orderItems['payload']['OrderItems'])) {
                    foreach ($orderItems['payload']['OrderItems'] as $item) {

                        $sellerSKU = $db->real_escape_string($item['SellerSKU'] ?? '');
                        $asin = $db->real_escape_string($item['ASIN'] ?? '');
                        $title = $db->real_escape_string($item['Title'] ?? '');
                        $conditionSubtypeId = $db->real_escape_string($item['ConditionSubtypeId'] ?? '');
                        $conditionId = $db->real_escape_string($item['ConditionId'] ?? '');
                        $QuantityOrdered = $db->real_escape_string($item['QuantityOrdered'] ?? '');
                        $QuantityShipped = $db->real_escape_string($item['QuantityShipped'] ?? '');

                        $itemprice = $db->real_escape_string($item['ItemPrice']['Amount'] ?? '');
                        $itemtax = $db->real_escape_string($item['ItemTax']['Amount'] ?? '');
                        $IsBuyerRequestedCancel = $db->real_escape_string($item['BuyerRequestedCancel']['IsBuyerRequestedCancel'] ?? '');
                        $BuyerCancelReason = $db->real_escape_string($item['BuyerRequestedCancel']['BuyerCancelReason'] ?? '');
                        $orderItemId = $db->real_escape_string($item['OrderItemId'] ?? '');
                        $ShippingPrice = $db->real_escape_string($item['ShippingPrice']['Amount'] ?? '');





                        $outbounditems_check = $db->prepare("SELECT platform_order_id FROM tbloutboundordersitem WHERE platform_order_id = ? AND platform_order_item_id = ? AND platform = ?");
                        $outbounditems_check->bind_param("sss", $AmazonOrderId, $orderItemId, $platform);
                        $outbounditems_check->execute();
                        $outbounditems_check->store_result();

                        echo "$AmazonOrderId <br>";
                        echo "Orderitems query $orderItemId <br>";

                        if ($outbounditems_check->num_rows > 0) {
                            // Step 2: Update if exists
                            $updateStmt_outbounditems = $db->prepare("UPDATE tbloutboundordersitem SET 
                                storename = ?,
                                platform_sku = ?, 
                                platform_asin = ?,
                                platform_title = ?,
                                ConditionSubtypeId = ?,
                                ConditionId = ?,
                                FulfillmentChannel = ?,
                                order_status = ?,
                                QuantityOrdered = ?,
                                QuantityShipped = ?,
                                unit_price = ?,
                                unit_tax = ?,
                                ShippingPrice = ?
                                IsBuyerRequestedCancel = ?,
                                BuyerCancelReason = ?
                            WHERE platform_order_id = ? AND platform_order_item_id = ? AND platform = ?");

                            $value_sheesh = [
                                $store,
                                $sellerSKU,               // 1
                                $asin,                    // 2
                                $title,                   // 3
                                $conditionSubtypeId,      // 4
                                $conditionId,             // 5
                                $fulfillmentChannel,      // 6
                                $orderStatus,             // 7
                                (int) $QuantityOrdered,    // 8
                                (int) $QuantityShipped,    // 9
                                (float) $itemprice,        // 10
                                (float) $itemtax,          // 11
                                (float) $ShippingPrice,     // 17
                                $IsBuyerRequestedCancel,  // 12
                                $BuyerCancelReason,       // 13
                                $AmazonOrderId,           // 14
                                $orderItemId,             // 15
                                $platform,                 // 16

                            ];

                            $updateStmt_outbounditems->bind_param('ssssssssiiidddssss', ...$value_sheesh);
                            $updateStmt_outbounditems->execute();
                            echo "------ Orderitem updated. <br>";
                            $updateStmt_outbounditems->close();

                        } else {
                            // Step 3: Insert if not exists
                            $insertStmt_outbounditems = $db->prepare("INSERT INTO tbloutboundordersitem (
                                storename,
                                platform,
                                platform_order_id,
                                platform_order_item_id,
                                platform_sku,
                                platform_asin,
                                platform_title,
                                ConditionSubtypeId,
                                ConditionId,
                                FulfillmentChannel,
                                order_status,
                                QuantityOrdered,
                                QuantityShipped,
                                unit_price,
                                unit_tax,
                                shippingPrice,
                                IsBuyerRequestedCancel,
                                BuyerCancelReason
                            ) VALUES ( ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");

                            $valueInsert = [
                                $store,
                                $platform,
                                $AmazonOrderId,
                                $orderItemId,
                                $sellerSKU,
                                $asin,
                                $title,
                                $conditionSubtypeId,
                                $conditionId,
                                $fulfillmentChannel,
                                $orderStatus,
                                $QuantityOrdered,
                                $QuantityShipped,
                                $itemprice,
                                $itemtax,
                                $ShippingPrice,
                                $IsBuyerRequestedCancel,
                                $BuyerCancelReason
                            ];

                            $insertStmt_outbounditems->bind_param('sssssssssssiidddss', ...$valueInsert);
                            $insertStmt_outbounditems->execute();
                            echo "------ Orderitem inserted. <br>";
                            $insertStmt_outbounditems->close();
                        }

                        $outbounditems_check->close();


                        // translate to local vocab
                        if ($fulfillmentChannel == 'MFN') {
                            $fulfilledby = "FBM";

                            if ($orderStatus === 'Cancelled') {

                                $stmt = $db->prepare("UPDATE tblshiphistory SET OrderStatus = ? WHERE AmazonOrderId = ? AND orderitemid = ?");
                                $stmt->bind_param('sss', $orderStatus, $amazonOrderId, $orderItemId);

                                if ($stmt->execute()) {
                                } else {
                                    echo "<br>Error Updating Record: " . $stmt->error;
                                }
                                $stmt->close();
                            }
                        }

                        if ($fulfillmentChannel === 'AFN') {
                            $fulfilledby = "FBA";
                            echo "<br>FBA Update";
                            // if order is FBA
                            $getedited = "SELECT edited FROM tblshiphistory WHERE AmazonOrderId = '$amazonOrderId' AND orderitemid = '$orderItemId'";
                            $editedresult = $db->query($getedited);

                            if ($editedresult->num_rows > 0) {
                                // output the data
                                while ($row = $editedresult->fetch_assoc()) {
                                    $edited = $row["edited"];
                                }
                            } else {
                                $edited = "FALSE";
                            }

                            $prodidquery = "SELECT ProductID FROM tblproduct WHERE ASINviewer = '$asin' AND MSKUviewer = '$sellerSKU' AND ProductModuleLoc = 'Stockroom'";
                            $result = $db->query($prodidquery);

                            if ($result->num_rows > 0) {
                                $row = $result->fetch_assoc();
                                $rowprodid = $row['ProductID'];

                                if ($orderStatus === "Pending") {

                                    // insert or update orders data except column ProductID
                                    InsertORUpdate_tblhistory($edited);

                                    $updateQuery = "UPDATE tblshiphistory SET ProductID = '' WHERE AmazonOrderId = ? AND orderitemid = ?";
                                    $updateStmt = $db->prepare($updateQuery);

                                    // Check if the statement was prepared successfully
                                    if ($updateStmt === false) {
                                        die("Error preparing the query: " . $db->error);
                                    }

                                    $updateStmt->bind_param("ss", $amazonOrderId, $orderItemId);
                                    // Execute the statement and check if it was successful
                                    if ($updateStmt->execute()) {
                                        echo "Record updated successfully.";

                                        // You can also check if any rows were affected
                                        if ($updateStmt->affected_rows > 0) {
                                            echo " Number of rows updated: " . $updateStmt->affected_rows;
                                        } else {
                                            echo " No rows were updated.";
                                        }
                                    } else {
                                        echo "Error updating record: " . $updateStmt->error;
                                    }
                                    $updateStmt->close();

                                    echo "<br> Product data: " . $orderStatus . " ProductID: " . $rowprodid . " AmazonOrderId: " . $amazonOrderId . "<br>";
                                }

                                if ($orderStatus === "Unshipped") {

                                    // insert or update orders data except column ProductID
                                    InsertORUpdate_tblhistory($edited);

                                    // inserts the ProductID where data is similar!
                                    $updateQuery = "UPDATE tblshiphistory SET ProductID = ? WHERE ASIN = ? AND SellerSKU = ? AND AmazonOrderId = ?";
                                    $updateStmt = $db->prepare($updateQuery);
                                    // Check if the statement was prepared successfully
                                    if ($updateStmt === false) {
                                        die("Error preparing the query: " . $db->error);
                                    }

                                    $updateStmt->bind_param("isss", $rowprodid, $asin, $sellerSKU, $amazonOrderId);

                                    // Execute the statement and check if it was successful
                                    if ($updateStmt->execute()) {
                                        echo "Record updated successfully.";

                                        // You can also check if any rows were affected
                                        if ($updateStmt->affected_rows > 0) {
                                            echo " Number of rows updated: " . $updateStmt->affected_rows;
                                        } else {
                                            echo " No rows were updated.";
                                        }
                                    } else {
                                        echo "Error updating record: " . $updateStmt->error;
                                    }

                                    $updateStmt->close();


                                    $updateQuery = "UPDATE tblproduct SET shipmentstatus = ? WHERE ASINviewer = ? AND MSKUviewer = ? AND ProductModuleLoc = 'Stockroom'";
                                    $updateStmt = $db->prepare($updateQuery);

                                    // Check if the statement was prepared successfully
                                    if ($updateStmt === false) {
                                        die("Error preparing the query: " . $db->error);
                                    }

                                    $updateStmt->bind_param("sss", $orderStatus, $asin, $sellerSKU);
                                    // Execute the statement and check if it was successful
                                    if ($updateStmt->execute()) {
                                        echo "Record updated successfully.";

                                        // You can also check if any rows were affected
                                        if ($updateStmt->affected_rows > 0) {
                                            echo " Number of rows updated: " . $updateStmt->affected_rows;
                                        } else {
                                            echo " No rows were updated.";
                                        }
                                    } else {
                                        echo "Error updating record: " . $updateStmt->error;
                                    }
                                    $updateStmt->close();
                                }

                                if ($orderStatus === "Shipped") {
                                    $orderStatus = 'Shipped to Customer';
                                    // insert or update orders data except column ProductID
                                    InsertORUpdate_tblhistory($edited);

                                    $updateQuery = "UPDATE tblproduct SET shipmentstatus = ? WHERE ASINviewer = ? AND MSKUviewer = ? AND ProductModuleLoc = 'Stockroom'";
                                    $updateStmt = $db->prepare($updateQuery);

                                    // Check if the statement was prepared successfully
                                    if ($updateStmt === false) {
                                        die("Error preparing the query: " . $db->error);
                                    }

                                    $updateStmt->bind_param("sss", $orderStatus, $asin, $sellerSKU);
                                    // Execute the statement and check if it was successful
                                    if ($updateStmt->execute()) {
                                        echo "Record updated successfully.";

                                        // You can also check if any rows were affected
                                        if ($updateStmt->affected_rows > 0) {
                                            echo " Number of rows updated: " . $updateStmt->affected_rows;
                                        } else {
                                            echo " No rows were updated.";
                                        }
                                    } else {
                                        echo "Error updating record: " . $updateStmt->error;
                                    }
                                    $updateStmt->close();

                                    echo "<br> Product data: " . $orderStatus . " ProductID: " . $rowprodid . " AmazonOrderId: " . $amazonOrderId;
                                }

                                if ($orderStatus == 'Cancelled' || $orderStatus == 'Unfulfillable') {
                                    // inserts the ProductID where data is similar!
                                    InsertORUpdate_tblhistory($edited);

                                    $updateQuery = "UPDATE tblproduct SET shipmentstatus = '' WHERE ASINviewer = ? AND MSKUviewer = ? AND ProductModuleLoc = 'Stockroom'";
                                    $updateStmt = $db->prepare($updateQuery);

                                    // Check if the statement was prepared successfully
                                    if ($updateStmt === false) {
                                        die("Error preparing the query: " . $db->error);
                                    }

                                    $updateStmt->bind_param("ss", $asin, $sellerSKU);
                                    // Execute the statement and check if it was successful
                                    if ($updateStmt->execute()) {
                                        echo "Record updated successfully.";

                                        // You can also check if any rows were affected
                                        if ($updateStmt->affected_rows > 0) {
                                            echo " Number of rows updated: " . $updateStmt->affected_rows;
                                        } else {
                                            echo " No rows were updated.";
                                        }
                                    } else {
                                        echo "Error updating record: " . $updateStmt->error;
                                    }
                                    $updateStmt->close();

                                    $updateQuery = "UPDATE tblshiphistory SET ProductID = '' WHERE AmazonOrderId = ? AND orderitemid = ?";
                                    $updateStmt = $db->prepare($updateQuery);

                                    // Check if the statement was prepared successfully
                                    if ($updateStmt === false) {
                                        die("Error preparing the query: " . $db->error);
                                    }

                                    $updateStmt->bind_param("ss", $amazonOrderId, $orderItemId);
                                    // Execute the statement and check if it was successful
                                    if ($updateStmt->execute()) {
                                        echo "Record updated successfully.";

                                        // You can also check if any rows were affected
                                        if ($updateStmt->affected_rows > 0) {
                                            echo " Number of rows updated: " . $updateStmt->affected_rows;
                                        } else {
                                            echo " No rows were updated.";
                                        }
                                    } else {
                                        echo "Error updating record: " . $updateStmt->error;
                                    }
                                    $updateStmt->close();
                                    echo "<br> Product data: " . $orderStatus . " ProductID: " . $rowprodid . " AmazonOrderId: " . $amazonOrderId;
                                }
                            }
                            $success = true;
                        }
                        echo " <br>Number of Entry" . $try++ . " <br>";
                    }
                }
            }


            if ($success) {
                $expirationTime = date("Y-m-d H:i:s", strtotime("+0 seconds"));
                $last_updated_time = date("Y-m-d H:i:s", strtotime("-60 minutes"));

                // Use prepared statements to avoid SQL Injection
                $insertQuery = "INSERT INTO aws_orders_reference (date_last_update, start_time) VALUES (?, ?)";
                $stmtInsert = $db->prepare($insertQuery);
                $stmtInsert->bind_param("ss", $expirationTime, $last_updated_time);

                if ($stmtInsert->execute()) {
                    // Fetch the latest ID
                    $sqlorders = "SELECT id FROM aws_orders_reference ORDER BY id DESC LIMIT 1";
                    $result = $db->query($sqlorders);

                    if ($result->num_rows > 0) {
                        $row = $result->fetch_assoc();
                        echo "Latest ID: " . $row["id"];
                        $latestid = $row['id'];
                        $subtractedValue = $latestid - 100;

                        // Corrected table name in DELETE query
                        $sqldelete = "DELETE FROM aws_orders_reference WHERE id < ?";
                        $stmtDelete = $db->prepare($sqldelete);
                        $stmtDelete->bind_param("i", $subtractedValue);

                        if ($stmtDelete->execute()) {
                            $affectedRows = $stmtDelete->affected_rows;
                            // Consider echoing the number of affected rows
                        } else {
                            echo "Error in delete operation: " . $db->error;
                        }
                    } else {
                        echo "0 results after insert operation";
                    }
                } else {
                    echo "Error in insert operation: " . $db->error;
                }
            }
        }
    }

}



function InsertORUpdate_tblhistory($edited)
{
    global $db, $amazonOrderId, $purchaseDate, $lastUpdateDate, $orderStatus, $fulfilledby, $itemsShipped, $paymentMethod, $buyerEmail, $BuyerName, $ShippingPrice, $itemsUnshipped, $asin, $sellerSKU, $title, $conditionId, $itemprice, $itemtax, $conditionSubtypeId, $QuantityOrdered, $QuantityShipped, $rowprodid, $orderItemId, $replacementOrder, $earliestShipDate, $latestShipDate, $earliestDeliveryDate, $latestDeliveryDate, $state, $postalcode, $city, $countrycode, $shipmentservice, $ordertype, $shippedtocustomerupdater, $strname, $AddressLine1, $ship_to_name, $IsBuyerRequestedCancel, $BuyerCancelReason;

    // Convert GMT dates to Los Angeles time
    $timezone = new DateTimeZone('America/Los_Angeles');

    function convertToLA($datetimeString, $timezone)
    {
        if (empty($datetimeString))
            return null;
        try {
            $date = new DateTime($datetimeString, new DateTimeZone('UTC'));
            $date->setTimezone($timezone);
            return $date->format('Y-m-d H:i:s');
        } catch (Exception $e) {
            return null;
        }
    }

    $purchaseDate = convertToLA($purchaseDate, $timezone);
    $lastUpdateDate = convertToLA($lastUpdateDate, $timezone);
    $earliestShipDate = convertToLA($earliestShipDate, $timezone);
    $latestShipDate = convertToLA($latestShipDate, $timezone);
    $earliestDeliveryDate = convertToLA($earliestDeliveryDate, $timezone);
    $latestDeliveryDate = convertToLA($latestDeliveryDate, $timezone);

    // Continue with your existing logic
    $query = "SELECT AmazonOrderId FROM tblshiphistory WHERE AmazonOrderId = '$amazonOrderId' AND orderitemid = '$orderItemId'";
    $result = $db->query($query);

    if ($result->num_rows > 0) {
        $sql = "UPDATE tblshiphistory SET
                PurchaseDate = '$purchaseDate',
                LastUpdateDate = '$lastUpdateDate',
                OrderStatus = '$orderStatus',
                FulfillmentChannel = '$fulfilledby',
                NumberOfItemsShipped = '$itemsShipped',
                NumberOfItemsUnshipped = '$itemsUnshipped',
                PaymentMethod = '$paymentMethod',
                BuyerEmail = '$buyerEmail',
                costumer_name = '$BuyerName',
                shippingPrice = '$ShippingPrice',
                ASIN = '$asin',
                SellerSKU = '$sellerSKU',
                Title = '$title',
                ConditionSubtypeId = '$conditionSubtypeId',
                ConditionId = '$conditionId',
                ItemPrice = '$itemprice',
                ItemTax = '$itemtax',
                QuantityOrdered = '$QuantityOrdered',
                QuantityShipped = '$QuantityShipped',
                ProductID = '$rowprodid',
                orderitemid = '$orderItemId',
                IsReplacementOrder = '$replacementOrder',
                EarliestShipDate = '$earliestShipDate',
                LatestShipDate = '$latestShipDate',
                EarliestDeliveryDate = '$earliestDeliveryDate',
                LatestDeliveryDate = '$latestDeliveryDate',
                StateOrRegion = '$state',
                PostalCode = '$postalcode',
                City = '$city',
                CountryCode = '$countrycode',
                AddressLine1 = '$AddressLine1',
                ship_to_name = '$ship_to_name',
                ShipmentServiceLevelCategory = '$shipmentservice',
                OrderType = '$ordertype',
                strname = '$strname',
                IsBuyerRequestedCancel = '$IsBuyerRequestedCancel',
                BuyerCancelReason = '$BuyerCancelReason'";

        if ($edited === "FALSE") {
            $sql .= ",
                ASIN = '$asin',
                SellerSKU = '$sellerSKU'";
        }

        if ($shippedtocustomerupdater !== "Shipped to Customer" && $orderStatus == "Shipped to Customer") {
            date_default_timezone_set('America/Los_Angeles');
            $current_time = date('Y-m-d H:i:s');
            $sql .= ",
                shipped_to_customer_date = '$current_time'";
        }

        $sql .= " WHERE AmazonOrderId = '$amazonOrderId' AND orderitemid = '$orderItemId'";
    } else {
        $sql = "INSERT INTO tblshiphistory (AmazonOrderId, purchaseDate, LastUpdateDate, OrderStatus, FulfillmentChannel, NumberOfItemsShipped, NumberOfItemsUnshipped, PaymentMethod, BuyerEmail, costumer_name, shippingPrice, ASIN, SellerSKU, Title, ConditionSubtypeId, ConditionId, ItemPrice, ItemTax, QuantityOrdered, QuantityShipped, orderitemid, IsReplacementOrder, EarliestShipDate, LatestShipDate, EarliestDeliveryDate, LatestDeliveryDate, StateOrRegion, PostalCode, City, CountryCode, ShipmentServiceLevelCategory, OrderType, strname, AddressLine1, IsBuyerRequestedCancel, BuyerCancelReason, ship_to_name)
        VALUES ('$amazonOrderId', '$purchaseDate', '$lastUpdateDate', '$orderStatus', '$fulfilledby', '$itemsShipped', '$itemsUnshipped', '$paymentMethod', '$buyerEmail', '$BuyerName', '$ShippingPrice', '$asin', '$sellerSKU', '$title', '$conditionSubtypeId', '$conditionId','$itemprice', '$itemtax', '$QuantityOrdered','$QuantityShipped', '$orderItemId', '$replacementOrder', '$earliestShipDate', '$latestShipDate', '$earliestDeliveryDate', '$latestDeliveryDate', '$state', '$postalcode', '$city', '$countrycode', '$shipmentservice', '$ordertype', '$strname', '$AddressLine1', '$IsBuyerRequestedCancel', '$BuyerCancelReason', '$ship_to_name')";
    }

    if ($db->query($sql) === false) {
        echo "Error: " . $sql . "<br>" . $db->error;
    }
    echo "<br>" . $sql;
    echo "<br><br>";
}

$db->close();

if ($pdo) {
    $pdo = null;
}

// Functions
//_________________________________________________________________________________________________________________________________________________________
function dbDatabase()
{
    // Define server mode here
    $servertype = "laravel_ims";

    // Set credentials based on server type
    switch ($servertype) {
        case "ims":
            $hostname = 'localhost';
            $username = 'root';
            $password = '';
            $database = 'ims';
            break;

        case "hostinger":
            $hostname = 'localhost';
            $username = 'u298641722_web_ims';
            $password = 'ImsHosting!11923';
            $database = 'u298641722_ims';
            break;

        case "test":
            $hostname = 'localhost';
            $username = 'u298641722_testing_user';
            $password = 'Watdahek1234!';
            $database = 'u298641722_test';
            break;

        case "laravel_ims":
            $hostname = 'localhost';
            $username = 'u298641722_dbims_user';
            $password = '?cIk=|zRk3T';
            $database = 'u298641722_dbims';
            break;

        default:
            die("❌ Invalid server type: Set \$servertype properly.");
    }

    echo "$hostname $username $password $database Rawr";

    // Create mysqli dbion
    $db = new mysqli($hostname, $username, $password, $database);

    return $db;
}

function fetchAccessToken($credentials, $returnRaw = false)
{
    $postfields = [
        'grant_type' => 'refresh_token',
        'client_id' => $credentials['client_id'],
        'client_secret' => $credentials['client_secret'],
        'refresh_token' => $credentials['refresh_token'],
    ];

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, 'https://api.amazon.com/auth/o2/token');
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
    curl_setopt($ch, CURLOPT_POST, 1);
    curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($postfields));
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Content-Type: application/x-www-form-urlencoded;charset=UTF-8'
    ]);

    $response = curl_exec($ch);

    if ($response === FALSE) {
        die('cURL Error: ' . curl_error($ch));
    }

    curl_close($ch);

    $decodedResponse = json_decode($response, true);

    if ($returnRaw) {
        return $decodedResponse;
    }

    return $decodedResponse['access_token'] ?? false;
}

function getRestrictedDataToken($credentials, $region, $accessToken)
{
    $endpoint = "https://sellingpartnerapi-na.amazon.com/tokens/2021-03-01/restrictedDataToken";
    $host = "sellingpartnerapi-na.amazon.com";
    $payload = json_encode([
        "restrictedResources" => [
            [
                "method" => "GET",
                "path" => "/orders/v0/orders",
                "dataElements" => ["buyerInfo", "shippingAddress"]
            ]
        ]
    ]);

    $amzDate = gmdate('Ymd\THis\Z');
    $date = gmdate('Ymd');

    $canonicalUri = '/tokens/2021-03-01/restrictedDataToken';
    $canonicalQuerystring = '';
    $canonicalHeaders = "content-type:application/json\nhost:$host\nx-amz-access-token:$accessToken\nx-amz-date:$amzDate\n";
    $signedHeaders = 'content-type;host;x-amz-access-token;x-amz-date';
    $payloadHash = hash('sha256', $payload);

    $canonicalRequest = "POST\n$canonicalUri\n$canonicalQuerystring\n$canonicalHeaders\n$signedHeaders\n$payloadHash";

    $algorithm = 'AWS4-HMAC-SHA256';
    $credentialScope = "$date/$region/execute-api/aws4_request";
    $stringToSign = "$algorithm\n$amzDate\n$credentialScope\n" . hash('sha256', $canonicalRequest);

    $kSecret = 'AWS4' . $credentials['client_secret'];
    $kDate = hash_hmac('sha256', $date, $kSecret, true);
    $kRegion = hash_hmac('sha256', $region, $kDate, true);
    $kService = hash_hmac('sha256', 'execute-api', $kRegion, true);
    $kSigning = hash_hmac('sha256', 'aws4_request', $kService, true);
    $signature = hash_hmac('sha256', $stringToSign, $kSigning);

    $authorizationHeader = "$algorithm Credential={$credentials['client_id']}/$credentialScope, SignedHeaders=$signedHeaders, Signature=$signature";

    $headers = [
        "Content-Type: application/json",
        "Host: $host",
        "x-amz-access-token: $accessToken",
        "x-amz-date: $amzDate",
        "Authorization: $authorizationHeader"
    ];

    $ch = curl_init($endpoint);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $payload);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);

    $response = curl_exec($ch);

    if (curl_errno($ch)) {
        echo 'Error:' . curl_error($ch);
    }

    curl_close($ch);

    return json_decode($response, true);
}

function fetchDataFromAPI($credentials, $accessToken)
{
    global $endpoint, $path;
    $allData = []; // Store all aggregated data here
    $nextToken = null; // Initialize the nextToken with null


    do {
        do {
            // process the build headers
            $headers = buildHeaders($credentials, $accessToken);
            // get the url and process the query for parameters
            $url = "{$endpoint}{$path}?" . buildQueryString($nextToken);

            $ch = curl_init($url);
            // process the items

            curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

            $result = curl_exec($ch);

            $httpcode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            echo '<br>';
            // print_r($httpcode);

            // handles the error code 429
            if ($httpcode == 429) {
                echo "<br>Rate limit exceeded, retrying in 60 seconds... For Orders ...<br>";
                sleep(60); // Sleep for 60 seconds
                // Don't forget to close the cURL session before retrying
                curl_close($ch);
            } else if ($httpcode == 401) {
                echo "Unauthorized Access Retrying!\n";

                $accessToken = fetchAccessToken($credentials);

                if ($accessToken) {
                    $_SESSION['access_token'] = $accessToken;
                    // echo "Access Token: " . $accessToken . "\n";
                } else {
                    echo "Access token not found in the response.\n";
                }

                curl_close($ch);
            } else {
                // If the response code is not 429, break out of the loop.
                break;
            }


            curl_close($ch);
        } while ($httpcode == 429 || $httpcode == 401);

        $data = json_decode($result, true);

        echo "<pre>";
        // print_r($data);
        echo "</pre>";

        // Check for the presence of the 'NextToken' in the response
        $nextToken = $data['payload']['NextToken'] ?? null;

        echo $nextToken;

        if (isset($data['payload']['Orders']) && is_array($data['payload']['Orders'])) {
            // Append each order to the $allData array
            foreach ($data['payload']['Orders'] as $order) {
                $allData[] = $order;
            }
        }
        if (isset($data['errors'])) {
            echo "Error Orders: " . $data['errors'][0]['message'] . "<br>";
            return [];
        }
    } while ($nextToken); // Continue as long as there's a nextToken
    echo '<br>Passed = Done Processing Fetch Orders<br>';
    return $allData; // Return the aggregated results
}

function buildHeaders($credentials, $accessToken)
{

    $amzDate = gmdate('Ymd\THis\Z');
    $signatureDetails = calculateSignature($credentials, $amzDate);

    return [
        "x-amz-date: {$amzDate}",
        "x-amz-access-token: {$accessToken}",
        "Authorization: {$signatureDetails['algorithm']} Credential={$credentials['client_id']}/{$signatureDetails['dateStamp']}/{$signatureDetails['region']}/{$signatureDetails['service']}/aws4_request, SignedHeaders={$signatureDetails['signedHeaders']}, Signature={$signatureDetails['signature']}"
    ];
}

function buildQueryString($nextToken = null)
{
    global $db;

    $query = "SELECT * FROM aws_orders_reference ORDER BY id DESC LIMIT 1";

    $timeresult = $db->query($query);
    if ($timeresult === false) {
        // Handle query error
        echo "Error executing query: " . $db->error;
        exit;
    }

    if ($timeresult->num_rows > 0) {
        while ($row = $timeresult->fetch_assoc()) {
            $starttime = $row['start_time'] ?? '';
            echo "Time from DB" . $starttime . "<br>";
            if (!empty($starttime)) {
                $date = new DateTime($starttime, new DateTimeZone('America/Los_Angeles')); // Replace with your database timezone
                $lastUpdatedTime = $date->format("Y-m-d\TH:i:s\Z");
                echo "Setting the start time from DB: " . $lastUpdatedTime . "<br>";
            } else {
                $starttime = gmdate('Y-m-d\TH:i:s\Z', strtotime('-1 minutes'));
                echo "Unable to scan database! Setting time from script: " . $starttime . "<br>";
                $lastUpdatedTime = $starttime;
            }
        }
    } else {
        $starttime = gmdate('Y-m-d\TH:i:s\Z', strtotime('-1 minutes'));
        echo "Unable to scan database! Setting time from script: " . $starttime . "<br>";
        $lastUpdatedTime = $starttime;
    }



    // $FulfillmentChannels = "AFN";
    // query parameter
    $query = "MarketplaceIds=ATVPDKIKX0DER&LastUpdatedAfter={$lastUpdatedTime}";

    // $query .= "&FulfillmentChannels={$FulfillmentChannels}";

    if ($nextToken) {
        $query .= "&NextToken=" . urlencode($nextToken);
    }

    return $query;
}

function calculateSignature($credentials, $amzDate)
{
    global $service, $region, $path;

    // Step 1: Create Canonical Request
    $method = 'GET';
    $canonicalUri = $path;
    $canonicalQueryString = buildQueryString();
    $canonicalHeaders = "host:sellingpartnerapi-na.amazon.com\nx-amz-date:{$amzDate}\n";
    $signedHeaders = 'host;x-amz-date';
    $payloadHash = hash('sha256', ''); // Empty payload for GET request
    $canonicalRequest = "{$method}\n{$canonicalUri}\n{$canonicalQueryString}\n{$canonicalHeaders}\n{$signedHeaders}\n{$payloadHash}";

    // Step 2: Create String to Sign
    $algorithm = 'AWS4-HMAC-SHA256';
    $dateStamp = substr($amzDate, 0, 8);
    $credentialScope = "{$dateStamp}/{$region}/{$service}/aws4_request";
    $stringToSign = "{$algorithm}\n{$amzDate}\n{$credentialScope}\n" . hash('sha256', $canonicalRequest);

    // Step 3: Calculate Signature
    $signatureKey = getSignatureKey($credentials['client_secret'], $dateStamp, $region, $service);
    $signature = hash_hmac('sha256', $stringToSign, $signatureKey);

    return [
        'algorithm' => $algorithm,
        'dateStamp' => $dateStamp,
        'signedHeaders' => $signedHeaders,
        'signature' => $signature,
        'region' => $region,
        'service' => $service
    ];
}

function getSignatureKey($key, $dateStamp, $regionName, $serviceName)
{
    $kSecret = 'AWS4' . $key;
    $kDate = hash_hmac('sha256', $dateStamp, $kSecret, true);
    $kRegion = hash_hmac('sha256', $regionName, $kDate, true);
    $kService = hash_hmac('sha256', $serviceName, $kRegion, true);
    $kSigning = hash_hmac('sha256', 'aws4_request', $kService, true);
    return $kSigning;
}


function fetchOrderItems($credentials, $accessToken, $amazonOrderId)
{
    global $endpoint, $service, $region, $pdo; // Added $pdo for database dbion

    do {

        $path = "/orders/v0/orders/{$amazonOrderId}/orderItems";

        $headers = headers_orders_items($credentials, $accessToken);

        $url = "{$endpoint}{$path}";

        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

        $result = curl_exec($ch);

        $httpcode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        echo '<br>';
        // print_r($httpcode);

        // handles the error code 429
        if ($httpcode == 429) {
            echo "<br>Rate limit exceeded, retrying in 60 seconds...  For Orders Items ...<br>";
            sleep(60); // Sleep for 60 seconds
            // Don't forget to close the cURL session before retrying
            curl_close($ch);
        } else if ($httpcode == 401) {
            echo "Unauthorized Access Retrying!\n";

            $accessToken = fetchAccessToken($credentials);

            if ($accessToken) {
                $_SESSION['access_token'] = $accessToken;
                // echo "Access Token: " . $accessToken . "\n";
            } else {
                echo "Access token not found in the response.\n";
            }

            curl_close($ch);
        } else {
            // If the response code is not 429, break out of the loop.
            break;
        }
    } while ($httpcode == 429 || $httpcode == 401);

    $data = json_decode($result, true);

    echo "<pre>";
    // print_r($data);
    echo "</pre>";

    if (isset($data['errors'])) {
        echo "Error Order Items: " . $data['errors'][0]['message'] . "<br>";
        return [];
    }
    curl_close($ch);



    return $data;
}

function Query_Orders_id($nextToken = null)
{


    $query = "";

    // &CreatedAfter={$createdAfter}&MaxResultPerPage=2 



    return $query;
}

function headers_orders_items($credentials, $accessToken)
{
    $amzDate = gmdate('Ymd\THis\Z');
    $signatureDetails = calculateSignature($credentials, $amzDate);

    return [
        "x-amz-date: {$amzDate}",
        "x-amz-access-token: {$accessToken}",
        "Authorization: {$signatureDetails['algorithm']} Credential={$credentials['client_id']}/{$signatureDetails['dateStamp']}/{$signatureDetails['region']}/{$signatureDetails['service']}/aws4_request, SignedHeaders={$signatureDetails['signedHeaders']}, Signature={$signatureDetails['signature']}"
    ];
}

function getAWSCredentials($db, $store)
{
    if ($store == 'Renovar Tech') {
        $id = 6; // The id you want to retrieve
    } else if ($store == 'All Renewed') {
        $id = 10;
    }
    $sql = "SELECT client_id, client_secret, refresh_token FROM tblstores WHERE store_id = $id";
    $result = $db->query($sql);
    $row = $result->fetch_assoc();

    if (!$row) {
        die("No keys found for the given client ID.");
    }

    return $row;
}

function isoToMysqlDatetime($isoString)
{
    if (empty($isoString)) return null;

    $date = new DateTime($isoString, new DateTimeZone('UTC'));
    $date->setTimezone(new DateTimeZone('America/Los_Angeles'));

    return $date->format('Y-m-d H:i:s');
}