<?php
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

if (!function_exists('getInternalByASIN')) {
    function getInternalByASIN(string $ASIN): string
    {
        if (empty($ASIN)) {
            return '';
        }

        // Query the database using Query Builder
        $internal = DB::table('tblasin')
            ->where('ASIN', $ASIN)
            ->value('internal');

        // Return the result or an empty string if not found
        return $internal ?? '';
    }
}

if (!function_exists('getAmazonTitleByASIN')) {
    function getAmazonTitleByASIN(string $ASIN): string
    {
        if (empty($ASIN)) {
            return '';
        }

        $amazonTitle = DB::table('tblasin')
            ->where('ASIN', $ASIN)
            ->value('amazon_title');

        return $amazonTitle ?? '';
    }
}

if (!function_exists('getShipDate')) {
    function getShipDate(string $AmazonOrderId, string $orderitemid): string
    {
        if (empty($AmazonOrderId) || empty($orderitemid)) {
            return '';
        }

        $shipDate = DB::table('tbllabelhistoryitems')
            ->where('AmazonOrderId', $AmazonOrderId)
            ->where('orderitemid', $orderitemid)
            ->value('shipDate');

        return $shipDate ?? '';
    }
}

if (!function_exists('DeliveryExperience')) {
    function DeliveryExperience(string $AmazonOrderId): string
    {
        if (empty($AmazonOrderId)) {
            return '';
        }

        $deliveryExperience = DB::table('tbllabelhistoryitems')
            ->where('AmazonOrderId', $AmazonOrderId)
            ->value('DeliveryExperience');

        return $deliveryExperience ?? '';
    }
}

if (!function_exists('getEarliestShipDate')) {
    function getEarliestShipDate(string $AmazonOrderId, string $orderitemid): string
    {
        if (empty($AmazonOrderId) || empty($orderitemid)) {
            return '';
        }

        $earliestShipDate = DB::table('tblshiphistory')
            ->where('AmazonOrderId', $AmazonOrderId)
            ->where('orderitemid', $orderitemid)
            ->value('EarliestShipDate');

        return $earliestShipDate ?? '';
    }
}

if (!function_exists('getLatestShipDate')) {
    function getLatestShipDate(string $AmazonOrderId, string $orderitemid): string
    {
        if (empty($AmazonOrderId) || empty($orderitemid)) {
            return '';
        }

        $latestShipDate = DB::table('tblshiphistory')
            ->where('AmazonOrderId', $AmazonOrderId)
            ->where('orderitemid', $orderitemid)
            ->value('LatestShipDate');

        return $latestShipDate ?? '';
    }
}

if (!function_exists('getEarliestDeliveryDate')) {
    function getEarliestDeliveryDate(string $AmazonOrderId, string $orderitemid): string
    {
        if (empty($AmazonOrderId) || empty($orderitemid)) {
            return '';
        }

        $earliestDeliveryDate = DB::table('tbllabelhistoryitems')
            ->where('AmazonOrderId', $AmazonOrderId)
            ->where('orderitemid', $orderitemid)
            ->value('EarliestEstimatedDeliveryDate');

        return $earliestDeliveryDate ?? '';
    }
}

if (!function_exists('getLatestDeliveryDate')) {
    function getLatestDeliveryDate(string $AmazonOrderId, string $orderitemid): string
    {
        if (empty($AmazonOrderId) || empty($orderitemid)) {
            return '';
        }

        $latestDeliveryDate = DB::table('tbllabelhistoryitems')
            ->where('AmazonOrderId', $AmazonOrderId)
            ->where('orderitemid', $orderitemid)
            ->value('LatestEstimatedDeliveryDate');

        return $latestDeliveryDate ?? '';
    }
}

if (!function_exists('convertNumberToCustomCode')) {
    function convertNumberToCustomCode($number): string
    {
        $singleDigitMapping = [
            1 => 'A',
            2 => 'B',
            3 => 'C',
            4 => 'D',
            5 => 'E',
            6 => 'F',
            7 => 'G',
            8 => 'H',
            9 => 'I',
            0 => 'X'
        ];

        if (empty($number)) {
            return "XX.XX";
        }

        $numberStr = (string) $number;
        $parts = explode('.', $numberStr);

        $result = '';
        foreach (str_split($parts[0]) as $digit) {
            $result .= $singleDigitMapping[(int) $digit];
        }

        if (isset($parts[1])) {
            $result .= '.';
            foreach (str_split($parts[1]) as $digit) {
                $result .= $singleDigitMapping[(int) $digit];
            }
        }

        return $result;
    }
}

if (!function_exists('fetchNote')) {
    function fetchNote(string $amazonOrderId, ?string $productId = null): string
    {
        try {
            if (!empty($productId)) {
                $note = DB::table('tblproduct')
                    ->where('ProductID', $productId)
                    ->value('shipmentnotes');

                return $note ?? 'N/A';
            } else {
                $note = DB::table('tblshiphistory')
                    ->where('AmazonOrderId', $amazonOrderId)
                    ->value('ordernote');

                return $note ?? 'N/A';
            }
        } catch (\Exception $e) {
            \Log::error("Error fetching note: " . $e->getMessage());
            return 'N/A';
        }
    }
}

