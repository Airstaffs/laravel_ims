<?php

namespace App\Services;

use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;
use Exception;

class ImageProcessingService
{
    protected $phpQrcodePath;
    protected $fontsPath;
    protected $imagesPath;
    protected $Connect;
    
    public function __construct()
    {
        // Set paths relative to Laravel app structure
        $this->phpQrcodePath = app_path('Http/Controllers/printer/phpqrcode/qrlib.php');
        $this->fontsPath = public_path('fonts/arial.ttf');
        $this->imagesPath = storage_path('app/public/images');
        
        // Create directories if they don't exist
        $this->createDirectories();
        
        // Include QR code library
        if (file_exists($this->phpQrcodePath)) {
            require_once $this->phpQrcodePath;
        } else {
            Log::warning('QR code library not found at: ' . $this->phpQrcodePath);
        }
        
        // Initialize database connection (using Laravel's DB facade)
        $this->Connect = DB::connection()->getPdo();
    }
    
    /**
     * Create necessary directories
     */
    protected function createDirectories()
    {
        $directories = [
            $this->imagesPath,
            $this->imagesPath . '/qrcodeSerial',
            $this->imagesPath . '/qrcode',
            $this->imagesPath . '/qrcodeManual',
            $this->imagesPath . '/monochrome',
            $this->imagesPath . '/vector',
            $this->imagesPath . '/temp',
            storage_path('app/public/images/serial_qr'),
            storage_path('app/public/images/usermanual'),
            storage_path('app/public/images/instructioncard'),
            storage_path('app/public/images/warranty/generated_images'),
            storage_path('app/public/images/warranty/templates')
        ];
        
        foreach ($directories as $directory) {
            if (!is_dir($directory)) {
                mkdir($directory, 0755, true);
            }
        }
    }
    
