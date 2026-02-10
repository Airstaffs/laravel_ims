<?php
// Check for overdue deliveries based on estimated_deliverydate

$mysqli = new mysqli("localhost", "imsv2_dbims_user", "Imsv2_dbims_user", "imsv2_dbims");

if ($mysqli->connect_error) {
    die("Connection failed: " . $mysqli->connect_error);
}

$today = date('Y-m-d');

// Find orders where:
// 1. Delivery status is not final (not Delivered/Cancelled/Refunded)
// 2. Estimated delivery date has passed
// 3. Module is Orders

$query = "
    UPDATE tblproduct 
    SET delivery_status = 'Delivery Overdue',
        updated_at = NOW()
    WHERE ProductModuleLoc = 'Orders'
    AND delivery_status NOT IN ('Delivered', 'Cancelled', 'Refunded', 'Delivery Overdue')
    AND estimated_deliverydate IS NOT NULL
    AND estimated_deliverydate != ''
    AND estimated_deliverydate != '0000-00-00'
    AND STR_TO_DATE(
        SUBSTRING_INDEX(estimated_deliverydate, ' to ', -1), 
        '%Y-%m-%d'
    ) < ?
";

$stmt = $mysqli->prepare($query);
$stmt->bind_param("s", $today);

if ($stmt->execute()) {
    $affected = $stmt->affected_rows;
    echo "✅ Updated {$affected} orders to 'Delivery Overdue' status<br>";
} else {
    echo "❌ Error: " . $stmt->error . "<br>";
}

$stmt->close();
$mysqli->close();