if (!function_exists('getLCode')) {
    function getLCode(string $AmazonOrderId): string
    {
        $labelPrice = DB::table('tbllabelhistory')
            ->where('amazonOrderId', $AmazonOrderId)
            ->value('labelprice');

        if (!empty($labelPrice) && $labelPrice != 0) {
            return $labelPrice;
        }

        $labelPrice = DB::table('tblshiphistory')
            ->where('amazonOrderId', $AmazonOrderId)
            ->value('labelprice');

        return $labelPrice ?? '00.00';
    }
}

if (!function_exists('getinvoicenumberid')) {
    function getinvoicenumberid(string $AmazonOrderId): ?string
    {
        $invoiceNumberId = DB::table('tbllabelhistory')
            ->where('amazonOrderId', $AmazonOrderId)
            ->value('invoicenumberid');

        return $invoiceNumberId;
    }
}

if (!function_exists('getUser')) {
    function getUser(string $AmazonOrderId): string
    {
        $user = DB::table('tbllabelhistory')
            ->where('AmazonOrderId', $AmazonOrderId)
            ->value('user');

        return $user ?? ' ';
    }
}

if (!function_exists('getshipmentid')) {
    function getshipmentid(string $tracking_number): string
    {
        $shipmentId = DB::table('tbllabelhistory')
            ->where('trackingid', $tracking_number)
            ->value('shipmentid');

        return $shipmentId ?? ' ';
    }
}

if (!function_exists('getlabelhistoryrawr')) {
    function getlabelhistoryrawr(string $tracking_number): string
    {
        $shipmentId = DB::table('tbllabelhistory')
            ->where('trackingid', $tracking_number)
            ->value('shipmentid');

        return $shipmentId ?? ' ';
    }
}


function convertImageToZPL($testprint, $imagePath, $maxWidth = 1250, $maxHeight = 1100, $bottomRightNumber = "0313", )
{
    $originalImg = imagecreatefrompng($imagePath);
    $origWidth = imagesx($originalImg);
    $origHeight = imagesy($originalImg);

    // Resize while maintaining aspect ratio
    $aspectRatio = $origWidth / $origHeight;
    if ($origWidth > $origHeight) {
        $newWidth = $maxWidth;
        $newHeight = $maxWidth / $aspectRatio;
    } else {
        $newHeight = $maxHeight;
        $newWidth = $maxHeight * $aspectRatio;
    }

    $newWidth = min($newWidth, $maxWidth);
    $newHeight = min($newHeight, $maxHeight);

    // Resize image
    $resizedImg = imagecreatetruecolor($newWidth, $newHeight);
    imagecopyresampled($resizedImg, $originalImg, 0, 0, 0, 0, $newWidth, $newHeight, $origWidth, $origHeight);

    // Ensure width is a multiple of 8 (Zebra printers require this)
    $paddedWidth = ceil($newWidth / 8) * 8;
    $bytesPerRow = $paddedWidth / 8;

    $binaryData = "";

    for ($y = 0; $y < $newHeight; $y++) {
        $rowBinary = "";
        for ($x = 0; $x < $paddedWidth; $x++) {
            if ($x < $newWidth) {
                $colorIndex = imagecolorat($resizedImg, $x, $y);
                $rgba = imagecolorsforindex($resizedImg, $colorIndex);
                $gray = ($rgba['red'] + $rgba['green'] + $rgba['blue']) / 3;
                $rowBinary .= ($gray < 128) ? "1" : "0"; // Black = 1, White = 0
            } else {
                $rowBinary .= "0"; // Padding if width is not a multiple of 8
            }
        }

        // Convert binary row to hex (8 bits = 1 byte)
        for ($i = 0; $i < strlen($rowBinary); $i += 8) {
            $byte = substr($rowBinary, $i, 8);
            $binaryData .= str_pad(dechex(bindec($byte)), 2, "0", STR_PAD_LEFT); // Convert to hex
        }
    }

    // Calculate total bytes
    $totalBytes = strlen($binaryData) / 2;

    // Construct ZPL command
    $zpl = "^XA\n"; // Start Label
    $zpl .= "^FO50,50\n"; // Position image
    $zpl .= "^GFA,$totalBytes,$totalBytes,$bytesPerRow,";
    $zpl .= strtoupper($binaryData) . "\n";
    /*
    // **Add Bottom-Right Number**
    $textX = 710;  // Adjust based on label size
    $textY = $newHeight + 70; // Position below the image
    $zpl .= "^FO{$textX},{$textY}^A0N,30,30^FD$bottomRightNumber^FS\n"; // Bottom-right text
    */
    if ($testprint) {
        // Define label dimensions (in dots, assuming 203 DPI)
        $labelWidth = 800;  // 4 inches * 203 DPI
        $labelHeight = 1200; // 6 inches * 203 DPI

        // Set larger font size
        $fontSize = 100; // Adjust for larger text
        $charWidth = 100; // Adjust for proportional spacing

        // Center horizontally
        $textLength = strlen("TEST PRINT") * ($charWidth / 2); // Approximate text width
        $textX = ($labelWidth - $textLength) / 2; // Center horizontally

        // Position towards the bottom (adjust as needed)
        $textY = $labelHeight - 200; // Place it 200 dots above the bottom

        // $textX = 400;
        $textY = $newHeight - 200;
        $zpl .= "^FO{$textX},{$textY}^A0N,{$fontSize},{$charWidth}^FDTEST PRINT^FS\n";
    }

    $zpl .= "^XZ\n"; // End Label

    return $zpl;
}