    /**
     * Convert image QR for serial number
     */
    public function convertImageQRserial($serial)
    {
        try {
            $serialfind = $serial;
            $seriallink = storage_path('app/public/images/serial_qr/' . $serialfind . '.png');
            $manual = url('storage/serial_qr/' . $serialfind . '.png');
            
            $qrCodePath = $this->imagesPath . '/qrcodeSerial/' . $serialfind . '.png';
            
            // Generate QR code
            if (class_exists('QRcode')) {
                \QRcode::png($manual, $qrCodePath, QR_ECLEVEL_L, 5);
            } else {
                Log::warning('QRcode class not available');
                return $this->generateSimpleQRZpl($serial);
            }
            
            if (!file_exists($qrCodePath)) {
                Log::warning('QR code image not generated: ' . $qrCodePath);
                return $this->generateSimpleQRZpl($serial);
            }
            
            $qrCodeImage = imagecreatefrompng($qrCodePath);
            if (!$qrCodeImage) {
                Log::warning('Failed to load QR code image');
                return $this->generateSimpleQRZpl($serial);
            }
            
            $outputImageWidth = 400;
            $outputImageHeight = 250;
            
            $image = imagecreatetruecolor($outputImageWidth, $outputImageHeight);
            $white = imagecolorallocate($image, 255, 255, 255);
            imagefill($image, 0, 0, $white);
            
            $availableWidthForQRCode = $outputImageWidth - 40;
            $availableHeightForQRCode = $outputImageHeight - 80;
            
            $qrScaleFactor = min($availableWidthForQRCode / imagesx($qrCodeImage), $availableHeightForQRCode / imagesy($qrCodeImage));
            
            // Add explicit casting to integers for these calculated dimensions
            $scaledQrCodeWidth = (int)(imagesx($qrCodeImage) * $qrScaleFactor);
            $scaledQrCodeHeight = (int)(imagesy($qrCodeImage) * $qrScaleFactor);
            
            $scaledQrCodeImage = imagecreatetruecolor($scaledQrCodeWidth, $scaledQrCodeHeight);
            imagecopyresampled($scaledQrCodeImage, $qrCodeImage, 0, 0, 0, 0, $scaledQrCodeWidth, $scaledQrCodeHeight, imagesx($qrCodeImage), imagesy($qrCodeImage));
            imagedestroy($qrCodeImage);
            
            // Move QR code slightly higher and add explicit casting
            $dstX = (int)(($outputImageWidth - $scaledQrCodeWidth) / 2);
            $dstY = 5; // Moved higher
            imagecopy($image, $scaledQrCodeImage, $dstX, $dstY, 0, 0, $scaledQrCodeWidth, $scaledQrCodeHeight);
            imagedestroy($scaledQrCodeImage);
            
            $bottomText1 = "Scan QR to see Saved Photos";
            $bottomText2 = "of this Item on the Cloud.";
            
            $black = imagecolorallocate($image, 0, 0, 0);
            $bottomFontSize = 14;
            
            // Use font if available, otherwise use built-in font
            if (file_exists($this->fontsPath)) {
                // Move bottom text slightly higher and add explicit casting
                $textBoxBottom1 = imagettfbbox($bottomFontSize, 0, $this->fontsPath, $bottomText1);
                $textWidthBottom1 = abs($textBoxBottom1[4] - $textBoxBottom1[0]);
                $textXBottom1 = (int)(($outputImageWidth - $textWidthBottom1) / 2);
                $textYBottom1 = $outputImageHeight - 50; // Moved higher
                
                imagettftext($image, $bottomFontSize, 0, $textXBottom1, $textYBottom1, $black, $this->fontsPath, $bottomText1);
                
                $textBoxBottom2 = imagettfbbox($bottomFontSize, 0, $this->fontsPath, $bottomText2);
                $textWidthBottom2 = abs($textBoxBottom2[4] - $textBoxBottom2[0]);
                $textXBottom2 = (int)(($outputImageWidth - $textWidthBottom2) / 2);
                $textYBottom2 = $textYBottom1 + 18; // Reduced spacing
                
                imagettftext($image, $bottomFontSize, 0, $textXBottom2, $textYBottom2, $black, $this->fontsPath, $bottomText2);
            } else {
                // Use built-in font
                $textWidthBottom1 = strlen($bottomText1) * 10; // Approximate width
                $textXBottom1 = (int)(($outputImageWidth - $textWidthBottom1) / 2);
                $textYBottom1 = $outputImageHeight - 50;
                
                imagestring($image, 3, $textXBottom1, $textYBottom1, $bottomText1, $black);
                
                $textWidthBottom2 = strlen($bottomText2) * 10;
                $textXBottom2 = (int)(($outputImageWidth - $textWidthBottom2) / 2);
                $textYBottom2 = $textYBottom1 + 18;
                
                imagestring($image, 3, $textXBottom2, $textYBottom2, $bottomText2, $black);
            }
            
            // Convert to ZPL
            $binaryString = "";
            for ($y = 0; $y < $outputImageHeight; $y++) {
                for ($x = 0; $x < $outputImageWidth; $x++) {
                    $color = imagecolorat($image, $x, $y);
                    $binaryString .= ($color & 0xFF) > 128 ? '0' : '1';
                }
            }
            
            imagedestroy($image);
            
            $hexString = '';
            for ($i = 0; $i < strlen($binaryString); $i += 8) {
                $byteString = substr($binaryString, $i, 8);
                $hexString .= str_pad(dechex(bindec($byteString)), 2, '0', STR_PAD_LEFT);
            }
            
            // Use explicit casting for the bytesPerRow calculation
            $bytesPerRow = (int)ceil($outputImageWidth / 8);
            
            $zplCommand = "^XA\n";
            $zplCommand .= "^FO20,20^GFA," . strlen($hexString) / 2 . "," . strlen($hexString) / 2 . "," . $bytesPerRow . "," . $hexString . "^FS\n";
            $zplCommand .= "^XZ";
            
            return $zplCommand;
            
        } catch (Exception $e) {
            Log::error('Error in convertImageQRserial:', [
                'error' => $e->getMessage(),
                'serial' => $serial
            ]);
            
            return $this->generateSimpleQRZpl($serial);
        }
    }
    
