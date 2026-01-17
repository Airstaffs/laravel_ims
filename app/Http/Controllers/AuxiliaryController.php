<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Auth;
use App\Services\ImageProcessingService;
use Exception;

class AuxiliaryController extends Controller
{
    protected $auxiliaryTable = 'tblauxiliary';
    protected $imageProcessingService;
    protected $printerIp;
    protected $printServerUrl;

    public function __construct()
    {
        $this->imageProcessingService = new ImageProcessingService();
        $this->printerIp = config('app.printer_ip', '192.168.1.109');
        $this->printServerUrl = config('app.print_server_url', 'http://99.0.87.190:1450/ims/Admin/modules/PRD-RPN-PCN/print.php');
    }

    /**
     * Get all auxiliary items with optional search
     */
    public function index(Request $request)
    {
        try {
            $searchTerm = $request->input('search');
            
            $query = DB::table($this->auxiliaryTable)
                ->select('*')
                ->orderBy('id', 'desc');

            if ($searchTerm) {
                $query->where(function($q) use ($searchTerm) {
                    $q->where('auxname', 'LIKE', "%{$searchTerm}%")
                      ->orWhere('auxcode', $searchTerm);
                });
            }

            $auxiliaries = $query->get();

            return response()->json($auxiliaries);

        } catch (Exception $e) {
            Log::error('Error fetching auxiliaries:', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch auxiliaries'
            ], 500);
        }
    }

    /**
     * Get available printers (only small label type, no married printers)
     */
    public function getPrinters()
    {
        try {
            $printers = DB::table('tblprinters')
                ->select([
                    'printerid',
                    'printername',
                    'printerip',
                    'port',
                    'printer_type',
                    'status'
                ])
                ->where('status', 'active')
                ->where('printer_type', 'small_label')
         //       ->whereNull('married_to_printer_id') // Exclude married printers
                ->orderBy('printername')
                ->get();

            // Simple printer data for auxiliary module
            $printersList = $printers->map(function ($printer) {
                return [
                    'printerid' => $printer->printerid,
                    'printername' => $printer->printername,
                    'printername_short' => $printer->printername,
                    'printerip' => $printer->printerip,
                    'port' => $printer->port,
                    'printer_type' => $printer->printer_type,
                    'status' => $printer->status
                ];
            });
            
            Log::info('Fetched printers for auxiliary:', [
                'count' => $printersList->count(),
                'printers' => $printersList->pluck('printername')->toArray()
            ]);

            return response()->json([
                'success' => true,
                'printers' => $printersList
            ]);

        } catch (Exception $e) {
            Log::error('Error fetching printers for auxiliary:', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch printers: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Upload a new auxiliary image
     */
    public function upload(Request $request)
    {
        try {
            $request->validate([
                'auxname' => 'required|string|max:255',
                'auxcode' => 'required|string|max:50',
                'image' => 'required|image|mimes:jpg,jpeg,png,gif|max:10240'
            ]);

            $auxname = $request->auxname;
            $auxcode = $request->auxcode;
            $image = $request->file('image');
            $fileName = $image->getClientOriginalName();

            // Check for duplicates
            $exists = DB::table($this->auxiliaryTable)
                ->where('auxname', $auxname)
                ->orWhere('auxcode', $auxcode)
                ->orWhere('auximgname', $fileName)
                ->exists();

            if ($exists) {
                return response()->json([
                    'success' => false,
                    'message' => 'Duplicate entry: Aux Name, Code, or Image already exists.'
                ], 400);
            }

            // Store the image in public/images/auxiliary/
            $targetDir = public_path('images/auxiliary');
            if (!file_exists($targetDir)) {
                mkdir($targetDir, 0755, true);
            }

            $image->move($targetDir, $fileName);

            // Save to database
            $user = Auth::user();
            $username = $user ? ($user->username ?? $user->name ?? 'Unknown') : 'System';

            DB::table($this->auxiliaryTable)->insert([
                'auxname' => $auxname,
                'auxcode' => $auxcode,
                'auximgname' => $fileName,
                'saveby' => $username,
                'datesaved' => now()->format('Y-m-d'),
                'created_at' => now(),
                'updated_at' => now()
            ]);

            Log::info('Auxiliary uploaded successfully:', [
                'auxname' => $auxname,
                'auxcode' => $auxcode,
                'filename' => $fileName
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Image uploaded and saved to database successfully!'
            ]);

        } catch (Exception $e) {
            Log::error('Error uploading auxiliary:', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to upload auxiliary: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Update an auxiliary item
     */
   public function update(Request $request, $id)
{
    try {
        $request->validate([
            'auxname' => 'required|string|max:255',
            'auxcode' => 'required|string|max:50',
            'image' => 'nullable|image|mimes:jpg,jpeg,png,gif|max:10240'
        ]);

        $auxname = trim($request->auxname);
        $auxcode = trim($request->auxcode);

        // Check if record exists
        $auxiliary = DB::table($this->auxiliaryTable)->where('id', $id)->first();
        
        if (!$auxiliary) {
            return response()->json([
                'success' => false,
                'message' => 'Auxiliary not found'
            ], 404);
        }

        // Check for duplicates (excluding current record)
        $duplicate = DB::table($this->auxiliaryTable)
            ->where('id', '!=', $id)
            ->where(function($q) use ($auxname, $auxcode) {
                $q->where('auxname', $auxname)
                  ->orWhere('auxcode', $auxcode);
            })
            ->exists();

        if ($duplicate) {
            return response()->json([
                'success' => false,
                'message' => 'Duplicate entry: Aux Name or Code already exists.'
            ], 400);
        }

        // Prepare update data
        $updateData = [
            'auxname' => $auxname,
            'auxcode' => $auxcode,
            'updated_at' => now()
        ];

        // Handle image update if provided
        if ($request->hasFile('image')) {
            $image = $request->file('image');
            $fileName = $image->getClientOriginalName();

            // Check if new image name conflicts with existing images (excluding current)
            $imageExists = DB::table($this->auxiliaryTable)
                ->where('id', '!=', $id)
                ->where('auximgname', $fileName)
                ->exists();

            if ($imageExists) {
                return response()->json([
                    'success' => false,
                    'message' => 'An auxiliary with this image name already exists.'
                ], 400);
            }

            // Store the new image
            $targetDir = public_path('images/auxiliary');
            if (!file_exists($targetDir)) {
                mkdir($targetDir, 0755, true);
            }

            $image->move($targetDir, $fileName);

            // Delete old image if it exists and is different
            if ($auxiliary->auximgname && $auxiliary->auximgname !== $fileName) {
                $oldImagePath = public_path('images/auxiliary/' . $auxiliary->auximgname);
                if (file_exists($oldImagePath)) {
                    unlink($oldImagePath);
                    Log::info('Deleted old auxiliary image:', ['filename' => $auxiliary->auximgname]);
                }
            }

            // Add new image name to update data
            $updateData['auximgname'] = $fileName;
        }

        // Update the record
        DB::table($this->auxiliaryTable)
            ->where('id', $id)
            ->update($updateData);

        Log::info('Auxiliary updated successfully:', [
            'id' => $id,
            'auxname' => $auxname,
            'auxcode' => $auxcode,
            'image_updated' => $request->hasFile('image')
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Auxiliary updated successfully!'
        ]);

    } catch (Exception $e) {
        Log::error('Error updating auxiliary:', [
            'error' => $e->getMessage(),
            'id' => $id,
            'trace' => $e->getTraceAsString()
        ]);

        return response()->json([
            'success' => false,
            'message' => 'Failed to update auxiliary: ' . $e->getMessage()
        ], 500);
    }
}

    /**
     * Delete an auxiliary item
     */
    public function delete($id)
    {
        try {
            // Get auxiliary record
            $auxiliary = DB::table($this->auxiliaryTable)->where('id', $id)->first();
            
            if (!$auxiliary) {
                return response()->json([
                    'success' => false,
                    'message' => 'Auxiliary not found'
                ], 404);
            }

            $imagePath = public_path('images/auxiliary/' . $auxiliary->auximgname);

            // Delete file from disk if exists
            $fileDeleted = false;
            if (file_exists($imagePath)) {
                $fileDeleted = unlink($imagePath);
            }

            // Delete from database
            $deleted = DB::table($this->auxiliaryTable)->where('id', $id)->delete();

            if ($deleted) {
                Log::info('Auxiliary deleted successfully:', [
                    'id' => $id,
                    'auximgname' => $auxiliary->auximgname,
                    'file_deleted' => $fileDeleted
                ]);

                return response()->json([
                    'success' => true,
                    'message' => 'Image and database record deleted successfully.'
                ]);
            } else {
                return response()->json([
                    'success' => false,
                    'message' => 'Failed to delete from database'
                ], 500);
            }

        } catch (Exception $e) {
            Log::error('Error deleting auxiliary:', [
                'error' => $e->getMessage(),
                'id' => $id
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to delete auxiliary: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Print auxiliary labels
     */
    public function print(Request $request)
    {
        try {
            $request->validate([
                'image_name' => 'required|string',
                'quantity' => 'required|integer|min:1|max:999',
                'printer_id' => 'required|integer'
            ]);

            $imageName = basename($request->image_name);
            $quantity = (int)$request->quantity;
            $printerId = $request->printer_id;

            // Get printer information
            $printer = DB::table('tblprinters')
                ->where('printerid', $printerId)
                ->where('status', 'active')
                ->first();

            if (!$printer) {
                return response()->json([
                    'success' => false,
                    'message' => 'Selected printer not found or inactive'
                ], 404);
            }

            $printerIp = $printer->printerip;

            // Generate ZPL for multiple copies
            $zpl = '';
            for ($i = 0; $i < $quantity; $i++) {
                $inputPath = public_path('images/auxiliary/' . $imageName);
                $outputPath = storage_path('app/public/images/monochrome');
                
                // Label dimensions in dots at 203 DPI
                $labelWidth = 450;
                $labelHeight = 250;
                $newWidth = 400;
                $newHeight = 230;
                
                // Center the image on the label
                $xOffset = intval(($labelWidth - $newWidth) / 2);
                $yOffset = intval(($labelHeight - $newHeight) / 2);

                // Convert image to monochrome
                $success = $this->imageProcessingService->convertImageToMonochrome(
                    $inputPath, 
                    $outputPath, 
                    $newWidth, 
                    $newHeight
                );

                if (!$success) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Failed to process image: ' . $imageName
                    ], 500);
                }

                $monochromeImagePath = $outputPath . '/' . $imageName;
                $zpl .= $this->convertMonochromeToZPL($monochromeImagePath, $xOffset, $yOffset);
            }

            // Send to printer
            $printResult = $this->sendToPrinter($zpl, $printerIp);

            if ($printResult['success']) {
                Log::info('Auxiliary print job successful:', [
                    'image_name' => $imageName,
                    'quantity' => $quantity,
                    'printer' => $printer->printername,
                    'printer_ip' => $printerIp
                ]);

                return response()->json([
                    'success' => true,
                    'message' => "Print job sent successfully to {$printer->printername}"
                ]);
            } else {
                return response()->json([
                    'success' => false,
                    'message' => 'Failed to send print job: ' . ($printResult['message'] ?? 'Unknown error')
                ], 500);
            }

        } catch (Exception $e) {
            Log::error('Error printing auxiliary:', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to print: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Convert monochrome image to ZPL with offset positioning
     */
    protected function convertMonochromeToZPL($monochromeImagePath, $xOffset = 0, $yOffset = 0)
    {
        try {
            if (!file_exists($monochromeImagePath)) {
                Log::warning('Monochrome image not found:', ['path' => $monochromeImagePath]);
                return "^XA^FO50,50^ADN,18,18^FDImage not found^FS^XZ";
            }

            $fileParts = pathinfo($monochromeImagePath);
            $extension = strtolower($fileParts['extension']);

            if ($extension == 'png') {
                $image = imagecreatefrompng($monochromeImagePath);
            } elseif ($extension == 'jpg' || $extension == 'jpeg') {
                $image = imagecreatefromjpeg($monochromeImagePath);
            } else {
                return "^XA^FO50,50^ADN,18,18^FDUnsupported image type^FS^XZ";
            }

            if (!$image) {
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

            imagedestroy($image);

            // Convert binary string to hexadecimal string
            $hexString = '';
            for ($i = 0; $i < strlen($binaryString); $i += 8) {
                $byteString = substr($binaryString, $i, 8);
                $hexString .= str_pad(dechex(bindec($byteString)), 2, '0', STR_PAD_LEFT);
            }

            $bytesPerRow = ceil($width / 8);
            $totalBytes = strlen($hexString) / 2;

            // Construct ZPL command with offset
            $zplCommand  = "^XA\n";
            $zplCommand .= "^FO{$xOffset},{$yOffset}\n";
            $zplCommand .= "^GFA,{$totalBytes},{$totalBytes},{$bytesPerRow},{$hexString}\n";
            $zplCommand .= "^FS\n^XZ";

            return $zplCommand;

        } catch (Exception $e) {
            Log::error('Error converting monochrome to ZPL:', [
                'error' => $e->getMessage(),
                'path' => $monochromeImagePath
            ]);

            return "^XA^FO50,50^ADN,18,18^FDError converting image^FS^XZ";
        }
    }

    /**
     * Send ZPL to printer
     */
    protected function sendToPrinter($zpl, $printerIp)
    {
        try {
            Log::info('Sending print job:', [
                'printer_ip' => $printerIp,
                'server_url' => $this->printServerUrl,
                'zpl_length' => strlen($zpl)
            ]);

            $postData = http_build_query([
                'zpl' => $zpl,
                'printerSelect' => $printerIp
            ]);

            $ch = curl_init($this->printServerUrl);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, $postData);
            curl_setopt($ch, CURLOPT_HEADER, false);
            curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/x-www-form-urlencoded']);
            curl_setopt($ch, CURLOPT_TIMEOUT, 30);
            curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 10);

            $response = curl_exec($ch);
            $error = curl_error($ch);
            $status = curl_getinfo($ch, CURLINFO_HTTP_CODE);

            curl_close($ch);

            Log::info('Print server response:', [
                'response' => $response,
                'status' => $status,
                'error' => $error
            ]);

            if ($response === "Message sent to printer successfully." || $status === 200) {
                return [
                    'success' => true,
                    'message' => 'Print job sent successfully'
                ];
            } else {
                return [
                    'success' => false,
                    'message' => $response ?: $error ?: 'Unknown error'
                ];
            }

        } catch (Exception $e) {
            Log::error('Error sending to printer:', [
                'error' => $e->getMessage(),
                'printer_ip' => $printerIp
            ]);

            return [
                'success' => false,
                'message' => $e->getMessage()
            ];
        }
    }
}