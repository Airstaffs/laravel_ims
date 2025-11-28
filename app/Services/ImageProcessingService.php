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
     * Convert image QR for serial number - Updated to match new layout
     */
      public function convertImageQRserial($serial)
    {
        try {
            if (empty($serial)) {
                Log::error("Serial number is required");
                return "";
            }
            
            // Template path for serial QR label
            $templatePath = public_path('images/warranty/templates/SerialQRTemplate.png');
            
            if (!file_exists($templatePath)) {
                Log::error("Serial QR template file does not exist: " . $templatePath);
                return "";
            }
            
            // Create the QR code URL
            $serialfind = $serial;
            $seriallink = storage_path('app/public/images/serial_qr/' . $serialfind . '.png');
            $manual = url('storage/serial_qr/' . $serialfind . '.png');
            
            // Generate QR code
            $qrCodePath = $this->imagesPath . '/qrcodeSerial/' . $serialfind . '.png';
            if (!file_exists(dirname($qrCodePath))) {
                mkdir(dirname($qrCodePath), 0777, true);
            }
            
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
            
            // Load the template
            $imageData = base64_encode(file_get_contents($templatePath));
            $decodedImage = base64_decode($imageData);
            
            if (!$decodedImage) {
                Log::error("Failed to decode serial QR template image");
                return "";
            }
            
            // Set dimensions for the label - 2" x 1.18" at 203dpi
            $outputImageWidth = 400;
            $outputImageHeight = 240;
            
            // Create a blank image with the specified dimensions
            $image = \imagecreatetruecolor($outputImageWidth, $outputImageHeight);
            
            // Fill the background with white color
            $white = \imagecolorallocate($image, 255, 255, 255);
            $black = \imagecolorallocate($image, 0, 0, 0);
            \imagefill($image, 0, 0, $white);
            
            // Load and scale the template
            if ($templateImage = @\imagecreatefromstring($decodedImage)) {
                $scaledImage = \imagecreatetruecolor($outputImageWidth, $outputImageHeight);
                \imagefill($scaledImage, 0, 0, $white);
                \imagecopyresampled($scaledImage, $templateImage, 0, 0, 0, 0, $outputImageWidth, $outputImageHeight, \imagesx($templateImage), \imagesy($templateImage));
                \imagecopy($image, $scaledImage, 0, 0, 0, 0, $outputImageWidth, $outputImageHeight);
                \imagedestroy($scaledImage);
                \imagedestroy($templateImage);
                
                // Add QR code (centered or positioned as needed)
                if (file_exists($qrCodePath)) {
                    $qrCodeImage = \imagecreatefrompng($qrCodePath);
                    if ($qrCodeImage) {
                        // Position QR code - adjust these values based on your template
                        $qrSize = 120; // QR code size
                        $qrX = 150; // Center horizontally
                        $qrY = 70;  // Center vertically
                        
                        // Resize and place QR code
                        \imagecopyresampled($image, $qrCodeImage, $qrX, $qrY, 0, 0, $qrSize, $qrSize, \imagesx($qrCodeImage), \imagesy($qrCodeImage));
                        \imagedestroy($qrCodeImage);
                    }
                    
                    // QR code file is kept for potential reuse
                }
                
            } else {
                Log::error("Failed to create image from serial QR template data");
                \imagedestroy($image);
                return "";
            }
            
            // Convert image to binary string for ZPL
            $binaryString = "";
            
            // Convert image pixels to binary string
            for ($y = 0; $y < $outputImageHeight; $y++) {
                for ($x = 0; $x < $outputImageWidth; $x++) {
                    $color = \imagecolorat($image, $x, $y);
                    $binaryString .= ($color & 0xFF) > 128 ? '0' : '1';
                }
            }
            
            // Free up memory
            \imagedestroy($image);
            
            // Convert binary string to hexadecimal string
            $hexString = '';
            for ($i = 0; $i < strlen($binaryString); $i += 8) {
                $byteString = substr($binaryString, $i, 8);
                $hexString .= str_pad(dechex(bindec($byteString)), 2, '0', STR_PAD_LEFT);
            }
            
            // Calculate bytes per row
            $bytesPerRow = (int)ceil($outputImageWidth / 8);
            
            // Construct ZPL command
            $zplCommand = "^XA\n";
            $zplCommand .= "^FO20,20^GFA," . strlen($hexString) / 2 . "," . strlen($hexString) / 2 . "," . $bytesPerRow . "," . $hexString . "^FS\n";
            $zplCommand .= "^XZ";
            
            Log::info('Generated serial QR label ZPL successfully for serial: ' . $serial);

            return $zplCommand;
            
        } catch (Exception $e) {
            Log::error('Error in convertImageQRserial:', [
                'error' => $e->getMessage(),
                'serial' => $serial,
                'trace' => $e->getTraceAsString()
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
            
            $qrCodeImage = \imagecreatefrompng($qrCodePath);
            if (!$qrCodeImage) {
                return $this->generateSimpleManualQRZpl($asinfind, $title);
            }
            
            // Set dimensions for the output image
            $outputImageWidth = 400; // Width in pixels
            $outputImageHeight = 200; // Height in pixels
            
            // Create a blank image with the specified dimensions
            $image = \imagecreatetruecolor($outputImageWidth, $outputImageHeight);
            
            // Fill the background with white color
            $white = \imagecolorallocate($image, 255, 255, 255);
            \imagefill($image, 0, 0, $white);
            
            // Add text "Scan Me for User Manual" at the top
            $scanMeText = "Scan Me for User Manual";
            $scanMeFontSize = 20; // Font size for "Scan Me for User Manual"
            $textColor = \imagecolorallocate($image, 0, 0, 0); // Black
            
            if (file_exists($this->fontsPath)) {
                // Calculate text bounding box for "Scan Me for User Manual"
                $bbox = \imagettfbbox($scanMeFontSize, 0, $this->fontsPath, $scanMeText);
                $scanMeTextWidth = $bbox[2] - $bbox[0];
                $scanMeTextX = ($outputImageWidth - $scanMeTextWidth) / 2; // Center text horizontally
                
                // Adjust this value to move the text closer to the top
                $scanMeTextY = 50; // Move text higher
                
                \imagettftext($image, $scanMeFontSize, 0, $scanMeTextX, $scanMeTextY, $textColor, $this->fontsPath, $scanMeText);
            } else {
                // Use built-in font
                $scanMeTextWidth = strlen($scanMeText) * 10;
                $scanMeTextX = ($outputImageWidth - $scanMeTextWidth) / 2;
                $scanMeTextY = 30;
                \imagestring($image, 3, $scanMeTextX, $scanMeTextY, $scanMeText, $textColor);
            }
            
            // Calculate QR code size and position
            $availableWidthForQRCode = $outputImageWidth - 40; // Subtract margins
            $availableHeightForQRCode = $outputImageHeight - 50 - 20 - 20; // Subtract text height and extra padding
            
            $qrScaleFactor = min($availableWidthForQRCode / \imagesx($qrCodeImage), $availableHeightForQRCode / \imagesy($qrCodeImage));
            $scaledQrCodeWidth = \imagesx($qrCodeImage) * $qrScaleFactor;
            $scaledQrCodeHeight = \imagesy($qrCodeImage) * $qrScaleFactor;
            
            // Scale the QR code
            $scaledQrCodeImage = \imagecreatetruecolor($scaledQrCodeWidth, $scaledQrCodeHeight);
            \imagecopyresampled($scaledQrCodeImage, $qrCodeImage, 0, 0, 0, 0, $scaledQrCodeWidth, $scaledQrCodeHeight, \imagesx($qrCodeImage), \imagesy($qrCodeImage));
            \imagedestroy($qrCodeImage);
            
            // Merge QR code with the blank image
            $dstX = 20; // Margin from the left
            $dstY = 50 + 20 + 10; // Position QR code just below the text
            \imagecopy($image, $scaledQrCodeImage, $dstX, $dstY, 0, 0, $scaledQrCodeWidth, $scaledQrCodeHeight);
            \imagedestroy($scaledQrCodeImage);
            
            // Add title text beside the QR code
            $titleFontSize = 20; // Font size for title
            $titleColor = \imagecolorallocate($image, 0, 0, 0); // Black
            
            if (file_exists($this->fontsPath)) {
                // Wrap the title text to fit within the image width
                $maxWidth = $outputImageWidth - $dstX - $scaledQrCodeWidth - 30; // Max width for the title text
                $lines = [];
                $words = explode(' ', $title);
                $currentLine = '';
                
                foreach ($words as $word) {
                    $testLine = $currentLine . ($currentLine ? ' ' : '') . $word;
                    $bbox = \imagettfbbox($titleFontSize, 0, $this->fontsPath, $testLine);
                    
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
                    $bbox = \imagettfbbox($titleFontSize, 0, $this->fontsPath, $line);
                    $titleWidth = $bbox[2] - $bbox[0];
                    $titleX = $dstX + $scaledQrCodeWidth + 25; // Padding from the QR code
                    $lineY = $titleY + ($index * $lineHeight) + $titleFontSize; // Vertical position of each line
                    
                    \imagettftext($image, $titleFontSize, 0, $titleX, $lineY, $titleColor, $this->fontsPath, $line);
                }
            } else {
                // Use built-in font for title
                $titleX = $dstX + $scaledQrCodeWidth + 25;
                $titleY = $dstY + 20;
                \imagestring($image, 2, $titleX, $titleY, substr($title, 0, 30), $titleColor);
            }
            
            // Convert to ZPL
            $binaryString = "";
            
            // Convert image pixels to binary string
            for ($y = 0; $y < $outputImageHeight; $y++) {
                for ($x = 0; $x < $outputImageWidth; $x++) {
                    $color = \imagecolorat($image, $x, $y);
                    $binaryString .= ($color & 0xFF) > 128 ? '0' : '1';
                }
            }
            
            // Free up memory
            \imagedestroy($image);
            
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
            $imageInfo = \getimagesize($inputPath);
            if (!$imageInfo) {
                Log::warning('Invalid image: ' . $inputPath);
                return false;
            }
            
            switch ($imageInfo[2]) {
                case IMAGETYPE_JPEG:
                    $image = \imagecreatefromjpeg($inputPath);
                    break;
                case IMAGETYPE_PNG:
                    $image = \imagecreatefrompng($inputPath);
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
            $origWidth = \imagesx($image);
            $origHeight = \imagesy($image);
            
            // Create a new true color image with the desired dimensions
            $newImage = \imagecreatetruecolor($newWidth, $newHeight);
            
            // Copy and resize part of an image with resampling
            \imagecopyresampled($newImage, $image, 0, 0, 0, 0, $newWidth, $newHeight, $origWidth, $origHeight);
            
            // Convert to grayscale
            \imagefilter($newImage, IMG_FILTER_GRAYSCALE);
            
            // Convert to black and white
            \imagefilter($newImage, IMG_FILTER_CONTRAST, -1000);
            
            // Get the filename without the extension
            $fileParts = pathinfo($inputPath);
            $filenameWithoutExt = $fileParts['filename'];
            
            // Determine the file extension based on the input file type
            $fileExtension = ($imageInfo[2] === IMAGETYPE_PNG) ? '.png' : '.jpg';
            
            // Construct the full output path
            $fullOutputPath = $outputPath . '/' . $filenameWithoutExt . $fileExtension;
            
            // Save the image
            if ($imageInfo[2] === IMAGETYPE_PNG) {
                $result = \imagepng($newImage, $fullOutputPath);
            } else {
                $result = \imagejpeg($newImage, $fullOutputPath);
            }
            
            // Free up memory
            \imagedestroy($image);
            \imagedestroy($newImage);
            
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
                $image = \imagecreatefrompng($monochromeImagePath);
            } elseif ($extension == 'jpg' || $extension == 'jpeg') {
                $image = \imagecreatefromjpeg($monochromeImagePath);
            } else {
                Log::warning('Unsupported image type: ' . $extension);
                return "^XA^FO50,50^ADN,18,18^FDUnsupported image type^FS^XZ";
            }
            
            if (!$image) {
                Log::warning('Unable to load or process the image: ' . $monochromeImagePath);
                return "^XA^FO50,50^ADN,18,18^FDUnable to load image^FS^XZ";
            }
            
            // Get image dimensions
            $width = \imagesx($image);
            $height = \imagesy($image);
            $binaryString = "";
            
            // Convert image pixels to binary string
            for ($y = 0; $y < $height; $y++) {
                for ($x = 0; $x < $width; $x++) {
                    $color = \imagecolorat($image, $x, $y);
                    $binaryString .= ($color & 0xFF) > 128 ? '0' : '1';
                }
            }
            
            // Free up memory
            \imagedestroy($image);
            
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
                $image = \imagecreatefrompng($monochromeImagePath);
            } elseif ($extension == 'jpg' || $extension == 'jpeg') {
                $image = \imagecreatefromjpeg($monochromeImagePath);
            } else {
                Log::warning('Unsupported image type: ' . $extension);
                return "^XA^FO50,50^ADN,18,18^FDUnsupported image type^FS^XZ";
            }
            
            if (!$image) {
                Log::warning('Unable to load or process the image: ' . $monochromeImagePath);
                return "^XA^FO50,50^ADN,18,18^FDUnable to load image^FS^XZ";
            }
            
            $width = \imagesx($image);
            $height = \imagesy($image);
            
            $binaryString = "";
            
            // Convert image pixels to binary string
            for ($y = 0; $y < $height; $y++) {
                for ($x = 0; $x < $width; $x++) {
                    $color = \imagecolorat($image, $x, $y);
                    $binaryString .= ($color & 0xFF) > 128 ? '0' : '1';
                }
            }
            
            // Free up memory
            \imagedestroy($image);
            
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
            $imageInfo = \getimagesize($inputPath);
            if (!$imageInfo) {
                throw new Exception('Invalid image file');
            }
            
            switch ($imageInfo[2]) {
                case IMAGETYPE_JPEG:
                    $image = \imagecreatefromjpeg($inputPath);
                    break;
                case IMAGETYPE_PNG:
                    $image = \imagecreatefrompng($inputPath);
                    break;
                default:
                    throw new Exception('Unsupported image type.');
            }
            
            if (!$image) {
                throw new Exception('Failed to create image resource');
            }
            
            // Get original dimensions
            $origWidth = \imagesx($image);
            $origHeight = \imagesy($image);
            
            // Create a new true color image with the desired dimensions
            $newImage = \imagecreatetruecolor($newWidth, $newHeight);
            
            // Copy and resize part of an image with resampling
            \imagecopyresampled($newImage, $image, 0, 0, 0, 0, $newWidth, $newHeight, $origWidth, $origHeight);
            
            // Convert to grayscale
            \imagefilter($newImage, IMG_FILTER_GRAYSCALE);
            
            // Convert to black and white
            \imagefilter($newImage, IMG_FILTER_CONTRAST, -1000);
            
            // Get the filename without the extension
            $fileParts = pathinfo($inputPath);
            $filenameWithoutExt = $fileParts['filename'];
            
            // Save the image based on the original file type
            $fullOutputPath = $outputPath . '/' . $filenameWithoutExt . (($imageInfo[2] === IMAGETYPE_PNG) ? '.png' : '.jpg');
            if ($imageInfo[2] === IMAGETYPE_PNG) {
                \imagepng($newImage, $fullOutputPath);
            } else {
                \imagejpeg($newImage, $fullOutputPath);
            }
            
            // Free up memory
            \imagedestroy($image);
            \imagedestroy($newImage);
            
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
        
        // Convert image to ZPL using the existing convertImageLayout function
        $zpl = $this->convertImageLayout($imagePath, $asinfind, $basketnumber);
        
        return $zpl;
        
    } catch (Exception $e) {
        Log::error('Error in enhanceAndConvertToZPL:', [
            'error' => $e->getMessage(),
            'imagePath' => $imagePath
        ]);
        
        return "^XA^FO50,50^ADN,18,18^FDError converting image^FS^XZ";
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
        
        $details = "SN : " . $serialNumber; // Changed from "serial number : " to "SN : "
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
            $canvas = \imagecreatetruecolor($width, $height);
            if (!$canvas) {
                Log::error('Failed to create canvas');
                continue;
            }
            
            $white = \imagecolorallocate($canvas, 255, 255, 255);
            \imagefill($canvas, 0, 0, $white);
            
            if ($image = @\imagecreatefromstring($decodedImage)) {
                // Scale and copy uploaded image
                $scaledImage = \imagecreatetruecolor($width, $height);
                \imagefill($scaledImage, 0, 0, $white);
                \imagecopyresampled($scaledImage, $image, 0, 0, 0, 0, $width, $height, \imagesx($image), \imagesy($image));
                \imagecopy($canvas, $scaledImage, 0, 0, 0, 0, $width, $height);
                \imagedestroy($scaledImage);
                \imagedestroy($image);
                
                // Add text with different coordinates based on the page index
                $blue = \imagecolorallocate($canvas, 0, 0, 255);
                
                if ($index == 0) { // First page
                    // MODIFIED: Move text higher up and make it bigger for first image
                    $textX = $width - 500; // Changed from -120 to -500
                    $textY = $height - 320; // Changed from -420 to -320 (moved up)
                    if (file_exists($this->fontsPath)) {
                        \imagettftext($canvas, 36, 90, $textX, $textY, $blue, $this->fontsPath, $details); // Changed from size 14 to 36
                    } else {
                        // Adjusted fallback position
                        \imagestring($canvas, 5, $width - 200, $height - 250, $details, $blue);
                    }
                } elseif ($index == 1) { // Second page
                    $textX = $width - 570; // Changed from -500 to -500 (consistent)
                    $textY = $height - 300;
                    if (file_exists($this->fontsPath)) {
                        \imagettftext($canvas, 28, 90, $textX, $textY, $blue, $this->fontsPath, $details); // Changed from size 18 to 28
                    } else {
                        // Fallback if font doesn't exist
                        \imagestring($canvas, 5, $width - 300, $height - 100, $details, $blue);
                    }
                }
            } else {
                Log::error('Failed to create image from template data');
                \imagedestroy($canvas);
                continue;
            }
            
            // Create output directory if it doesn't exist
            $outputDir = storage_path('app/public/images/warranty/generated_images');
            if (!file_exists($outputDir)) {
                mkdir($outputDir, 0777, true);
            }
            
            $outputPath = $outputDir . '/' . $serialNumber . '_page_' . ($index + 1) . '.png';
            
            // Save the canvas as a PNG image
            if (\imagepng($canvas, $outputPath)) {
                $generatedImages[] = $outputPath;
            } else {
                Log::error('Failed to save image to: ' . $outputPath);
            }
            
            \imagedestroy($canvas);
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

    public function generateQRforInstructionCard($serialNumber)
{
    try {
        if (empty($serialNumber)) {
            Log::error("Serial number is required for QR instruction card");
            return "";
        }
        
        // Define paths using Laravel structure
        $templatePath = public_path('images/warranty/templates/InstructionCardSerialQR.png');
        
        // Check if template exists
        if (!file_exists($templatePath)) {
            Log::error("QR instruction card template not found: " . $templatePath);
            return "^XA^FO50,50^ADN,18,18^FDQR template not found^FS^XZ";
        }
        
        // Create the QR code URL
         $manual = $serialNumber;
        
        // Generate QR code in temp directory
        $qrCodePath = $this->imagesPath . '/temp/qr_' . $serialNumber . '.png';
        if (!file_exists(dirname($qrCodePath))) {
            mkdir(dirname($qrCodePath), 0777, true);
        }
        
        if (class_exists('QRcode')) {
            \QRcode::png($manual, $qrCodePath, QR_ECLEVEL_L, 5);
        } else {
            Log::warning('QRcode class not available for instruction card');
            return "^XA^FO50,50^ADN,18,18^FDQRcode library not available^FS^XZ";
        }
        
        // Load the template
        $imageData = base64_encode(file_get_contents($templatePath));
        $decodedImage = base64_decode($imageData);
        
        if (!$decodedImage) {
            Log::error("Failed to decode QR instruction card template");
            return "^XA^FO50,50^ADN,18,18^FDTemplate decode failed^FS^XZ";
        }
        
        // Set dimensions for the output image (portrait for sticker)
        $outputImageWidth = 800;
        $outputImageHeight = 1200;
        
        // Create a blank image with the specified dimensions
        $image = \imagecreatetruecolor($outputImageWidth, $outputImageHeight);
        
        // Fill the background with white color
        $white = \imagecolorallocate($image, 255, 255, 255);
        $black = \imagecolorallocate($image, 0, 0, 0);
        \imagefill($image, 0, 0, $white);
        
        // Load and scale the template
        if ($templateImage = @\imagecreatefromstring($decodedImage)) {
            // Rotate the template image 90 degrees counter-clockwise to landscape orientation
            $rotatedTemplate = \imagerotate($templateImage, 90, $white);
            \imagedestroy($templateImage);
            
            $scaledImage = \imagecreatetruecolor($outputImageWidth, $outputImageHeight);
            \imagefill($scaledImage, 0, 0, $white);
            \imagecopyresampled($scaledImage, $rotatedTemplate, 0, 0, 0, 0, $outputImageWidth, $outputImageHeight, \imagesx($rotatedTemplate), \imagesy($rotatedTemplate));
            \imagecopy($image, $scaledImage, 0, 0, 0, 0, $outputImageWidth, $outputImageHeight);
            \imagedestroy($scaledImage);
            \imagedestroy($rotatedTemplate);
            
            // Add serial number - adjusted for rotated template
            $serialTextY = 950; // Adjusted for rotated layout sideways
            $serialTextX = 280; // Adjusted for rotated layout downward/upward
            
            if (file_exists($this->fontsPath)) {
                // Create bold effect by drawing the text multiple times with slight offsets
                \imagettftext($image, 52, 90, $serialTextX, $serialTextY, $black, $this->fontsPath, $serialNumber);
                \imagettftext($image, 52, 90, $serialTextX + 1, $serialTextY, $black, $this->fontsPath, $serialNumber);
                \imagettftext($image, 52, 90, $serialTextX, $serialTextY + 1, $black, $this->fontsPath, $serialNumber);
                \imagettftext($image, 52, 90, $serialTextX + 1, $serialTextY + 1, $black, $this->fontsPath, $serialNumber);
            } else {
                // Fallback if TTF font not available
                \imagestring($image, 5, $serialTextX, $serialTextY, $serialNumber, $black);
                Log::warning('TTF font not available, using fallback for QR instruction card');
            }
            
            // Load and add QR code - adjusted for rotated template
            if (file_exists($qrCodePath)) {
                $qrCodeImage = \imagecreatefrompng($qrCodePath);
                if ($qrCodeImage) {
                    // Position QR code - adjusted for rotated layout
                    $qrX = 450; // Adjusted for rotated layout
                    $qrY = 500; // Adjusted for rotated layout  
                    $qrSize = 200; // QR code size
                    
                    // Resize and place QR code
                    \imagecopyresampled($image, $qrCodeImage, $qrX, $qrY, 0, 0, $qrSize, $qrSize, \imagesx($qrCodeImage), \imagesy($qrCodeImage));
                    \imagedestroy($qrCodeImage);
                }
                
                // Clean up temporary QR code file
                unlink($qrCodePath);
            }
            
        } else {
            Log::error("Failed to create image from QR instruction card template data");
            \imagedestroy($image);
            return "^XA^FO50,50^ADN,18,18^FDTemplate processing failed^FS^XZ";
        }
        
        // Convert image to binary string for ZPL
        $binaryString = "";
        
        // Convert image pixels to binary string
        for ($y = 0; $y < $outputImageHeight; $y++) {
            for ($x = 0; $x < $outputImageWidth; $x++) {
                $color = \imagecolorat($image, $x, $y);
                $binaryString .= ($color & 0xFF) > 128 ? '0' : '1';
            }
        }
        
        // Free up memory
        \imagedestroy($image);
        
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
        
        Log::info('Generated QR instruction card ZPL successfully for serial: ' . $serialNumber);
        
        return $zplCommand;
        
    } catch (Exception $e) {
        Log::error('Error generating QR instruction card:', [
            'error' => $e->getMessage(),
            'serial_number' => $serialNumber,
            'trace' => $e->getTraceAsString()
        ]);
        
        return "^XA^FO50,50^ADN,18,18^FDError generating QR card^FS^XZ";
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
     * Generate QR code for small label card (2" x 1" label)
     * This creates a compact label with serial number and QR code
     */
public function generateQRforSmallLabelCard($serialNumber, $returnCount = 0)
{
    try {
        if (empty($serialNumber)) {
            Log::error("Serial number is required for small label card");
            return "";
        }
        
        // Template path for small label
        $templatePath = public_path('images/warranty/templates/SmallLabelCardSerialQR.png');
        
        if (!file_exists($templatePath)) {
            Log::error("Small label template file does not exist: " . $templatePath);
            return "";
        }
        
        // Create the QR code URL
        $manual = $serialNumber;

        // Generate QR code in temp directory
        $qrCodePath = $this->imagesPath . '/temp/qr_small_' . $serialNumber . '.png';
        if (!file_exists(dirname($qrCodePath))) {
            mkdir(dirname($qrCodePath), 0777, true);
        }
        
        if (class_exists('QRcode')) {
            \QRcode::png($manual, $qrCodePath, QR_ECLEVEL_L, 5);
        } else {
            Log::error('QRcode class not available for small label card');
            return "";
        }
        
        // Load the template
        $imageData = base64_encode(file_get_contents($templatePath));
        $decodedImage = base64_decode($imageData);
        
        if (!$decodedImage) {
            Log::error("Failed to decode small label template image");
            return "";
        }
        
        // Set dimensions for the smaller label - 2" x 1" at 203dpi
        $outputImageWidth = 400;
        $outputImageHeight = 220;
        
        // Create a blank image with the specified dimensions
        $image = \imagecreatetruecolor($outputImageWidth, $outputImageHeight);
        
        // Fill the background with white color
        $white = \imagecolorallocate($image, 255, 255, 255);
        $black = \imagecolorallocate($image, 0, 0, 0);
        \imagefill($image, 0, 0, $white);
        
        // Load and scale the template
        if ($templateImage = @\imagecreatefromstring($decodedImage)) {
            $scaledImage = \imagecreatetruecolor($outputImageWidth, $outputImageHeight);
            \imagefill($scaledImage, 0, 0, $white);
            \imagecopyresampled($scaledImage, $templateImage, 0, 0, 0, 0, $outputImageWidth, $outputImageHeight, \imagesx($templateImage), \imagesy($templateImage));
            \imagecopy($image, $scaledImage, 0, 0, 0, 0, $outputImageWidth, $outputImageHeight);
            \imagedestroy($scaledImage);
            \imagedestroy($templateImage);
            
            // Add serial number with stretched/taller effect
            $serialTextX = 5;
            $serialTextY = 110;
            $fontSize = 20;
            
            if (file_exists($this->fontsPath)) {
                // Calculate text dimensions
                $textBox = \imagettfbbox($fontSize, 0, $this->fontsPath, $serialNumber);
                $textWidth = abs($textBox[4] - $textBox[0]);
                $textHeight = abs($textBox[5] - $textBox[1]);
                
                // Height multiplier - adjust this to make text taller (1.5 = 50% taller)
                $heightMultiplier = 1.5;
                
                // Create temporary image for the text - cast to int to fix deprecation
                $tempImgWidth = (int)($textWidth + 20);
                $tempImgHeight = (int)(($textHeight + 20) * $heightMultiplier);
                $tempImg = \imagecreatetruecolor($tempImgWidth, $tempImgHeight);
                $tempWhite = \imagecolorallocate($tempImg, 255, 255, 255);
                $tempBlack = \imagecolorallocate($tempImg, 0, 0, 0);
                \imagefill($tempImg, 0, 0, $tempWhite);
                
                // Draw text on temporary image with bold effect
                \imagettftext($tempImg, $fontSize, 0, 10, $fontSize + 10, $tempBlack, $this->fontsPath, $serialNumber);
                \imagettftext($tempImg, $fontSize, 0, 11, $fontSize + 10, $tempBlack, $this->fontsPath, $serialNumber);
                \imagettftext($tempImg, $fontSize, 0, 10, $fontSize + 11, $tempBlack, $this->fontsPath, $serialNumber);
                \imagettftext($tempImg, $fontSize, 0, 11, $fontSize + 11, $tempBlack, $this->fontsPath, $serialNumber);
                
                // Calculate stretched dimensions - cast to int
                $stretchedHeight = (int)(($textHeight + 20) * $heightMultiplier);
                
                // Copy with vertical stretch onto main image - cast all float calculations to int
                \imagecopyresampled(
                    $image, $tempImg,
                    $serialTextX, (int)($serialTextY - ($textHeight * $heightMultiplier)), // destination position
                    0, 0, // source position
                    $tempImgWidth, $stretchedHeight, // destination dimensions (width stays same, height stretched)
                    $tempImgWidth, (int)($textHeight + 20) // source dimensions
                );
                
                // Clean up temporary image
                \imagedestroy($tempImg);
                
                // Add return count BELOW the serial number - ALWAYS show it
                $returnText = "R: " . $returnCount;
                $returnFontSize = 16; // Slightly smaller font for return count
                
                // Calculate return text dimensions
                $returnTextBox = \imagettfbbox($returnFontSize, 0, $this->fontsPath, $returnText);
                $returnTextWidth = abs($returnTextBox[4] - $returnTextBox[0]);
                $returnTextHeight = abs($returnTextBox[5] - $returnTextBox[1]);
                
                // Position return count BELOW the serial number
                $returnTextX = $serialTextX + 140;
                $returnTextY = $serialTextY + 45; // 15px below serial number
                
                // Draw return count with bold effect (directly on main image)
                \imagettftext($image, $returnFontSize, 0, $returnTextX, $returnTextY, $black, $this->fontsPath, $returnText);
                \imagettftext($image, $returnFontSize, 0, $returnTextX + 1, $returnTextY, $black, $this->fontsPath, $returnText);
                \imagettftext($image, $returnFontSize, 0, $returnTextX, $returnTextY + 1, $black, $this->fontsPath, $returnText);
                \imagettftext($image, $returnFontSize, 0, $returnTextX + 1, $returnTextY + 1, $black, $this->fontsPath, $returnText);
                
            } else {
                // Fallback if TTF font not available
                \imagestring($image, 3, $serialTextX, $serialTextY - 10, $serialNumber, $black);
                
                // Add return count in fallback mode BELOW serial - ALWAYS show it
                $returnText = "R:" . $returnCount;
                \imagestring($image, 2, $serialTextX, $serialTextY + 10, $returnText, $black);
                
                Log::error('TTF font not available for small label card');
            }
            
            // Add QR code next to the serial number (on the right side)
            if (file_exists($qrCodePath)) {
                $qrCodeImage = \imagecreatefrompng($qrCodePath);
                if ($qrCodeImage) {
                    // Position QR code on the right side of the label
                    // Increased size for better scannability
                    $qrSize = 90; // Slightly larger for better scanning
                    $qrX = 310; // Position on right side
                    $qrY = 70; // Center vertically in lower portion
                    
                    // Resize and place QR code
                    \imagecopyresampled($image, $qrCodeImage, $qrX, $qrY, 0, 0, $qrSize, $qrSize, \imagesx($qrCodeImage), \imagesy($qrCodeImage));
                    \imagedestroy($qrCodeImage);
                }
                
                // Clean up temporary QR code file
                if (file_exists($qrCodePath)) {
                    unlink($qrCodePath);
                }
            }
            
        } else {
            Log::error("Failed to create image from small label template data");
            \imagedestroy($image);
            return "";
        }
        
        // Convert image to binary string for ZPL
        $binaryString = "";
        
        // Convert image pixels to binary string
        for ($y = 0; $y < $outputImageHeight; $y++) {
            for ($x = 0; $x < $outputImageWidth; $x++) {
                $color = \imagecolorat($image, $x, $y);
                $binaryString .= ($color & 0xFF) > 128 ? '0' : '1';
            }
        }
        
        // Free up memory
        \imagedestroy($image);
        
        // Convert binary string to hexadecimal string
        $hexString = '';
        for ($i = 0; $i < strlen($binaryString); $i += 8) {
            $byteString = substr($binaryString, $i, 8);
            $hexString .= str_pad(dechex(bindec($byteString)), 2, '0', STR_PAD_LEFT);
        }
        
        // Calculate bytes per row
        $bytesPerRow = ceil($outputImageWidth / 8);
        
        // Construct ZPL command for smaller label
        $zplCommand = "^XA\n";
        $zplCommand .= "^FO20,20^GFA," . strlen($hexString) / 2 . "," . strlen($hexString) / 2 . "," . $bytesPerRow . "," . $hexString . "^FS\n";
        $zplCommand .= "^XZ";
        
        Log::info('Generated small label card ZPL successfully', [
            'serial' => $serialNumber,
            'return_count' => $returnCount
        ]);
        
        return $zplCommand;
        
    } catch (Exception $e) {
        Log::error('Error generating small label card:', [
            'error' => $e->getMessage(),
            'serial_number' => $serialNumber,
            'return_count' => $returnCount,
            'trace' => $e->getTraceAsString()
        ]);
        
        return "";
    }
}


  /**
 * Generate QR code label for restocking with serial number parameter
 * Uses "RESTOCKING FEE IF RETURNED?" template
 */
public function generateRestockingLabel($serialNumber)
{
    try {
        if (empty($serialNumber)) {
            Log::error("Serial number is required for restocking label");
            return "";
        }
        
        // Template path for restocking label
        $templatePath = public_path('images/warranty/templates/RestockingLabelTemplate.png');
        
        if (!file_exists($templatePath)) {
            Log::error("Restocking label template file does not exist: " . $templatePath);
            return "";
        }
        
        // Load the template
        $imageData = base64_encode(file_get_contents($templatePath));
        $decodedImage = base64_decode($imageData);
        
        if (!$decodedImage) {
            Log::error("Failed to decode restocking label template image");
            return "";
        }
        
        // Set dimensions for instruction card printer - 4" x 6" at 203dpi (portrait)
        $outputImageWidth = 800;
        $outputImageHeight = 1200;
        
        // Create a blank image with the specified dimensions
        $image = \imagecreatetruecolor($outputImageWidth, $outputImageHeight);
        
        // Fill the background with white color
        $white = \imagecolorallocate($image, 255, 255, 255);
        $black = \imagecolorallocate($image, 0, 0, 0);
        \imagefill($image, 0, 0, $white);
        
        // Load and scale the template
        if ($templateImage = @\imagecreatefromstring($decodedImage)) {
            // Rotate the template image 90 degrees counter-clockwise to landscape orientation
            $rotatedTemplate = \imagerotate($templateImage, 90, $white);
            \imagedestroy($templateImage);
            
            $scaledImage = \imagecreatetruecolor($outputImageWidth, $outputImageHeight);
            \imagefill($scaledImage, 0, 0, $white);
            \imagecopyresampled($scaledImage, $rotatedTemplate, 0, 0, 0, 0, $outputImageWidth, $outputImageHeight, \imagesx($rotatedTemplate), \imagesy($rotatedTemplate));
            \imagecopy($image, $scaledImage, 0, 0, 0, 0, $outputImageWidth, $outputImageHeight);
            \imagedestroy($scaledImage);
            \imagedestroy($rotatedTemplate);
            
            // Add serial number - adjusted for rotated template
            $serialTextY = 950; // Moved down for rotated layout sideways
            $serialTextX = 730; // Adjusted for rotated layout downward/upward
            
            if (file_exists($this->fontsPath)) {
                // Create bold effect by drawing the text multiple times with slight offsets
                \imagettftext($image, 55, 90, $serialTextX, $serialTextY, $black, $this->fontsPath, $serialNumber);
                \imagettftext($image, 55, 90, $serialTextX + 1, $serialTextY, $black, $this->fontsPath, $serialNumber);
                \imagettftext($image, 55, 90, $serialTextX, $serialTextY + 1, $black, $this->fontsPath, $serialNumber);
                \imagettftext($image, 55, 90, $serialTextX + 1, $serialTextY + 1, $black, $this->fontsPath, $serialNumber);
            } else {
                // Fallback if TTF font not available
                \imagestring($image, 5, $serialTextX, $serialTextY, $serialNumber, $black);
                Log::warning('TTF font not available for restocking label');
            }
            
        } else {
            Log::error("Failed to create image from restocking label template data");
            \imagedestroy($image);
            return "";
        }
        
        // Convert image to binary string for ZPL
        $binaryString = "";
        
        for ($y = 0; $y < $outputImageHeight; $y++) {
            for ($x = 0; $x < $outputImageWidth; $x++) {
                $color = \imagecolorat($image, $x, $y);
                $binaryString .= ($color & 0xFF) > 128 ? '0' : '1';
            }
        }
        
        // Free up memory
        \imagedestroy($image);
        
        // Convert binary string to hexadecimal string
        $hexString = '';
        for ($i = 0; $i < strlen($binaryString); $i += 8) {
            $byteString = substr($binaryString, $i, 8);
            $hexString .= str_pad(dechex(bindec($byteString)), 2, '0', STR_PAD_LEFT);
        }
        
        // Calculate bytes per row
        $bytesPerRow = ceil($outputImageWidth / 8);
        
        // Construct ZPL command for instruction card printer
        $zplCommand = "^XA\n";
        $zplCommand .= "^FO20,20^GFA," . strlen($hexString) / 2 . "," . strlen($hexString) / 2 . "," . $bytesPerRow . "," . $hexString . "^FS\n";
        $zplCommand .= "^XZ";
        
        Log::info('Generated restocking label ZPL successfully for serial: ' . $serialNumber);
        
        return $zplCommand;
        
    } catch (Exception $e) {
        Log::error('Error in generateRestockingLabel:', [
            'error' => $e->getMessage(),
            'serial_number' => $serialNumber,
            'trace' => $e->getTraceAsString()
        ]);
        
        return "";
    }
}

/**
 * Generate QR code label for requesting replacement
 * Uses "ISSUES? Scan QR code to request for a replacement" template
 * NO parameters - just the template with QR code
 */
public function generateRequestReplacementLabel()
{
    try {
        // Template path for replacement request label
        $templatePath = public_path('images/warranty/templates/ReplacementRequestTemplate.png');
        
        if (!file_exists($templatePath)) {
            Log::error("Replacement request label template file does not exist: " . $templatePath);
            return "";
        }
        
        // Load the template
        $imageData = base64_encode(file_get_contents($templatePath));
        $decodedImage = base64_decode($imageData);
        
        if (!$decodedImage) {
            Log::error("Failed to decode replacement request label template image");
            return "";
        }
        
        // Set dimensions for instruction card printer - 4" x 6" at 203dpi (portrait)
        $outputImageWidth = 800;
        $outputImageHeight = 1200;
        
        // Create a blank image with the specified dimensions
        $image = \imagecreatetruecolor($outputImageWidth, $outputImageHeight);
        
        // Fill the background with white color
        $white = \imagecolorallocate($image, 255, 255, 255);
        \imagefill($image, 0, 0, $white);
        
        // Load and scale the template (template already contains QR code and all text)
        if ($templateImage = @\imagecreatefromstring($decodedImage)) {
            // Rotate the template image 90 degrees counter-clockwise to landscape orientation
            $rotatedTemplate = \imagerotate($templateImage, 90, $white);
            \imagedestroy($templateImage);
            
            $scaledImage = \imagecreatetruecolor($outputImageWidth, $outputImageHeight);
            \imagefill($scaledImage, 0, 0, $white);
            \imagecopyresampled($scaledImage, $rotatedTemplate, 0, 0, 0, 0, $outputImageWidth, $outputImageHeight, \imagesx($rotatedTemplate), \imagesy($rotatedTemplate));
            \imagecopy($image, $scaledImage, 0, 0, 0, 0, $outputImageWidth, $outputImageHeight);
            \imagedestroy($scaledImage);
            \imagedestroy($rotatedTemplate);
            
        } else {
            Log::error("Failed to create image from replacement request label template data");
            \imagedestroy($image);
            return "";
        }
        
        // Convert image to binary string for ZPL
        $binaryString = "";
        
        for ($y = 0; $y < $outputImageHeight; $y++) {
            for ($x = 0; $x < $outputImageWidth; $x++) {
                $color = \imagecolorat($image, $x, $y);
                $binaryString .= ($color & 0xFF) > 128 ? '0' : '1';
            }
        }
        
        // Free up memory
        \imagedestroy($image);
        
        // Convert binary string to hexadecimal string
        $hexString = '';
        for ($i = 0; $i < strlen($binaryString); $i += 8) {
            $byteString = substr($binaryString, $i, 8);
            $hexString .= str_pad(dechex(bindec($byteString)), 2, '0', STR_PAD_LEFT);
        }
        
        // Calculate bytes per row
        $bytesPerRow = ceil($outputImageWidth / 8);
        
        // Construct ZPL command for instruction card printer
        $zplCommand = "^XA\n";
        $zplCommand .= "^FO20,20^GFA," . strlen($hexString) / 2 . "," . strlen($hexString) / 2 . "," . $bytesPerRow . "," . $hexString . "^FS\n";
        $zplCommand .= "^XZ";
        
        Log::info('Generated replacement request label ZPL successfully');
        
        return $zplCommand;
        
    } catch (Exception $e) {
        Log::error('Error in generateRequestReplacementLabel:', [
            'error' => $e->getMessage(),
            'trace' => $e->getTraceAsString()
        ]);
        
        return "";
    }
}

/**
 * Generate recycle request label
 * Uses "RecycleRequstTemplate.png" template
 * NO parameters - just the template
 */
public function generateRecycleRequestLabel()
{
    try {
        // Template path for recycle request label
        $templatePath = public_path('images/warranty/templates/RecycleRequstTemplate.png');
        
        if (!file_exists($templatePath)) {
            Log::error("Recycle request label template file does not exist: " . $templatePath);
            return "";
        }
        
        // Load the template
        $imageData = base64_encode(file_get_contents($templatePath));
        $decodedImage = base64_decode($imageData);
        
        if (!$decodedImage) {
            Log::error("Failed to decode recycle request label template image");
            return "";
        }
        
        // Set dimensions for instruction card printer - 4" x 6" at 203dpi (portrait)
        $outputImageWidth = 800;
        $outputImageHeight = 1200;
        
        // Create a blank image with the specified dimensions
        $image = \imagecreatetruecolor($outputImageWidth, $outputImageHeight);
        
        // Fill the background with white color
        $white = \imagecolorallocate($image, 255, 255, 255);
        \imagefill($image, 0, 0, $white);
        
        // Load and scale the template (template already contains all content)
        if ($templateImage = @\imagecreatefromstring($decodedImage)) {
            // Rotate the template image 90 degrees counter-clockwise to landscape orientation
            $rotatedTemplate = \imagerotate($templateImage, 90, $white);
            \imagedestroy($templateImage);
            
            $scaledImage = \imagecreatetruecolor($outputImageWidth, $outputImageHeight);
            \imagefill($scaledImage, 0, 0, $white);
            \imagecopyresampled($scaledImage, $rotatedTemplate, 0, 0, 0, 0, $outputImageWidth, $outputImageHeight, \imagesx($rotatedTemplate), \imagesy($rotatedTemplate));
            \imagecopy($image, $scaledImage, 0, 0, 0, 0, $outputImageWidth, $outputImageHeight);
            \imagedestroy($scaledImage);
            \imagedestroy($rotatedTemplate);
            
        } else {
            Log::error("Failed to create image from recycle request label template data");
            \imagedestroy($image);
            return "";
        }
        
        // Convert image to binary string for ZPL
        $binaryString = "";
        
        for ($y = 0; $y < $outputImageHeight; $y++) {
            for ($x = 0; $x < $outputImageWidth; $x++) {
                $color = \imagecolorat($image, $x, $y);
                $binaryString .= ($color & 0xFF) > 128 ? '0' : '1';
            }
        }
        
        // Free up memory
        \imagedestroy($image);
        
        // Convert binary string to hexadecimal string
        $hexString = '';
        for ($i = 0; $i < strlen($binaryString); $i += 8) {
            $byteString = substr($binaryString, $i, 8);
            $hexString .= str_pad(dechex(bindec($byteString)), 2, '0', STR_PAD_LEFT);
        }
        
        // Calculate bytes per row
        $bytesPerRow = ceil($outputImageWidth / 8);
        
        // Construct ZPL command for instruction card printer
        $zplCommand = "^XA\n";
        $zplCommand .= "^FO20,20^GFA," . strlen($hexString) / 2 . "," . strlen($hexString) / 2 . "," . $bytesPerRow . "," . $hexString . "^FS\n";
        $zplCommand .= "^XZ";
        
        Log::info('Generated recycle request label ZPL successfully');
        
        return $zplCommand;
        
    } catch (Exception $e) {
        Log::error('Error in generateRecycleRequestLabel:', [
            'error' => $e->getMessage(),
            'trace' => $e->getTraceAsString()
        ]);
        
        return "";
    }
}



 /**
     * Generate condition auxiliary small label (2" x 1" label)
     * Creates a small label showing item condition (Renewed or Used)
     * 
     * @param string $condition The condition to display ('Renewed' or 'Used')
     * @return string ZPL command string
     */
    public function generateConditionAuxSmallLabel($condition)
    {
        try {
            if (empty($condition)) {
                Log::error("Condition is required for condition label");
                return "";
            }
            
            // Determine which template to use based on condition
            if ($condition === 'Renewed') {
                $templatePath = public_path('images/warranty/templates/AUXrenewed.png');
            } else {
                $templatePath = public_path('images/warranty/templates/AUXUsed.png');
            }
            
            if (!file_exists($templatePath)) {
                Log::error("Condition label template file does not exist: " . $templatePath);
                return "";
            }
            
            // Load the template
            $imageData = base64_encode(file_get_contents($templatePath));
            $decodedImage = base64_decode($imageData);
            
            if (!$decodedImage) {
                Log::error("Failed to decode condition label template image");
                return "";
            }
            
            // Set dimensions for the smaller label - 2" x 1" at 203dpi
            $outputImageWidth = 400;
            $outputImageHeight = 220;
            
            // Create a blank image with the specified dimensions
            $image = \imagecreatetruecolor($outputImageWidth, $outputImageHeight);
            
            // Fill the background with white color
            $white = \imagecolorallocate($image, 255, 255, 255);
            \imagefill($image, 0, 0, $white);
            
            // Load and scale the template
            if ($templateImage = @\imagecreatefromstring($decodedImage)) {
                $scaledImage = \imagecreatetruecolor($outputImageWidth, $outputImageHeight);
                \imagefill($scaledImage, 0, 0, $white);
                \imagecopyresampled($scaledImage, $templateImage, 0, 0, 0, 0, $outputImageWidth, $outputImageHeight, \imagesx($templateImage), \imagesy($templateImage));
                \imagecopy($image, $scaledImage, 0, 0, 0, 0, $outputImageWidth, $outputImageHeight);
                \imagedestroy($scaledImage);
                \imagedestroy($templateImage);
            } else {
                Log::error("Failed to create image from condition label template data");
                \imagedestroy($image);
                return "";
            }
            
            // Convert image to binary string for ZPL
            $binaryString = "";
            
            // Convert image pixels to binary string
            for ($y = 0; $y < $outputImageHeight; $y++) {
                for ($x = 0; $x < $outputImageWidth; $x++) {
                    $color = \imagecolorat($image, $x, $y);
                    $binaryString .= ($color & 0xFF) > 128 ? '0' : '1';
                }
            }
            
            // Free up memory
            \imagedestroy($image);
            
            // Convert binary string to hexadecimal string
            $hexString = '';
            for ($i = 0; $i < strlen($binaryString); $i += 8) {
                $byteString = substr($binaryString, $i, 8);
                $hexString .= str_pad(dechex(bindec($byteString)), 2, '0', STR_PAD_LEFT);
            }
            
            // Calculate bytes per row
            $bytesPerRow = ceil($outputImageWidth / 8);
            
            // Construct ZPL command for smaller label
            $zplCommand = "^XA\n";
            $zplCommand .= "^FO20,20^GFA," . strlen($hexString) / 2 . "," . strlen($hexString) / 2 . "," . $bytesPerRow . "," . $hexString . "^FS\n";
            $zplCommand .= "^XZ";
            
            Log::info('Generated condition label ZPL successfully', [
                'condition' => $condition
            ]);
            
            return $zplCommand;
            
        } catch (Exception $e) {
            Log::error('Error generating condition label:', [
                'error' => $e->getMessage(),
                'condition' => $condition,
                'trace' => $e->getTraceAsString()
            ]);
            
            return "";
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