    /**
     * Convert image QR for manual
     */
    public function convertImageQRmanual($asinfind, $title)
    {
        try {
            $asinlink = storage_path('app/public/User_manual/ASIN_PDF/' . $asinfind . '.pdf');
            if (file_exists($asinlink)) {
                $manual = url('storage/User_manual/ASIN_PDF/' . $asinfind . '.pdf');
            } else {
                $manual = url('storage/User_manual/ASIN_PDF/' . $asinfind . '.pdf');
            }
            
            $qrCodePath = $this->imagesPath . '/qrcode/' . $asinfind . '.png';
            
            // Generate QR code
            if (class_exists('QRcode')) {
                \QRcode::png($manual, $qrCodePath, QR_ECLEVEL_L, 10); // Reduce error correction to increase size
            } else {
                return $this->generateSimpleManualQRZpl($asinfind, $title);
            }
            
            if (!file_exists($qrCodePath)) {
                return $this->generateSimpleManualQRZpl($asinfind, $title);
            }
            
            $qrCodeImage = imagecreatefrompng($qrCodePath);
            if (!$qrCodeImage) {
                return $this->generateSimpleManualQRZpl($asinfind, $title);
            }
            
            // Set dimensions for the output image
            $outputImageWidth = 400; // Width in pixels
            $outputImageHeight = 200; // Height in pixels
            
            // Create a blank image with the specified dimensions
            $image = imagecreatetruecolor($outputImageWidth, $outputImageHeight);
            
            // Fill the background with white color
            $white = imagecolorallocate($image, 255, 255, 255);
            imagefill($image, 0, 0, $white);
            
            // Add text "Scan Me for User Manual" at the top
            $scanMeText = "Scan Me for User Manual";
            $scanMeFontSize = 20; // Font size for "Scan Me for User Manual"
            $textColor = imagecolorallocate($image, 0, 0, 0); // Black
            
            if (file_exists($this->fontsPath)) {
                // Calculate text bounding box for "Scan Me for User Manual"
                $bbox = imagettfbbox($scanMeFontSize, 0, $this->fontsPath, $scanMeText);
                $scanMeTextWidth = $bbox[2] - $bbox[0];
                $scanMeTextX = ($outputImageWidth - $scanMeTextWidth) / 2; // Center text horizontally
                
                // Adjust this value to move the text closer to the top
                $scanMeTextY = 50; // Move text higher
                
                imagettftext($image, $scanMeFontSize, 0, $scanMeTextX, $scanMeTextY, $textColor, $this->fontsPath, $scanMeText);
            } else {
                // Use built-in font
                $scanMeTextWidth = strlen($scanMeText) * 10;
                $scanMeTextX = ($outputImageWidth - $scanMeTextWidth) / 2;
                $scanMeTextY = 30;
                imagestring($image, 3, $scanMeTextX, $scanMeTextY, $scanMeText, $textColor);
            }
            
            // Calculate QR code size and position
            $availableWidthForQRCode = $outputImageWidth - 40; // Subtract margins
            $availableHeightForQRCode = $outputImageHeight - 50 - 20 - 20; // Subtract text height and extra padding
            
            $qrScaleFactor = min($availableWidthForQRCode / imagesx($qrCodeImage), $availableHeightForQRCode / imagesy($qrCodeImage));
            $scaledQrCodeWidth = imagesx($qrCodeImage) * $qrScaleFactor;
            $scaledQrCodeHeight = imagesy($qrCodeImage) * $qrScaleFactor;
            
            // Scale the QR code
            $scaledQrCodeImage = imagecreatetruecolor($scaledQrCodeWidth, $scaledQrCodeHeight);
            imagecopyresampled($scaledQrCodeImage, $qrCodeImage, 0, 0, 0, 0, $scaledQrCodeWidth, $scaledQrCodeHeight, imagesx($qrCodeImage), imagesy($qrCodeImage));
            imagedestroy($qrCodeImage);
            
            // Merge QR code with the blank image
            $dstX = 20; // Margin from the left
            $dstY = 50 + 20 + 10; // Position QR code just below the text
            imagecopy($image, $scaledQrCodeImage, $dstX, $dstY, 0, 0, $scaledQrCodeWidth, $scaledQrCodeHeight);
            imagedestroy($scaledQrCodeImage);
            
            // Add title text beside the QR code
            $titleFontSize = 20; // Font size for title
            $titleColor = imagecolorallocate($image, 0, 0, 0); // Black
            
            if (file_exists($this->fontsPath)) {
                // Wrap the title text to fit within the image width
                $maxWidth = $outputImageWidth - $dstX - $scaledQrCodeWidth - 30; // Max width for the title text
                $lines = [];
                $words = explode(' ', $title);
                $currentLine = '';
                
                foreach ($words as $word) {
                    $testLine = $currentLine . ($currentLine ? ' ' : '') . $word;
                    $bbox = imagettfbbox($titleFontSize, 0, $this->fontsPath, $testLine);
                    
                    if (($bbox[2] - $bbox[0]) > $maxWidth) {
                        $lines[] = $currentLine;
                        $currentLine = $word;
                    } else {
                        $currentLine = $testLine;
                    }
                }
                $lines[] = $currentLine; // Add the last line
                
                // Calculate title position
                $lineHeight = $titleFontSize + 5; // Space between lines
                $titleY = $dstY + ($scaledQrCodeHeight - (count($lines) * $lineHeight)) / 2; // Center vertically
                
                foreach ($lines as $index => $line) {
                    $bbox = imagettfbbox($titleFontSize, 0, $this->fontsPath, $line);
                    $titleWidth = $bbox[2] - $bbox[0];
                    $titleX = $dstX + $scaledQrCodeWidth + 25; // Padding from the QR code
                    $lineY = $titleY + ($index * $lineHeight) + $titleFontSize; // Vertical position of each line
                    
                    imagettftext($image, $titleFontSize, 0, $titleX, $lineY, $titleColor, $this->fontsPath, $line);
                }
            } else {
                // Use built-in font for title
                $titleX = $dstX + $scaledQrCodeWidth + 25;
                $titleY = $dstY + 20;
                imagestring($image, 2, $titleX, $titleY, substr($title, 0, 30), $titleColor);
            }
            
            // Convert to ZPL
            $binaryString = "";
            
            // Convert image pixels to binary string
            for ($y = 0; $y < $outputImageHeight; $y++) {
                for ($x = 0; $x < $outputImageWidth; $x++) {
                    $color = imagecolorat($image, $x, $y);
                    $binaryString .= ($color & 0xFF) > 128 ? '0' : '1';
                }
            }
            
            // Free up memory
            imagedestroy($image);
            
            // Convert binary string to hexadecimal string
            $hexString = '';
            for ($i = 0; $i < strlen($binaryString); $i += 8) {
                $byteString = substr($binaryString, $i, 8);
                $hexString .= str_pad(dechex(bindec($byteString)), 2, '0', STR_PAD_LEFT);
            }
            
            // Calculate bytes per row
            $bytesPerRow = ceil($outputImageWidth / 8);
            
            // Construct ZPL command
            $zplCommand = "^XA\n";
            $zplCommand .= "^FO20,20^GFA," . strlen($hexString) / 2 . "," . strlen($hexString) / 2 . "," . $bytesPerRow . "," . $hexString . "^FS\n";
            $zplCommand .= "^XZ";
            
            return $zplCommand;
            
        } catch (Exception $e) {
            Log::error('Error in convertImageQRmanual:', [
                'error' => $e->getMessage(),
                'asin' => $asinfind
            ]);
            
            return $this->generateSimpleManualQRZpl($asinfind, $title);
        }
    }
    
