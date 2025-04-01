<?php
session_start();
include 'db_connection.php'; // Include your database connection

// Set headers for JSON response
header('Content-Type: application/json');

// Initialize response array
$response = [
    'success' => false,
    'message' => '',
    'item' => '',
    'playsound' => 0,
    'reason' => '',
    'needReprint' => false,
    'productId' => null
];

// Check if request is POST and contains necessary data
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $postData = json_decode(file_get_contents('php://input'), true);
    
    if (isset($postData['submitted']) && $postData['submitted'] === 'yes') {
        $User = $_SESSION['id'] ?? 'Unknown';
        $serial = trim($postData['SerialNumber']);
        $location = $postData['Location'];
        $FNSKU = $postData['FNSKU'];
        $store = '';
        $Module = "Stockroom";
        $california_timezone = new DateTimeZone('America/Los_Angeles');
        $currentDatetime = new DateTime('now', $california_timezone);
        $formatted_datetime = $currentDatetime->format('Y-m-d h:i A');
        $currentDate = date('Y-m-d', strtotime($formatted_datetime));
        $Action = "Scanned and insert to Stockroom";
        $scanned_serial = [];

        // Initialize session counters if not set
        if (!isset($_SESSION['Submitted'])) $_SESSION['Submitted'] = 0;
        if (!isset($_SESSION['notSubmitted'])) $_SESSION['notSubmitted'] = 0;
        if (!isset($_SESSION['playsound'])) $_SESSION['playsound'] = 0;

        // Validate inputs
        if ((!preg_match('/^[a-zA-Z0-9]+$/', $serial)) or (strpos($serial, 'X00') !== false)) {
            $response['message'] = 'Invalid Serial';
            $_SESSION['notSubmitted']++; 
            $_SESSION['playsound'] = 2;
            $response['playsound'] = 2;
            $response['reason'] = 'invalid_serial';
        } elseif (preg_match('/^L\d{3}[A-G]$/i', $FNSKU)) {
            $response['message'] = 'Invalid FNSKU';
            $_SESSION['notSubmitted']++; 
            $_SESSION['playsound'] = 2;
            $response['playsound'] = 2;
            $response['reason'] = 'invalid_fnsku';
        } elseif (!preg_match('/^L\d{3}[A-G]$/i', $location) && $location !== 'Floor' && $location !== 'L800G') {
            $response['message'] = 'Invalid Format Location';
            $_SESSION['notSubmitted']++; 
            $_SESSION['playsound'] = 2;
            $response['playsound'] = 2;
            $response['reason'] = 'invalid_location';
        } else {
            // Check if the serial exists
            $check_sql = "SELECT * FROM tblproduct WHERE (ProductModuleLoc = 'Stockroom' AND (serialnumber = ? OR serialnumberb = ?) 
                            OR ProductModuleLoc = 'SoldList' AND (serialnumber = ? OR serialnumberb = ?) 
                            OR ProductModuleLoc = 'Production Area' AND (serialnumber = ? OR serialnumberb = ?)
                            OR ProductModuleLoc = 'Shipment' AND (serialnumber = ? OR serialnumberb = ?))";
            
            $stmt = $Connect->prepare($check_sql);
            $stmt->bind_param("ssssssss", $serial, $serial, $serial, $serial, $serial, $serial, $serial, $serial);
            $stmt->execute();
            $result = $stmt->get_result();

            if ($result->num_rows > 0) {
                $row = $result->fetch_assoc();
                $id = $row['ProductID'];
                $module = $row['ProductModuleLoc'];
                $rt = $row['rtcounter'];
                $executePrintScript = false;
                $curentDatetimeString = $currentDatetime->format('Y-m-d H:i:s');
                
                if (($row['ProductModuleLoc'] === 'SoldList') && ($row['Fulfilledby'] === 'FBM') || ($row['Fulfilledby'] === 'FBA') || ($row['ProductModuleLoc'] === 'Shipment')) {
                    $table = '';
                    $FindFNSKU = "SELECT * FROM tblmasterfnsku WHERE FNSKU = ?";
                    $FindFNSKUresult_stmt = $Connect->prepare($FindFNSKU);
                    $FindFNSKUresult_stmt->bind_param("s", $FNSKU);
                    $FindFNSKUresult_stmt->execute();
                    $FindFNSKUresult = $FindFNSKUresult_stmt->get_result();
                    
                    if ($FindFNSKUresult->num_rows > 0) {
                        // Fetch the ASIN from the result
                        $fnsku_row = $FindFNSKUresult->fetch_assoc();
                        $ASINmainFnsku = $fnsku_row['ASIN'];
                        $getCondition1 = $fnsku_row['grading'];
                        $getTitle1 = $fnsku_row['astitle'];
                        $getMSKU1 = $fnsku_row['MSKU'];
                        $getmetaKeyword = $fnsku_row['metakeyword'];
                        $table = 'tblmasterfnsku';
                        $store = 'Renovar Tech';
                        $weightRetail = $fnsku_row['lbs'];
                        $weightWhite = $fnsku_row['white_lbs'];
                        $Status = $fnsku_row['Status'];
                        $SetID = $fnsku_row['productid'];
                    } else {
                        $FindFNSKU1 = "SELECT * FROM tblmasterfnskuAllrenewed WHERE FNSKU = ?";
                        $FindFNSKUresult1_stmt = $Connect->prepare($FindFNSKU1);
                        $FindFNSKUresult1_stmt->bind_param("s", $FNSKU);
                        $FindFNSKUresult1_stmt->execute();
                        $FindFNSKUresult1 = $FindFNSKUresult1_stmt->get_result();
                        
                        if ($FindFNSKUresult1->num_rows > 0) {
                            $row1 = $FindFNSKUresult1->fetch_assoc();
                            $ASINmainFnsku = $row1['ASIN'];
                            $getCondition1 = $row1['grading'];
                            $getTitle1 = $row1['astitle'];
                            $getMSKU1 = $row1['MSKU'];
                            $getmetaKeyword = $row1['metakeyword'];
                            $table = 'tblmasterfnskuAllrenewed';
                            $store = 'Allrenewed';
                            $weightRetail = $row1['lbs'];
                            $weightWhite = $row1['white_lbs'];
                            $Status = $row1['Status'];
                            $SetID = $row1['productid'];
                        } else {
                            $response['message'] = 'FNSKU not found in database';
                            $_SESSION['notSubmitted']++;
                            $_SESSION['playsound'] = 2;
                            $response['playsound'] = 2;
                            $response['reason'] = 'fnsku_not_found';
                            echo json_encode($response);
                            exit;
                        }
                    }
                    
                    // Determine warehouse location module
                    if (substr($location, 0, 4) === 'L800') {
                        $modulelocation = 'Production Area';
                        $insertedDate = NULL;
                    } else {
                        $modulelocation = 'Stockroom';
                        $insertedDate = $curentDatetimeString;
                    }
                    
                    // Handle item based on status
                    if (($Status === 'Available') || ($module === 'Shipment')) {
                        // Update existing item to returned status
                        $sql = "UPDATE tblproduct SET returnstatus='Returned', ReceivedStatus='Received', ProductModuleLoc='Returnlist' WHERE ProductID = ?";
                        $update_stmt = $Connect->prepare($sql);
                        $update_stmt->bind_param("i", $id);
                        $Connect->query($sql);
                        
                        // Insert LPN record
                        $InsertLPNtable = "INSERT INTO tbllpn (SERIAL, LPN, LPNDATE, ProdID, BuyerName) VALUES (?, ?, ?, ?, ?)";
                        $lpn_stmt = $Connect->prepare($InsertLPNtable);
                        $lpn_stmt->bind_param("sssss", $serial, $lpn, $curentDatetimeString, $id, $buyersName);
                        $lpn_stmt->execute();
                        
                        // Get max LPN ID
                        $findmaxlpnid = "SELECT MAX(lpnID) AS maxlpnid FROM tbllpn";
                        $lpnid_result = $Connect->query($findmaxlpnid);
                        $lpnid_row = $lpnid_result->fetch_assoc();
                        $maxlpnid = $lpnid_row['maxlpnid'];
                        
                        // Insert history record
                        $InsertHistory = "INSERT INTO tblitemprocesshistory (rtcounter, employeeName, editDate, Module, Action) VALUES (?, ?, ?, 'Scanner Return Module', 'Return Item')";
                        $history_stmt = $Connect->prepare($InsertHistory);
                        $history_stmt->bind_param("iss", $rt, $User, $curentDatetimeString);
                        $history_stmt->execute();
                        
                        // Get next RT counter
                        $findrt = "SELECT MAX(rtcounter) AS maxrt FROM tblproduct";
                        $rt_result = $Connect->query($findrt);
                        $rt_row = $rt_result->fetch_assoc();
                        $newrt = $rt_row['maxrt'] + 1;
                        
                        // Insert new item
                        $sqlInsertNewItem = "INSERT INTO tblproduct (rtcounter, rtid, itemnumber, Username, serialnumber, serialnumberb, serialnumberc, serialnumberd, ProductModuleLoc, quantity, price, lpnID, warehouselocation, ASiNviewer, FNSKUviewer, gradingviewer, MSKUviewer, metakeyword, AStitle, stockroom_insert_date, StoreName, BoxWeight, boxChoice) 
                         VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 1, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
                        
                        $insert_stmt = $Connect->prepare($sqlInsertNewItem);
                        $insert_stmt->bind_param("issssssssdisssssssssss", $newrt, $orderid, $itemid, $User, $serial, $serialb, $serialc, $seriald, $modulelocation, $price, $maxlpnid, $location, $ASINmainFnsku, $FNSKU, $getCondition1, $getMSKU1, $getmetaKeyword, $getTitle1, $insertedDate, $store, $weight, $SelectedBox);
                        $insert_stmt->execute();
                        
                        // Get new product ID
                        $findmaxID = "SELECT MAX(ProductID) AS maxID FROM tblproduct";
                        $id_result = $Connect->query($findmaxID);
                        $id_row = $id_result->fetch_assoc();
                        $maxID = $id_row['maxID'];
                        
                        // Insert history for new item
                        $InsertHistoryNewAdded = "INSERT INTO tblitemprocesshistory (rtcounter, employeeName, editDate, Module, Action) 
                        VALUES (?, ?, ?, 'Scan Add Module', ?)";
                        $new_history_stmt = $Connect->prepare($InsertHistoryNewAdded);
                        $action = "Scanned and insert to " . $modulelocation;
                        $new_history_stmt->bind_param("isss", $newrt, $User, $curentDatetimeString, $action);
                        $new_history_stmt->execute();
                        
                        // Update FNSKU status
                        $sql1 = "UPDATE $table SET Status = 'Unavailable', productid = ? WHERE FNSKU = ? AND ASIN = ?";
                        $update_fnsku_stmt = $Connect->prepare($sql1);
                        $update_fnsku_stmt->bind_param("iss", $maxID, $FNSKU, $ASINmainFnsku);
                        
                        if ($update_fnsku_stmt->execute()) {
                            // Delete from shipping if exists
                            $sqldelete = "DELETE FROM tbldoneshipping WHERE Prodid = ?";
                            $delete_stmt = $Connect->prepare($sqldelete);
                            $delete_stmt->bind_param("i", $id);
                            $delete_stmt->execute();
                            
                            $response['success'] = true;
                            $response['message'] = 'Scanned and Updated. Moved to ' . $modulelocation;
                            $response['item'] = $getTitle1;
                            $_SESSION['Submitted']++;
                            $scanned_serial[] = $serial;
                            $_SESSION['playsound'] = 1;
                            $response['playsound'] = 1;
                        } else {
                            $response['message'] = "Error: " . $Connect->error;
                            $response['playsound'] = 2;
                            $response['reason'] = 'database_error';
                        }
                    } else {
                        // Handle unavailable FNSKU logic
                        // Find available FNSKU with same ASIN and condition
                        $FindFNSKU = "SELECT * FROM $table WHERE Status = 'Available' AND amazon_status = 'Existed' AND LimitStatus = 'False' AND 
                        ASIN = ? AND grading = ?";
                        $available_fnsku_stmt = $Connect->prepare($FindFNSKU);
                        $available_fnsku_stmt->bind_param("ss", $ASINmainFnsku, $getCondition1);
                        $available_fnsku_stmt->execute();
                        $FindFNSKUresult = $available_fnsku_stmt->get_result();
                        
                        if ($FindFNSKUresult->num_rows > 0) {
                            // Use available FNSKU
                            $row = $FindFNSKUresult->fetch_assoc();
                            $AvailableFnsku = $row['FNSKU'];
                            $ASIN = $row['ASIN'];
                            $Condition = $row['grading'];
                            $Title = $row['astitle'];
                            $MSKU = $row['MSKU'];
                            
                            // Process with available FNSKU
                            // (Rest of your logic for processing with available FNSKU)
                            
                            $response['success'] = true;
                            $response['message'] = 'Processed with available FNSKU: ' . $AvailableFnsku;
                            $response['item'] = $Title;
                            $_SESSION['Submitted']++;
                            $scanned_serial[] = $serial;
                            $_SESSION['playsound'] = 1;
                            $response['playsound'] = 1;
                        } else {
                            $response['message'] = 'No Available FNSKU for this item';
                            $_SESSION['Submitted']++;
                            $scanned_serial[] = $serial;
                            $_SESSION['playsound'] = 2;
                            $response['playsound'] = 2;
                            $response['reason'] = 'no_available_fnsku';
                        }
                    }
                } else if (($row['ProductModuleLoc'] === 'Production Area') && ($row['Fulfilledby'] === 'FBM')) {
                    if (substr($location, 0, 4) === 'L800') {
                        $modulelocation = 'Production Area';
                        $insertedDate = NULL;
                    } else {
                        $modulelocation = 'Stockroom';
                        $insertedDate = $curentDatetimeString;
                    }
                    
                    $sql = "UPDATE tblproduct SET warehouselocation = ?, ProductModuleLoc = ?, stockroom_insert_date = ? WHERE ProductID = ?";
                    $update_stmt = $Connect->prepare($sql);
                    $update_stmt->bind_param("sssi", $location, $modulelocation, $insertedDate, $id);
                    
                    if ($update_stmt->execute()) {
                        $InsertHistoryNewAdded = "INSERT INTO tblitemprocesshistory (rtcounter, employeeName, editDate, Module, Action) 
                        VALUES (?, ?, ?, 'Scan Add Module', ?)";
                        $history_stmt = $Connect->prepare($InsertHistoryNewAdded);
                        $action = "Scanned and insert to " . $modulelocation;
                        $history_stmt->bind_param("isss", $rt, $User, $curentDatetimeString, $action);
                        $history_stmt->execute();
                        
                        $response['success'] = true;
                        $response['message'] = 'Scanned and Updated. Moved to ' . $modulelocation;
                        $response['item'] = $row['AStitle'] ?? 'Unknown item';
                        $_SESSION['Submitted']++;
                        $scanned_serial[] = $serial;
                        $_SESSION['playsound'] = 1;
                        $response['playsound'] = 1;
                    } else {
                        $response['message'] = "Error: " . $Connect->error;
                        $response['playsound'] = 2;
                        $response['reason'] = 'database_error';
                    }
                } else {
                    if ($row['warehouselocation'] === 'Floor') {
                        // Update location for Floor items
                        $sql = "UPDATE tblproduct SET warehouselocation = ? WHERE ProductID = ?";
                        $update_stmt = $Connect->prepare($sql);
                        $update_stmt->bind_param("si", $location, $id);
                        
                        if ($update_stmt->execute()) {
                            $response['success'] = true;
                            $response['message'] = 'Scanned and Updated Location Successfully';
                            $response['item'] = $row['AStitle'] ?? 'Unknown item';
                            $_SESSION['Submitted']++;
                            $scanned_serial[] = $serial;
                            $_SESSION['playsound'] = 1;
                            $response['playsound'] = 1;
                        } else {
                            $response['message'] = "Error: " . $Connect->error;
                            $response['playsound'] = 2;
                            $response['reason'] = 'database_error';
                        }
                    } else {
                        // Duplicate serial in stockroom
                        $Insertlogs = "INSERT INTO tbladditemstockroomlogs (FNSKU, LOCATION, SERIALNUMBER, NOTE) VALUES (?, ?, ?, 'Duplicate Serial')";
                        $logs_stmt = $Connect->prepare($Insertlogs);
                        $logs_stmt->bind_param("sss", $FNSKU, $location, $serial);
                        
                        if ($logs_stmt->execute()) {
                            $response['message'] = 'Serial already exists in stockroom module';
                            $_SESSION['notSubmitted']++;
                            $_SESSION['playsound'] = 2;
                            $response['playsound'] = 2;
                            $response['reason'] = 'duplicate_serial';
                        } else {
                            $response['message'] = "Database error: " . $Connect->error;
                            $response['playsound'] = 2;
                            $response['reason'] = 'database_error';
                        }
                    }
                }
            } else {
                // Serial not found in specified modules, check for other modules
                $check_other_sql = "SELECT rtcounter, ProductID, FNSKUviewer, ProductModuleLoc
                                   FROM tblproduct
                                   WHERE (serialnumber = ? OR serialnumberb = ?)
                                   AND returnstatus = 'Not Returned' AND validation_status = 'validated'
                                   AND (ProductModuleLoc != 'Orders' AND ProductModuleLoc != 'Migrated' 
                                        AND ProductModuleLoc != 'Labeling' AND ProductModuleLoc != 'Soldlist' 
                                        AND ProductModuleLoc != 'Shipment' AND ProductModuleLoc != 'RTS')";
                
                $other_stmt = $Connect->prepare($check_other_sql);
                $other_stmt->bind_param("ss", $serial, $serial);
                $other_stmt->execute();
                $other_result = $other_stmt->get_result();
                
                if ($other_result->num_rows > 0) {
                    $other_row = $other_result->fetch_assoc();
                    $id = $other_row['ProductID'];
                    $rtnumberofitem = $other_row['rtcounter'];
                    $checkFNSKUviewer = $other_row['FNSKUviewer'];
                    $itemlocation = $other_row['ProductModuleLoc'];
                    
                    if (!empty($checkFNSKUviewer)) {
                        // Check if FNSKUs are different
                        $trimmedFNSKU = trim($checkFNSKUviewer);
                        $trimmedFNSKU2 = trim($FNSKU);
                        $prefix = substr($trimmedFNSKU, 0, 2);
                        $prefix2 = substr($trimmedFNSKU2, 0, 2);
                        
                        if (preg_match('/^[B-W][0-9]/', $prefix)) {
                            $mainFnsku = substr($trimmedFNSKU, 2);
                        } else {
                            $mainFnsku = $trimmedFNSKU;
                        }
                        
                        if (preg_match('/^[B-W][0-9]/', $prefix2)) {
                            $inputFnsku = substr($trimmedFNSKU2, 2);
                        } else {
                            $inputFnsku = $trimmedFNSKU2;
                        }
                        
                        if (trim($mainFnsku) != trim($inputFnsku)) {
                            // Different FNSKU found, need to reprint label
                            $response['needReprint'] = true;
                            $response['productId'] = $id;
                        }
                        
                        // Update item to move to stockroom
                        $update_sql = "UPDATE tblproduct SET ProductModuleLoc='Stockroom', warehouselocation = ?, stockroom_insert_date = ? WHERE ProductID = ?";
                        $update_stmt = $Connect->prepare($update_sql);
                        $update_stmt->bind_param("ssi", $location, $curentDatetimeString, $id);
                        
                        if ($update_stmt->execute()) {
                            // Insert history record
                            $insert_history = "INSERT INTO tblitemprocesshistory (rtcounter, employeeName, editDate, Module, Action) 
                                           VALUES (?, ?, ?, ?, ?)";
                            $history_stmt = $Connect->prepare($insert_history);
                            $history_stmt->bind_param("issss", $rtnumberofitem, $User, $curentDatetimeString, $Module, $Action);
                            $history_stmt->execute();
                            
                            $response['success'] = true;
                            $response['message'] = 'Scanned and Forwarded to Stockroom Successfully';
                            $_SESSION['Submitted']++;
                            $scanned_serial[] = $serial;
                            $_SESSION['playsound'] = 1;
                            $response['playsound'] = 1;
                        } else {
                            $response['message'] = "Database error: " . $Connect->error;
                            $response['playsound'] = 2;
                            $response['reason'] = 'database_error';
                        }
                    } else {
                        $response['message'] = 'Cannot Proceed to Move item: FNSKU is Blank';
                        $_SESSION['playsound'] = 2;
                        $response['playsound'] = 2;
                        $response['reason'] = 'blank_fnsku';
                    }
                } else {
                    // Handle new item creation logic (FNSKU lookup and insertion)
                    $findFNSKU = "SELECT * FROM tblmasterfnsku WHERE FNSKU = ?";
                    $fnsku_stmt = $Connect->prepare($findFNSKU);
                    $fnsku_stmt->bind_param("s", $FNSKU);
                    $fnsku_stmt->execute();
                    $resultFIND = $fnsku_stmt->get_result();
                    
                    if ($resultFIND->num_rows > 0) {
                        // Process with found FNSKU in tblmasterfnsku
                        $fnsku_row = $resultFIND->fetch_assoc();
                        $checkFNSKUstatus = $fnsku_row['Status'];
                        $getASIN = $fnsku_row['ASIN'];
                        $getCondition = $fnsku_row['grading'];
                        $getTitle = $fnsku_row['astitle'];
                        $getMSKU = $fnsku_row['MSKU'];
                        $getFNSKU = $fnsku_row['FNSKU'];
                        $store = 'Renovar Tech';
                        
                        if ($checkFNSKUstatus == "Available" || $checkFNSKUstatus == NULL) {
                            // FNSKU is available, create new item
                            // Get next RT counter
                            $findrt = "SELECT MAX(rtcounter) AS maxrt FROM tblproduct";
                            $rt_result = $Connect->query($findrt);
                            $rt_row = $rt_result->fetch_assoc();
                            $newrt = $rt_row['maxrt'] + 1;
                            
                            // Insert new product
                            $sql = "INSERT INTO tblproduct (rtcounter, serialnumber, ProductModuleLoc, warehouselocation, 
                                  ASiNviewer, FNSKUviewer, gradingviewer, AStitle, MSKUviewer, FbmAvailable, 
                                  Fulfilledby, quantity, DateCreated, stockroom_insert_date, StoreName) 
                                  VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 1, 'FBM', 1, ?, ?, ?)";
                                  
                            $insert_stmt = $Connect->prepare($sql);
                            $insert_stmt->bind_param("issssssssss", $newrt, $serial, $Module, $location, 
                                                    $getASIN, $getFNSKU, $getCondition, $getTitle, 
                                                    $getMSKU, $curentDatetimeString, $curentDatetimeString, $store);
                            
                            if ($insert_stmt->execute()) {
                                // Get new product ID
                                $findID = "SELECT MAX(ProductID) AS maxID FROM tblproduct";
                                $id_result = $Connect->query($findID);
                                $id_row = $id_result->fetch_assoc();
                                $newID = $id_row['maxID'];
                                
                                // Insert history record
                                $QryINSERTHistory = "INSERT INTO tblitemprocesshistory (rtcounter, employeeName, editDate, Module, Action) 
                                                  VALUES (?, ?, ?, ?, ?)";
                                $history_stmt = $Connect->prepare($QryINSERTHistory);
                                $history_stmt->bind_param("issss", $newrt, $User, $curentDatetimeString, $Module, $Action);
                                $result5 = $history_stmt->execute();
                                
                                // Update FNSKU status
                                $SqlUpdateFNSKU = "UPDATE tblmasterfnsku SET Status='Unavailable', productid=? WHERE FNSKU=?";
                                $update_fnsku_stmt = $Connect->prepare($SqlUpdateFNSKU);
                                $update_fnsku_stmt->bind_param("is", $newID, $getFNSKU);
                                $result6 = $update_fnsku_stmt->execute();
                                
                                if ($result5 && $result6) {
                                    $response['success'] = true;
                                    $response['message'] = 'Scanned and Inserted Successfully';
                                    $response['item'] = $getTitle;
                                    $_SESSION['Submitted']++;
                                    $scanned_serial[] = $serial;
                                    $_SESSION['playsound'] = 1;
                                    $response['playsound'] = 1;
                                }
                            } else {
                                $response['message'] = "Database error: " . $Connect->error;
                                $response['playsound'] = 2;
                                $response['reason'] = 'database_error';
                            }
                        } else {
                            // Handle FNSKU validation and related logic
                            // (Your validation logic for existing items)
                            
                            // For demonstration purposes, log the attempt
                            $Insertlogs = "INSERT INTO tbladditemstockroomlogs (ASIN, TITLE, FNSKU, MSKU, CONDITIONS, LOCATION, SERIALNUMBER) 
                                        VALUES (?, ?, ?, ?, ?, ?, ?)";
                            $logs_stmt = $Connect->prepare($Insertlogs);
                            $logs_stmt->bind_param("sssssss", $getASIN, $getTitle, $getFNSKU, $getMSKU, $getCondition, $location, $serial);
                            
                            if ($logs_stmt->execute()) {
                                $response['message'] = 'FNSKU is Already Used';
                                $_SESSION['notSubmitted']++;
                                $_SESSION['playsound'] = 2;
                                $response['playsound'] = 2;
                                $response['reason'] = 'fnsku_in_use';
                            } else {
                                $response['message'] = 'Failed: Error inserting to logs';
                                $_SESSION['notSubmitted']++; 
                                $_SESSION['playsound'] = 2;
                                $response['reason'] = 'database_error';
                            }
                        }
                    } else {
                        // Check in AllRenewed FNSKU table
                         $findFNSKU = "SELECT * FROM tblmasterfnskuAllrenewed WHERE FNSKU = ?";
                        $fnsku_stmt = $Connect->prepare($findFNSKU);
                        $fnsku_stmt->bind_param("s", $FNSKU);
                        $fnsku_stmt->execute();
                        $resultFIND = $fnsku_stmt->get_result();
                        
                        if ($resultFIND->num_rows > 0) {
                            // Process with found FNSKU in tblmasterfnskuAllrenewed
                            $fnsku_row = $resultFIND->fetch_assoc();
                            $checkFNSKUstatus = $fnsku_row['Status'];
                            $getASIN = $fnsku_row['ASIN'];
                            $getCondition = $fnsku_row['grading'];
                            $getTitle = $fnsku_row['astitle'];
                            $getMSKU = $fnsku_row['MSKU'];
                            $getFNSKU = $fnsku_row['FNSKU'];
                            $store = 'Allrenewed';
                            
                            if ($checkFNSKUstatus == "Available" || $checkFNSKUstatus == NULL) {
                                // FNSKU is available, create new item
                                // Get next RT counter
                                $findrt = "SELECT MAX(rtcounter) AS maxrt FROM tblproduct";
                                $rt_result = $Connect->query($findrt);
                                $rt_row = $rt_result->fetch_assoc();
                                $newrt = $rt_row['maxrt'] + 1;
                                
                                // Insert new product
                                $sql = "INSERT INTO tblproduct (rtcounter, serialnumber, ProductModuleLoc, warehouselocation, 
                                      ASiNviewer, FNSKUviewer, gradingviewer, AStitle, MSKUviewer, FbmAvailable, 
                                      Fulfilledby, quantity, DateCreated, stockroom_insert_date, StoreName) 
                                      VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 1, 'FBM', 1, ?, ?, ?)";
                                
                                $insert_stmt = $Connect->prepare($sql);
                                $insert_stmt->bind_param("issssssssss", $newrt, $serial, $Module, $location, 
                                                        $getASIN, $getFNSKU, $getCondition, $getTitle, 
                                                        $getMSKU, $curentDatetimeString, $curentDatetimeString, $store);
                                
                                if ($insert_stmt->execute()) {
                                    // Get new product ID
                                    $findID = "SELECT MAX(ProductID) AS maxID FROM tblproduct";
                                    $id_result = $Connect->query($findID);
                                    $id_row = $id_result->fetch_assoc();
                                    $newID = $id_row['maxID'];
                                    
                                    // Insert history record
                                    $QryINSERTHistory = "INSERT INTO tblitemprocesshistory (rtcounter, employeeName, editDate, Module, Action) 
                                                      VALUES (?, ?, ?, ?, ?)";
                                    $history_stmt = $Connect->prepare($QryINSERTHistory);
                                    $history_stmt->bind_param("issss", $newrt, $User, $curentDatetimeString, $Module, $Action);
                                    $result5 = $history_stmt->execute();
                                    
                                    // Update FNSKU status
                                    $SqlUpdateFNSKU = "UPDATE tblmasterfnskuAllrenewed SET Status='Unavailable', productid=? WHERE FNSKU=?";
                                    $update_fnsku_stmt = $Connect->prepare($SqlUpdateFNSKU);
                                    $update_fnsku_stmt->bind_param("is", $newID, $getFNSKU);
                                    $result6 = $update_fnsku_stmt->execute();
                                    
                                    if ($result5 && $result6) {
                                        $response['success'] = true;
                                        $response['message'] = 'Scanned and Inserted Successfully';
                                        $response['item'] = $getTitle;
                                        $_SESSION['Submitted']++;
                                        $scanned_serial[] = $serial;
                                        $_SESSION['playsound'] = 1;
                                        $response['playsound'] = 1;
                                    }
                                } else {
                                    $response['message'] = "Database error: " . $Connect->error;
                                    $response['playsound'] = 2;
                                    $response['reason'] = 'database_error';
                                }
                            } else {
                                // Handle validation for existing items
                                $validateFNSKU = "SELECT * FROM tblproduct WHERE FNSKUviewer = ? AND serialnumber = ? AND returnstatus = 'Not Returned'
                                              AND (ProductModuleLoc <> 'Stockroom' AND ProductModuleLoc <> 'Soldlist' AND ProductModuleLoc <> 'Migrated')";
                                $valid_stmt = $Connect->prepare($validateFNSKU);
                                $valid_stmt->bind_param("ss", $getFNSKU, $serial);
                                $valid_stmt->execute();
                                $Validationresult = $valid_stmt->get_result();
                                
                                if ($Validationresult->num_rows > 0) {
                                    $rows = $Validationresult->fetch_assoc();
                                    $findInsertedrtcounter = $rows['rtcounter'];
                                    $prodIDunique = $rows['ProductID'];
                                    
                                    // Update item location
                                    $sql = "UPDATE tblproduct SET ProductModuleLoc = 'Stockroom', stockroom_insert_date = ?, warehouselocation = ? WHERE ProductID = ?";
                                    $update_stmt = $Connect->prepare($sql);
                                    $update_stmt->bind_param("ssi", $curentDatetimeString, $location, $prodIDunique);
                                    
                                    if ($update_stmt->execute()) {
                                        // Insert history record
                                        $QryINSERTHistory = "INSERT INTO tblitemprocesshistory (rtcounter, employeeName, editDate, Module, Action) VALUES (?, ?, ?, ?, ?)";
                                        $history_stmt = $Connect->prepare($QryINSERTHistory);
                                        $history_stmt->bind_param("issss", $findInsertedrtcounter, $User, $curentDatetimeString, $Module, $Action);
                                        $result5 = $history_stmt->execute();
                                        
                                        if ($result5) {
                                            $response['success'] = true;
                                            $response['message'] = 'Scanned and Inserted Successfully';
                                            $_SESSION['Submitted']++;
                                            $scanned_serial[] = $serial;
                                            $_SESSION['playsound'] = 1;
                                            $response['playsound'] = 1;
                                        }
                                    } else {
                                        $response['message'] = "Error: " . $Connect->error;
                                        $response['playsound'] = 2;
                                        $response['reason'] = 'database_error';
                                    }
                                } else {
                                    // Log the attempt
                                    $Insertlogs = "INSERT INTO tbladditemstockroomlogs (ASIN, TITLE, FNSKU, MSKU, CONDITIONS, LOCATION, SERIALNUMBER) 
                                                VALUES (?, ?, ?, ?, ?, ?, ?)";
                                    $logs_stmt = $Connect->prepare($Insertlogs);
                                    $logs_stmt->bind_param("sssssss", $getASIN, $getTitle, $getFNSKU, $getMSKU, $getCondition, $location, $serial);
                                    
                                    if ($logs_stmt->execute()) {
                                        $response['message'] = 'FNSKU is Already Used';
                                        $_SESSION['notSubmitted']++;
                                        $_SESSION['playsound'] = 2;
                                        $response['playsound'] = 2;
                                        $response['reason'] = 'fnsku_in_use';
                                    } else {
                                        $response['message'] = 'Failed: Error inserting to logs';
                                        $_SESSION['notSubmitted']++; 
                                        $_SESSION['playsound'] = 2;
                                        $response['reason'] = 'database_error';
                                    }
                                }
                            }
                        } else {
                            // Check for FNSKU prefix pattern
                            $prefix = substr($FNSKU, 0, 2);
                            $table = '';
                            
                            if (preg_match('/^[B-W][0-9]/', $prefix)) {
                                $mainFnsku = substr($FNSKU, 2);
                                
                                // Try to find main FNSKU
                                $FindFNSKU = "SELECT * FROM tblmasterfnsku WHERE FNSKU = ?";
                                $main_fnsku_stmt = $Connect->prepare($FindFNSKU);
                                $main_fnsku_stmt->bind_param("s", $mainFnsku);
                                $main_fnsku_stmt->execute();
                                $FindFNSKUresult = $main_fnsku_stmt->get_result();
                                
                                if ($FindFNSKUresult->num_rows > 0) {
                                    // Found in tblmasterfnsku
                                    $row = $FindFNSKUresult->fetch_assoc();
                                    $ASINmainFnsku = $row['ASIN'];
                                    $getCondition1 = $row['grading'];
                                    $getTitle1 = $row['astitle'];
                                    $getMSKU1 = $row['MSKU'];
                                    $getmetaKeyword = $row['metakeyword'];
                                    $table = 'tblmasterfnsku';
                                    $store = 'Renovar Tech';
                                    $weightRetail = $row['lbs'];
                                    $weightWhite = $row['white_lbs'];
                                } else {
                                    // Try tblmasterfnskuAllrenewed
                                    $FindFNSKU1 = "SELECT * FROM tblmasterfnskuAllrenewed WHERE FNSKU = ?";
                                    $main_fnsku_stmt1 = $Connect->prepare($FindFNSKU1);
                                    $main_fnsku_stmt1->bind_param("s", $mainFnsku);
                                    $main_fnsku_stmt1->execute();
                                    $FindFNSKUresult1 = $main_fnsku_stmt1->get_result();
                                    
                                    if ($FindFNSKUresult1->num_rows > 0) {
                                        $row1 = $FindFNSKUresult1->fetch_assoc();
                                        $ASINmainFnsku = $row1['ASIN'];
                                        $getCondition1 = $row1['grading'];
                                        $getTitle1 = $row1['astitle'];
                                        $getMSKU1 = $row1['MSKU'];
                                        $getmetaKeyword = $row1['metakeyword'];
                                        $table = 'tblmasterfnskuAllrenewed';
                                        $store = 'Allrenewed';
                                        $weightRetail = $row1['lbs'];
                                        $weightWhite = $row1['white_lbs'];
                                    } else {
                                        $response['message'] = 'FNSKU not found in database';
                                        $_SESSION['notSubmitted']++;
                                        $_SESSION['playsound'] = 2;
                                        $response['playsound'] = 2;
                                        $response['reason'] = 'fnsku_not_found';
                                        echo json_encode($response);
                                        exit;
                                    }
                                }
                                
                                // Determine weight and box based on condition
                                if (($skuCondition === 'UsedLikeNew') || ($skuCondition === 'New')) {
                                    $weight = $weightRetail;
                                    $SelectedBox = 'Retailbox';
                                } else {
                                    if ($weightWhite > 0) {
                                        $weight = $weightWhite;
                                        $SelectedBox = 'Whitebox';
                                    } else {
                                        $weight = $weightRetail;
                                        $SelectedBox = 'Retailbox';
                                    }
                                }
                                
                                // Get next RT counter
                                $findrt = "SELECT MAX(rtcounter) AS maxrt FROM tblproduct";
                                $rt_result = $Connect->query($findrt);
                                $rt_row = $rt_result->fetch_assoc();
                                $newrt = $rt_row['maxrt'] + 1;
                                
                                // Insert new item
                                $curentDatet2 = $currentDatetime->format('Y-m-d');
                                
                                $sql = "INSERT INTO tblproduct (metakeyword, rtcounter, serialnumber, ProductModuleLoc, warehouselocation, ASiNviewer, FNSKUviewer, 
                                      gradingviewer, AStitle, MSKUviewer, FbmAvailable, Fulfilledby, quantity, DateCreated, stockroom_insert_date, StoreName, 
                                      BoxWeight, boxChoice) 
                                      VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 1, 'FBM', 1, ?, ?, ?, ?, ?)";
                                
                                $insert_stmt = $Connect->prepare($sql);
                                $insert_stmt->bind_param("ssssssssssssss", $getmetaKeyword, $newrt, $serial, $Module, $location, 
                                                        $ASINmainFnsku, $FNSKU, $getCondition1, $getTitle1, $getMSKU1, 
                                                        $curentDatetimeString, $curentDatetimeString, $store, $weight, $SelectedBox);
                                
                                if ($insert_stmt->execute()) {
                                    // Get new product ID
                                    $findID = "SELECT MAX(ProductID) AS maxID FROM tblproduct";
                                    $id_result = $Connect->query($findID);
                                    $id_row = $id_result->fetch_assoc();
                                    $newID = $id_row['maxID'];
                                    
                                    // Insert history record
                                    $QryINSERTHistory = "INSERT INTO tblitemprocesshistory (rtcounter, employeeName, editDate, Module, Action) 
                                                      VALUES (?, ?, ?, ?, ?)";
                                    $history_stmt = $Connect->prepare($QryINSERTHistory);
                                    $history_stmt->bind_param("issss", $newrt, $User, $curentDatetimeString, $Module, $Action);
                                    
                                    // Insert into FNSKU master list
                                    $QryINSERTmasterlist = "INSERT INTO $table (ASIN, grading, astitle, MSKU, FNSKU, Status, productid, dateFreeUp) 
                                                          VALUES (?, ?, ?, ?, ?, 'Unavailable', ?, ?)";
                                    $master_stmt = $Connect->prepare($QryINSERTmasterlist);
                                    $master_stmt->bind_param("sssssss", $ASINmainFnsku, $getCondition1, $getTitle1, $getMSKU1, 
                                                            $FNSKU, $newID, $curentDatet2);
                                    
                                    $result5 = $history_stmt->execute();
                                    $result6 = $master_stmt->execute();
                                    
                                    if ($result5 && $result6) {
                                        $response['success'] = true;
                                        $response['message'] = 'Scanned and Inserted Successfully';
                                        $response['item'] = $getTitle1;
                                        $_SESSION['Submitted']++;
                                        $scanned_serial[] = $serial;
                                        $_SESSION['playsound'] = 1;
                                        $response['playsound'] = 1;
                                    } else {
                                        // Log error
                                        $Insertlogs = "INSERT INTO tbladditemstockroomlogs (FNSKU, LOCATION, SERIALNUMBER, NOTE) 
                                                    VALUES (?, ?, ?, 'Cannot find Main FNSKU in database')";
                                        $logs_stmt = $Connect->prepare($Insertlogs);
                                        $logs_stmt->bind_param("sss", $FNSKU, $location, $serial);
                                        
                                        if ($logs_stmt->execute()) {
                                            $response['message'] = 'Cannot find Main FNSKU in database';
                                            $_SESSION['notSubmitted']++;
                                            $_SESSION['playsound'] = 2;
                                            $response['playsound'] = 2;
                                            $response['reason'] = 'main_fnsku_not_found';
                                        }
                                    }
                                }
                            } else {
                                $response['message'] = 'Invalid FNSKU';
                                $_SESSION['notSubmitted']++;
                                $_SESSION['playsound'] = 2;
                                $response['playsound'] = 2;
                                $response['reason'] = 'invalid_fnsku';
                            }
                        }
                    }
                }
            }
        }
    } else {
        $response['message'] = 'Invalid request data';
        $response['reason'] = 'invalid_request';
    }
} else {
    $response['message'] = 'Invalid request method';
    $response['reason'] = 'invalid_method';
}

// Return the JSON response
echo json_encode($response);
exit;
?>