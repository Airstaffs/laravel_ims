<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Log file path
$logFile = __DIR__ . '/duplicate_orders_log.txt';
file_put_contents($logFile, "==== Duplicate Orders Skipped Log ====" . PHP_EOL);

// SOURCE DB: u298641722_ims (tblshiphistory)
$host1 = 'localhost';
$db1 = 'u298641722_ims';
$user1 = 'u298641722_web_ims';
$pass1 = 'ImsHosting!11923';

// TARGET DB: u298641722_dbims (tbloutboundorders, tbloutboundordersitem)
$host2 = 'localhost';
$db2 = 'u298641722_dbims';
$user2 = 'u298641722_dbims_user';
$pass2 = '?cIk=|zRk3T';

try {
    // Connect to source
    $pdo_source = new PDO("mysql:host=$host1;dbname=$db1;charset=utf8mb4", $user1, $pass1);
    $pdo_source->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // Connect to target
    $pdo_target = new PDO("mysql:host=$host2;dbname=$db2;charset=utf8mb4", $user2, $pass2);
    $pdo_target->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // === STEP 1: Insert orders with logging on duplicates ===
    $orderStmt = $pdo_source->query("
        SELECT MAX(amzn_id) AS outboundorderid, AmazonOrderId, costumer_name, BuyerEmail,
               AddressLine1, AddressLine2, AddressLine3, StateOrRegion, PostalCode,
               City, CountryCode, PaymentMethod, OrderType, PurchaseDate,
               datedelivered, ship_to_name, ShipmentServiceLevelCategory,
               FulfillmentChannel, NumberOfItemsShipped, NumberOfItemsUnshipped
        FROM tblshiphistory
        GROUP BY AmazonOrderId
    ");

    $insertOrder = $pdo_target->prepare("
        INSERT INTO tbloutboundorders (
          outboundorderid, platform_order_id, BuyerName, BuyerEmail, address_line1, address_line2,
          AddressLine3, StateOrRegion, postal_code, city, CountryCode, PaymentMethod, OrderType,
          PurchaseDate, ship_date, delivery_date, ShiptoName, ShipmentServiceLevelCategory,
          FulfillmentChannel, NumberOfItemsShipped, NumberOfItemsUnshipped, created_at, updated_at
        ) VALUES (
          :outboundorderid, :platform_order_id, :BuyerName, :BuyerEmail, :address_line1, :address_line2,
          :AddressLine3, :StateOrRegion, :postal_code, :city, :CountryCode, :PaymentMethod, :OrderType,
          :PurchaseDate, NULL, :delivery_date, :ShiptoName, :ShipmentServiceLevelCategory,
          :FulfillmentChannel, :NumberOfItemsShipped, :NumberOfItemsUnshipped, NOW(), NOW()
        )
    ");

    foreach ($orderStmt as $order) {
        try {
            $insertOrder->execute([
                ':outboundorderid' => $order['outboundorderid'],
                ':platform_order_id' => $order['AmazonOrderId'],
                ':BuyerName' => $order['costumer_name'],
                ':BuyerEmail' => $order['BuyerEmail'],
                ':address_line1' => $order['AddressLine1'],
                ':address_line2' => $order['AddressLine2'],
                ':AddressLine3' => $order['AddressLine3'],
                ':StateOrRegion' => $order['StateOrRegion'],
                ':postal_code' => $order['PostalCode'],
                ':city' => $order['City'],
                ':CountryCode' => $order['CountryCode'],
                ':PaymentMethod' => $order['PaymentMethod'],
                ':OrderType' => $order['OrderType'],
                ':PurchaseDate' => $order['PurchaseDate'],
                ':delivery_date' => $order['datedelivered'],
                ':ShiptoName' => $order['ship_to_name'],
                ':ShipmentServiceLevelCategory' => $order['ShipmentServiceLevelCategory'],
                ':FulfillmentChannel' => $order['FulfillmentChannel'],
                ':NumberOfItemsShipped' => $order['NumberOfItemsShipped'],
                ':NumberOfItemsUnshipped' => $order['NumberOfItemsUnshipped'],
            ]);
        } catch (PDOException $e) {
            file_put_contents($logFile, "❌ Skipped outboundorderid {$order['outboundorderid']} (AmazonOrderId: {$order['AmazonOrderId']}) — Duplicate
", FILE_APPEND);
        }
    }

    // === STEP 2: Insert items (outboundorderitemid is auto-incremented) ===
    $itemStmt = $pdo_source->query("SELECT * FROM tblshiphistory WHERE orderitemid IS NOT NULL");

    $insertItem = $pdo_target->prepare("
        INSERT IGNORE INTO tbloutboundordersitem (
          platform_order_id, platform_order_item_id, platform_sku, platform_asin,
          platform_title, ConditionSubtypeId, ConditionId, NumberOfItemsShipped, NumberOfItemsUnshipped,
          FulfillmentChannel, QuantityOrdered, QuantityShipped, trackingnumber, carrier, carrier_description,
          unit_price, unit_tax, IsBuyerRequestedCancel, BuyerCancelReason, created_at, updated_at, storename
        ) VALUES (
          :platform_order_id, :platform_order_item_id, :platform_sku, :platform_asin,
          :platform_title, :ConditionSubtypeId, :ConditionId, :NumberOfItemsShipped, :NumberOfItemsUnshipped,
          :FulfillmentChannel, :QuantityOrdered, :QuantityShipped, :trackingnumber, :carrier, :carrier_description,
          :unit_price, :unit_tax, :IsBuyerRequestedCancel, :BuyerCancelReason, NOW(), NOW(), :storename
        )
    ");

    foreach ($itemStmt as $item) {
        $insertItem->execute([
            ':platform_order_id' => $item['AmazonOrderId'],
            ':platform_order_item_id' => $item['orderitemid'],
            ':platform_sku' => $item['SellerSKU'],
            ':platform_asin' => $item['ASIN'],
            ':platform_title' => $item['Title'],
            ':ConditionSubtypeId' => $item['ConditionSubtypeId'],
            ':ConditionId' => $item['ConditionId'],
            ':NumberOfItemsShipped' => $item['NumberOfItemsShipped'],
            ':NumberOfItemsUnshipped' => $item['NumberOfItemsUnshipped'],
            ':FulfillmentChannel' => $item['FulfillmentChannel'],
            ':QuantityOrdered' => $item['QuantityOrdered'],
            ':QuantityShipped' => $item['QuantityShipped'],
            ':trackingnumber' => $item['tracking_number'],
            ':carrier' => $item['carrier'],
            ':carrier_description' => $item['courier_sheeesh'],
            ':unit_price' => $item['ItemPrice'],
            ':unit_tax' => $item['ItemTax'],
            ':IsBuyerRequestedCancel' => $item['IsBuyerRequestedCancel'],
            ':BuyerCancelReason' => $item['BuyerCancelReason'],
            ':storename' => $item['strname'],
        ]);
    }

    echo "✅ Migration completed successfully. Check duplicate_orders_log.txt for skipped entries.";
} catch (PDOException $e) {
    echo "❌ Error: " . $e->getMessage();
}
?>