    /**
     * Convert image to monochrome
     */
    public function convertImageToMonochrome($inputPath, $outputPath, $newWidth, $newHeight)
    {
        try {
            if (!file_exists($inputPath)) {
                Log::warning('Input image not found: ' . $inputPath);
                return false;
            }
            
            // Check the image type (jpeg, png) and create a new image from file
            $imageInfo = getimagesize($inputPath);
            if (!$imageInfo) {
                Log::warning('Invalid image: ' . $inputPath);
                return false;
            }
            
            switch ($imageInfo[2]) {
                case IMAGETYPE_JPEG:
                    $image = imagecreatefromjpeg($inputPath);
                    break;
                case IMAGETYPE_PNG:
                    $image = imagecreatefrompng($inputPath);
                    break;
                default:
                    Log::warning('Unsupported image type: ' . $inputPath);
                    return false;
            }
            
            if (!$image) {
                Log::warning('Failed to create image resource: ' . $inputPath);
                return false;
            }
            
            // Get original dimensions
            $origWidth = imagesx($image);
            $origHeight = imagesy($image);
            
            // Create a new true color image with the desired dimensions
            $newImage = imagecreatetruecolor($newWidth, $newHeight);
            
            // Copy and resize part of an image with resampling
            imagecopyresampled($newImage, $image, 0, 0, 0, 0, $newWidth, $newHeight, $origWidth, $origHeight);
            
            // Convert to grayscale
            imagefilter($newImage, IMG_FILTER_GRAYSCALE);
            
            // Convert to black and white
            imagefilter($newImage, IMG_FILTER_CONTRAST, -1000);
            
            // Get the filename without the extension
            $fileParts = pathinfo($inputPath);
            $filenameWithoutExt = $fileParts['filename'];
            
            // Determine the file extension based on the input file type
            $fileExtension = ($imageInfo[2] === IMAGETYPE_PNG) ? '.png' : '.jpg';
            
            // Construct the full output path
            $fullOutputPath = $outputPath . '/' . $filenameWithoutExt . $fileExtension;
            
            // Save the image
            if ($imageInfo[2] === IMAGETYPE_PNG) {
                $result = imagepng($newImage, $fullOutputPath);
            } else {
                $result = imagejpeg($newImage, $fullOutputPath);
            }
            
            // Free up memory
            imagedestroy($image);
            imagedestroy($newImage);
            
            return $result;
            
        } catch (Exception $e) {
            Log::error('Error in convertImageToMonochrome:', [
                'error' => $e->getMessage(),
                'inputPath' => $inputPath
            ]);
            
            return false;
        }
    }
    
    /**
     * Convert monochrome image to ZPL
     */
    public function convertMonochromeImageToZPL($monochromeImagePath)
    {
        try {
            if (!file_exists($monochromeImagePath)) {
                Log::warning('Monochrome image not found: ' . $monochromeImagePath);
                return "^XA^FO50,50^ADN,18,18^FDImage not found^FS^XZ";
            }
            
            $fileParts = pathinfo($monochromeImagePath);
            $extension = strtolower($fileParts['extension']);
            
            if ($extension == 'png') {
                $image = imagecreatefrompng($monochromeImagePath);
            } elseif ($extension == 'jpg' || $extension == 'jpeg') {
                $image = imagecreatefromjpeg($monochromeImagePath);
            } else {
                Log::warning('Unsupported image type: ' . $extension);
                return "^XA^FO50,50^ADN,18,18^FDUnsupported image type^FS^XZ";
            }
            
            if (!$image) {
                Log::warning('Unable to load or process the image: ' . $monochromeImagePath);
                return "^XA^FO50,50^ADN,18,18^FDUnable to load image^FS^XZ";
            }
            
            // Get image dimensions
            $width = imagesx($image);
            $height = imagesy($image);
            $binaryString = "";
            
            // Convert image pixels to binary string
            for ($y = 0; $y < $height; $y++) {
                for ($x = 0; $x < $width; $x++) {
                    $color = imagecolorat($image, $x, $y);
                    $binaryString .= ($color & 0xFF) > 128 ? '0' : '1';
                }
            }
            
            // Free up memory
            imagedestroy($image);
            
            // Convert binary string to hexadecimal string
            $hexString = '';
            for ($i = 0; $i < strlen($binaryString); $i += 8) {
                $byteString = substr($binaryString, $i, 8);
                $hexString .= str_pad(dechex(bindec($byteString)), 2, '0', STR_PAD_LEFT);
            }
            
            // Calculate bytes per row
            $bytesPerRow = ceil($width / 8);
            
            // Construct ZPL command
            $zplCommand = "^XA\n";
            $zplCommand .= "^FO0,0^GFA," . strlen($hexString) / 2 . "," . strlen($hexString) / 2 . "," . $bytesPerRow . "," . $hexString . "^FS\n";
            $zplCommand .= "^XZ";
            
            return $zplCommand;
            
        } catch (Exception $e) {
            Log::error('Error in convertMonochromeImageToZPL:', [
                'error' => $e->getMessage(),
                'imagePath' => $monochromeImagePath
            ]);
            
            return "^XA^FO50,50^ADN,18,18^FDError converting image^FS^XZ";
        }
    }
    
    /**
     * Convert image layout to ZPL
     */
    public function convertImageLayout($monochromeImagePath, $asinfind, $basketnumber)
    {
        try {
            if (!file_exists($monochromeImagePath)) {
                Log::warning('Monochrome image not found: ' . $monochromeImagePath);
                return "^XA^FO50,50^ADN,18,18^FDImage not found^FS^XZ";
            }
            
            $fileParts = pathinfo($monochromeImagePath);
            $extension = strtolower($fileParts['extension']);
            
            if ($extension == 'png') {
                $image = imagecreatefrompng($monochromeImagePath);
            } elseif ($extension == 'jpg' || $extension == 'jpeg') {
                $image = imagecreatefromjpeg($monochromeImagePath);
            } else {
                Log::warning('Unsupported image type: ' . $extension);
                return "^XA^FO50,50^ADN,18,18^FDUnsupported image type^FS^XZ";
            }
            
            if (!$image) {
                Log::warning('Unable to load or process the image: ' . $monochromeImagePath);
                return "^XA^FO50,50^ADN,18,18^FDUnable to load image^FS^XZ";
            }
            
            $width = imagesx($image);
            $height = imagesy($image);
            
            $binaryString = "";
            
            // Convert image pixels to binary string
            for ($y = 0; $y < $height; $y++) {
                for ($x = 0; $x < $width; $x++) {
                    $color = imagecolorat($image, $x, $y);
                    $binaryString .= ($color & 0xFF) > 128 ? '0' : '1';
                }
            }
            
            // Free up memory
            imagedestroy($image);
            
            // Convert binary string to hexadecimal string
            $hexString = '';
            for ($i = 0; $i < strlen($binaryString); $i += 8) {
                $byteString = substr($binaryString, $i, 8);
                $hexString .= str_pad(dechex(bindec($byteString)), 2, '0', STR_PAD_LEFT);
            }
            
            // Calculate bytes per row
            $bytesPerRow = ceil($width / 8);
            
            // Construct ZPL command
            $zplCommand = "^XA\n";
            $zplCommand .= "^FO0,50^GFA," . strlen($hexString) / 2 . "," . strlen($hexString) / 2 . "," . $bytesPerRow . "," . $hexString . "^FS\n";
            $zplCommand .= "^XZ";
            
            return $zplCommand;
            
        } catch (Exception $e) {
            Log::error('Error in convertImageLayout:', [
                'error' => $e->getMessage(),
                'imagePath' => $monochromeImagePath
            ]);
            
            return "^XA^FO50,50^ADN,18,18^FDError converting image layout^FS^XZ";
        }
    }
    
    /**
     * Convert image with proper resizing and filtering
     */
    public function convertImage($inputPath, $outputPath, $newWidth, $newHeight)
    {
        try {
            if (!file_exists($inputPath)) {
                Log::warning('Input image not found: ' . $inputPath);
                throw new Exception('Input image not found');
            }
            
            // Check the image type (jpeg, png) and create a new image from file
            $imageInfo = getimagesize($inputPath);
            if (!$imageInfo) {
                throw new Exception('Invalid image file');
            }
            
            switch ($imageInfo[2]) {
                case IMAGETYPE_JPEG:
                    $image = imagecreatefromjpeg($inputPath);
                    break;
                case IMAGETYPE_PNG:
                    $image = imagecreatefrompng($inputPath);
                    break;
                default:
                    throw new Exception('Unsupported image type.');
            }
            
            if (!$image) {
                throw new Exception('Failed to create image resource');
            }
            
            // Get original dimensions
            $origWidth = imagesx($image);
            $origHeight = imagesy($image);
            
            // Create a new true color image with the desired dimensions
            $newImage = imagecreatetruecolor($newWidth, $newHeight);
            
            // Copy and resize part of an image with resampling
            imagecopyresampled($newImage, $image, 0, 0, 0, 0, $newWidth, $newHeight, $origWidth, $origHeight);
            
            // Convert to grayscale
            imagefilter($newImage, IMG_FILTER_GRAYSCALE);
            
            // Convert to black and white
            imagefilter($newImage, IMG_FILTER_CONTRAST, -1000);
            
            // Get the filename without the extension
            $fileParts = pathinfo($inputPath);
            $filenameWithoutExt = $fileParts['filename'];
            
            // Save the image based on the original file type
            $fullOutputPath = $outputPath . '/' . $filenameWithoutExt . (($imageInfo[2] === IMAGETYPE_PNG) ? '.png' : '.jpg');
            if ($imageInfo[2] === IMAGETYPE_PNG) {
                imagepng($newImage, $fullOutputPath);
            } else {
                imagejpeg($newImage, $fullOutputPath);
            }
            
            // Free up memory
            imagedestroy($image);
            imagedestroy($newImage);
            
            return true;
            
        } catch (Exception $e) {
            Log::error('Error in convertImage:', [
                'error' => $e->getMessage(),
                'inputPath' => $inputPath
            ]);
            throw $e;
        }
    }
    
    /**
     * Enhances an image with QR code and basket number, then converts it to ZPL
     */
    public function enhanceAndConvertToZPL($imagePath, $asinfind, $basketnumber)
    {
        try {
            // Check if input file exists
            if (!file_exists($imagePath)) {
                Log::warning('Input file does not exist: ' . $imagePath);
                return "^XA^FO50,50^ADN,18,18^FDInput file not found^FS^XZ";
            }
            
            // Get file extension
            $fileParts = pathinfo($imagePath);
            $extension = strtolower($fileParts['extension']);
            
            // Load the image
            if ($extension == 'png') {
                $image = @imagecreatefrompng($imagePath);
            } elseif ($extension == 'jpg' || $extension == 'jpeg') {
                $image = @imagecreatefromjpeg($imagePath);
            } else {
                Log::warning('Unsupported image type: ' . $extension);
                return "^XA^FO50,50^ADN,18,18^FDUnsupported image type^FS^XZ";
            }
            
            if (!$image) {
                Log::warning('Failed to load image: ' . $imagePath);
                return "^XA^FO50,50^ADN,18,18^FDFailed to load image^FS^XZ";
            }
            
            // Get image dimensions
            $width = imagesx($image);
            $height = imagesy($image);
            
            // Create QR code
            $manual = url('storage/User_manual/ASIN_PDF/' . $asinfind . '.pdf');
            $qrCodePath = $this->imagesPath . '/qrcode/' . $asinfind . '.png';
            
            // Generate QR code
            if (class_exists('QRcode')) {
                \QRcode::png($manual, $qrCodePath, QR_ECLEVEL_L, 3);
            } else {
                Log::warning('QRcode class not available');
                imagedestroy($image);
                return "^XA^FO50,50^ADN,18,18^FDQRcode class not available^FS^XZ";
            }
            
            if (!file_exists($qrCodePath)) {
                Log::warning('Failed to create QR code at: ' . $qrCodePath);
                imagedestroy($image);
                return "^XA^FO50,50^ADN,18,18^FDFailed to create QR code^FS^XZ";
            }
            
            // Load QR code image
            $qrCodeImage = @imagecreatefrompng($qrCodePath);
            if (!$qrCodeImage) {
                Log::warning('Failed to load QR code image: ' . $qrCodePath);
                imagedestroy($image);
                return "^XA^FO50,50^ADN,18,18^FDFailed to load QR code^FS^XZ";
            }
            
            // Get QR code dimensions
            $qrCodeWidth = imagesx($qrCodeImage);
            $qrCodeHeight = imagesy($qrCodeImage);
            
            // Position QR code at top right
            $dstX = $width - $qrCodeWidth - 10;
            $dstY = 10;
            
            // Add QR code to image
            imagecopy($image, $qrCodeImage, $dstX, $dstY, 0, 0, $qrCodeWidth, $qrCodeHeight);
            imagedestroy($qrCodeImage);
            
            // Add "Scan for Manual" text
            $text = "Scan for Manual";
            $fontSize = 5;
            
            $textColor = imagecolorallocate($image, 0, 0, 0);
            
            $textWidth = imagefontwidth($fontSize) * strlen($text);
            $textHeight = imagefontheight($fontSize);
            
            $textImage = imagecreatetruecolor($textWidth, $textHeight);
            $bgColor = imagecolorallocate($textImage, 255, 255, 255);
            imagefill($textImage, 0, 0, $bgColor);
            imagestring($textImage, $fontSize, 0, 0, $text, $textColor);
            
            $rotatedTextImage = imagerotate($textImage, 90, 0);
            imagedestroy($textImage);
            
            $textX = (int)($dstX - imagesx($rotatedTextImage) - 10);
            $textY = (int)($dstY + ($qrCodeHeight - imagesy($rotatedTextImage)) / 2);
            
            imagecopy($image, $rotatedTextImage, $textX, $textY, 0, 0, imagesx($rotatedTextImage), imagesy($rotatedTextImage));
            imagedestroy($rotatedTextImage);
            
            // Add basket number
            $basketText = $basketnumber;
            $basketFontSize = 5;
            
            $basketTextColor = imagecolorallocate($image, 0, 0, 0);
            
            $basketTextWidth = imagefontwidth($basketFontSize) * strlen($basketText);
            $basketTextX = (int)($dstX + ($qrCodeWidth - $basketTextWidth) / 2);
            $basketTextY = $dstY + $qrCodeHeight + imagefontheight($basketFontSize) + 10;
            
            imagestring($image, $basketFontSize, $basketTextX, $basketTextY, $basketText, $basketTextColor);
            
            // Save enhanced image to temp file
            $tempFile = $this->imagesPath . '/temp/' . basename($imagePath);
            
            if ($extension == 'png') {
                imagepng($image, $tempFile);
            } else {
                imagejpeg($image, $tempFile);
            }
            
            imagedestroy($image);
            
            // Now use the convertImageLayout function to convert to ZPL
            $zpl = $this->convertImageLayout($tempFile, $asinfind, $basketnumber);
            
            // Clean up temp file
            if (file_exists($tempFile)) {
                unlink($tempFile);
            }
            
            return $zpl;
            
        } catch (Exception $e) {
            Log::error('Error in enhanceAndConvertToZPL:', [
                'error' => $e->getMessage(),
                'imagePath' => $imagePath
            ]);
            
            return "^XA^FO50,50^ADN,18,18^FDError enhancing image^FS^XZ";
        }
    }
    
    /**
     * Generate serial-specific images from template images
     */
    public function generateSerialImagesFromTemplates($serialNumber, $templatePath1, $templatePath2)
    {
        try {
            if (empty($serialNumber)) {
                Log::error('Serial number is required');
                return false;
            }
            
            // Check if template files exist
            if (!file_exists($templatePath1)) {
                Log::error('Template file does not exist: ' . $templatePath1);
                return false;
            }
            
            if (!file_exists($templatePath2)) {
                Log::error('Template file does not exist: ' . $templatePath2);
                return false;
            }
            
            $details = "serial number : " . $serialNumber;
            $generatedImages = [];
            
            // Convert template images to base64
            $templateImages = [
                base64_encode(file_get_contents($templatePath1)),
                base64_encode(file_get_contents($templatePath2))
            ];
            
            // Process each template image
            foreach ($templateImages as $index => $imageData) {
                // Decode base64 image data
                $decodedImage = base64_decode($imageData);
                if (!$decodedImage) {
                    Log::error('Failed to decode template image at index ' . $index);
                    continue;
                }
                
                $width = 794;
                $height = 1123;
                
                // Create a canvas with a white background
                $canvas = imagecreatetruecolor($width, $height);
                if (!$canvas) {
                    Log::error('Failed to create canvas');
                    continue;
                }
                
                $white = imagecolorallocate($canvas, 255, 255, 255);
                imagefill($canvas, 0, 0, $white);
                
                if ($image = @imagecreatefromstring($decodedImage)) {
                    // Scale and copy uploaded image
                    $scaledImage = imagecreatetruecolor($width, $height);
                    imagefill($scaledImage, 0, 0, $white);
                    imagecopyresampled($scaledImage, $image, 0, 0, 0, 0, $width, $height, imagesx($image), imagesy($image));
                    imagecopy($canvas, $scaledImage, 0, 0, 0, 0, $width, $height);
                    imagedestroy($scaledImage);
                    imagedestroy($image);
                    
                    // Add text with different coordinates based on the page index
                    $blue = imagecolorallocate($canvas, 0, 0, 255);
                    
                    if ($index == 0) { // First page
                        $textX = $width - 120;
                        $textY = $height - 420;
                        if (file_exists($this->fontsPath)) {
                            imagettftext($canvas, 14, 90, $textX, $textY, $blue, $this->fontsPath, $details);
                        } else {
                            // Fallback if font doesn't exist
                            imagestring($canvas, 5, $width - 200, $height - 50, $details, $blue);
                        }
                    } elseif ($index == 1) { // Second page
                        $textX = $width - 500;
                        $textY = $height - 300;
                        if (file_exists($this->fontsPath)) {
                            imagettftext($canvas, 18, 90, $textX, $textY, $blue, $this->fontsPath, $details);
                        } else {
                            // Fallback if font doesn't exist
                            imagestring($canvas, 5, $width - 300, $height - 100, $details, $blue);
                        }
                    }
                } else {
                    Log::error('Failed to create image from template data');
                    imagedestroy($canvas);
                    continue;
                }
                
                // Create output directory if it doesn't exist
                $outputDir = storage_path('app/public/images/warranty/generated_images');
                if (!file_exists($outputDir)) {
                    mkdir($outputDir, 0777, true);
                }
                
                $outputPath = $outputDir . '/' . $serialNumber . '_page_' . ($index + 1) . '.png';
                
                // Save the canvas as a PNG image
                if (imagepng($canvas, $outputPath)) {
                    $generatedImages[] = $outputPath;
                } else {
                    Log::error('Failed to save image to: ' . $outputPath);
                }
                
                imagedestroy($canvas);
            }
            
            return !empty($generatedImages) ? $generatedImages : false;
            
        } catch (Exception $e) {
            Log::error('Error in generateSerialImagesFromTemplates:', [
                'error' => $e->getMessage(),
                'serialNumber' => $serialNumber
            ]);
            
            return false;
        }
    }
    
    /**
     * Ensure serial images exist, creating them from templates if they don't
     */
    public function ensureSerialImagesExist($serialNumber)
    {
        try {
            // Check if images already exist
            $page1 = storage_path('app/public/images/warranty/generated_images/' . $serialNumber . '_page_1.png');
            $page2 = storage_path('app/public/images/warranty/generated_images/' . $serialNumber . '_page_2.png');
            
            if (file_exists($page1) && file_exists($page2)) {
                return true;
            }
            
            // Define the template paths
            $templatePath1 = storage_path('app/public/images/warranty/templates/6_1st.png');
            $templatePath2 = storage_path('app/public/images/warranty/templates/6_2nd.png');
            
            // Generate images from templates
            $result = $this->generateSerialImagesFromTemplates($serialNumber, $templatePath1, $templatePath2);
            return $result !== false;
            
        } catch (Exception $e) {
            Log::error('Error in ensureSerialImagesExist:', [
                'error' => $e->getMessage(),
                'serialNumber' => $serialNumber
            ]);
            
            return false;
        }
    }
    
    /**
     * Safe wrapper for image conversion that checks if the input file exists
     */
    public function safeConvertImage($inputPath, $outputPath, $newWidth, $newHeight)
    {
        try {
            // Check if input file exists
            if (!file_exists($inputPath)) {
                Log::error('Input file does not exist: ' . $inputPath);
                return false;
            }
            
            // Call the convertImage function
            $this->convertImage($inputPath, $outputPath, $newWidth, $newHeight);
            return true;
            
        } catch (Exception $e) {
            Log::error('Error converting image: ' . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Get port IP from database
     */
    public function getPortIP()
    {
        try {
            $portResult = DB::table('tblport')
                ->where('portstatus', 1)
                ->first();
            
            return $portResult ? $portResult->portip : null;
            
        } catch (Exception $e) {
            Log::error('Error getting port IP:', [
                'error' => $e->getMessage()
            ]);
            
            return null;
        }
    }
    
    /**
     * Generate simple QR ZPL for serial when image processing fails
     */
    protected function generateSimpleQRZpl($serial)
    {
        return "^XA" .
               "^FO50,50^BQN,2,10^FDQA," . $serial . "^FS" .
               "^FO50,225^FB400,1,0,C^ADN,12,12^FD" . $serial . "^FS" .
               "^FO50,250^FB400,1,0,C^ADN,10,10^FDScan QR to see Saved Photos^FS" .
               "^FO50,270^FB400,1,0,C^ADN,10,10^FDof this Item on the Cloud^FS" .
               "^XZ";
    }
    
    /**
     * Generate simple manual QR ZPL when image processing fails
     */
    protected function generateSimpleManualQRZpl($asin, $title)
    {
        $manual = url('storage/User_manual/ASIN_PDF/' . $asin . '.pdf');
        
        return "^XA" .
               "^FO50,50^BQN,2,10^FDQA," . $manual . "^FS" .
               "^FO50,225^FB400,1,0,C^ADN,12,12^FD" . $asin . "^FS" .
               "^FO50,250^FB400,1,0,C^ADN,10,10^FDScan Me for User Manual^FS" .
               "^FO50,270^FB400,2,0,C^ADN,8,8^FD" . substr($title, 0, 50) . "^FS" .
               "^XZ";
    }
}