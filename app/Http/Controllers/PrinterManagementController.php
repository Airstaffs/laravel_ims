<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class PrinterManagementController extends Controller
{
    /**
     * Get all printers or filter by type for management
     */
    public function getAllPrinters(Request $request)
    {
        try {
            $query = DB::table('tblprinters');
            
            if ($request->has('type') && $request->type) {
                $query->where('printer_type', $request->type);
            }
            
            $printers = $query->orderBy('printername')->get();
            
            return response()->json([
                'success' => true,
                'printers' => $printers
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error fetching printers: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get specific printer details
     */
    public function getPrinter($id)
    {
        try {
            $printer = DB::table('tblprinters')
                ->where('printerid', $id)
                ->first();

            if (!$printer) {
                return response()->json([
                    'success' => false,
                    'message' => 'Printer not found'
                ], 404);
            }

            return response()->json([
                'success' => true,
                'printer' => $printer
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error fetching printer: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Add new printer
     */
    public function addPrinter(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'printer_name' => 'required|string|max:255|unique:tblprinters,printername',
            'printer_type' => 'required|in:small_label,instruction_card',
            'ip_address' => 'required|ip',
            'port' => 'nullable|integer|min:1|max:65535',
            'description' => 'nullable|string|max:500'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => $validator->errors()->first()
            ], 422);
        }

        try {
            $printerId = DB::table('tblprinters')->insertGetId([
                'printername' => $request->printer_name,
                'printer_type' => $request->printer_type,
                'printerip' => $request->ip_address,
                'port' => $request->port ?? 9100,
                'description' => $request->description,
                'status' => 'active',
                'created_at' => now(),
                'updated_at' => now()
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Printer added successfully',
                'printer_id' => $printerId
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error adding printer: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Update printer
     */
    public function updatePrinter(Request $request, $id)
    {
        $validator = Validator::make($request->all(), [
            'printer_name' => 'required|string|max:255|unique:tblprinters,printername,' . $id . ',printerid',
            'printer_type' => 'required|in:small_label,instruction_card',
            'ip_address' => 'required|ip',
            'port' => 'nullable|integer|min:1|max:65535',
            'description' => 'nullable|string|max:500',
            'status' => 'required|in:active,inactive,maintenance'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => $validator->errors()->first()
            ], 422);
        }

        try {
            $updated = DB::table('tblprinters')
                ->where('printerid', $id)
                ->update([
                    'printername' => $request->printer_name,
                    'printer_type' => $request->printer_type,
                    'printerip' => $request->ip_address,
                    'port' => $request->port ?? 9100,
                    'description' => $request->description,
                    'status' => $request->status,
                    'updated_at' => now()
                ]);

            if (!$updated) {
                return response()->json([
                    'success' => false,
                    'message' => 'Printer not found'
                ], 404);
            }

            return response()->json([
                'success' => true,
                'message' => 'Printer updated successfully'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error updating printer: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Delete printer
     */
    public function deletePrinter($id)
    {
        try {
            // Check if printer is married and update the married partner
            $marriedPrinter = DB::table('tblprinters')
                ->where('married_to_printer_id', $id)
                ->first();

            if ($marriedPrinter) {
                // Remove marriage from the partner
                DB::table('tblprinters')
                    ->where('printerid', $marriedPrinter->printerid)
                    ->update([
                        'married_to_printer_id' => null,
                        'marriage_name' => null,
                        'marriage_description' => null,
                        'updated_at' => now()
                    ]);
            }

            $deleted = DB::table('tblprinters')
                ->where('printerid', $id)
                ->delete();

            if (!$deleted) {
                return response()->json([
                    'success' => false,
                    'message' => 'Printer not found'
                ], 404);
            }

            return response()->json([
                'success' => true,
                'message' => 'Printer deleted successfully'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error deleting printer: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Test printer connection
     */
    public function testPrinter($id)
    {
        try {
            $printer = DB::table('tblprinters')
                ->where('printerid', $id)
                ->first();

            if (!$printer) {
                return response()->json([
                    'success' => false,
                    'message' => 'Printer not found'
                ], 404);
            }

            // Test printer connection
            $testResult = $this->performPrinterTest($printer->printerip, $printer->port);

            return response()->json([
                'success' => $testResult,
                'message' => $testResult ? 'Printer connection successful' : 'Printer connection failed'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error testing printer: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get available printers for marriage (unmarried printers)
     */
    public function getAvailablePrinters()
    {
        try {
            $smallLabelPrinters = DB::table('tblprinters')
                ->where('printer_type', 'small_label')
                ->where('status', 'active')
                ->whereNull('married_to_printer_id')
                ->get();

            $instructionCardPrinters = DB::table('tblprinters')
                ->where('printer_type', 'instruction_card')
                ->where('status', 'active')
                ->whereNull('married_to_printer_id')
                ->get();

            return response()->json([
                'success' => true,
                'small_label' => $smallLabelPrinters,
                'instruction_card' => $instructionCardPrinters
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error fetching available printers: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Marry printers
     */
    public function marryPrinters(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'small_label_printer_id' => 'required|exists:tblprinters,printerid',
            'instruction_card_printer_id' => 'required|exists:tblprinters,printerid|different:small_label_printer_id',
            'marriage_name' => 'required|string|max:255',
            'description' => 'nullable|string|max:500'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => $validator->errors()->first()
            ], 422);
        }

        try {
            // Verify printer types
            $smallLabelPrinter = DB::table('tblprinters')
                ->where('printerid', $request->small_label_printer_id)
                ->where('printer_type', 'small_label')
                ->first();

            $instructionCardPrinter = DB::table('tblprinters')
                ->where('printerid', $request->instruction_card_printer_id)
                ->where('printer_type', 'instruction_card')
                ->first();

            if (!$smallLabelPrinter || !$instructionCardPrinter) {
                return response()->json([
                    'success' => false,
                    'message' => 'Invalid printer types selected'
                ], 422);
            }

            // Check if either printer is already married
            if ($smallLabelPrinter->married_to_printer_id || $instructionCardPrinter->married_to_printer_id) {
                return response()->json([
                    'success' => false,
                    'message' => 'One or both printers are already married'
                ], 422);
            }

            // Create bidirectional marriage relationship
            DB::transaction(function () use ($request) {
                // Update small label printer
                DB::table('tblprinters')
                    ->where('printerid', $request->small_label_printer_id)
                    ->update([
                        'married_to_printer_id' => $request->instruction_card_printer_id,
                        'marriage_name' => $request->marriage_name,
                        'marriage_description' => $request->description,
                        'updated_at' => now()
                    ]);

                // Update instruction card printer
                DB::table('tblprinters')
                    ->where('printerid', $request->instruction_card_printer_id)
                    ->update([
                        'married_to_printer_id' => $request->small_label_printer_id,
                        'marriage_name' => $request->marriage_name,
                        'marriage_description' => $request->description,
                        'updated_at' => now()
                    ]);
            });

            return response()->json([
                'success' => true,
                'message' => 'Printers married successfully'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error marrying printers: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get married printers
     */
    public function getMarriedPrinters()
    {
        try {
            $marriages = DB::table('tblprinters as sl')
                ->join('tblprinters as ic', 'sl.married_to_printer_id', '=', 'ic.printerid')
                ->where('sl.printer_type', 'small_label')
                ->where('ic.printer_type', 'instruction_card')
                ->whereNotNull('sl.married_to_printer_id')
                ->select(
                    'sl.printerid as sl_id',
                    'sl.printername as sl_name',
                    'sl.printerip as sl_ip',
                    'sl.marriage_name',
                    'sl.marriage_description',
                    'ic.printerid as ic_id',
                    'ic.printername as ic_name',
                    'ic.printerip as ic_ip'
                )
                ->get();

            $formattedMarriages = $marriages->map(function ($marriage) {
                return [
                    'marriage_name' => $marriage->marriage_name,
                    'description' => $marriage->marriage_description,
                    'small_label_printer' => [
                        'printer_id' => $marriage->sl_id,
                        'printer_name' => $marriage->sl_name,
                        'ip_address' => $marriage->sl_ip
                    ],
                    'instruction_card_printer' => [
                        'printer_id' => $marriage->ic_id,
                        'printer_name' => $marriage->ic_name,
                        'ip_address' => $marriage->ic_ip
                    ]
                ];
            });

            return response()->json([
                'success' => true,
                'marriages' => $formattedMarriages
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error fetching married printers: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Divorce printers
     */
    public function divorcePrinters($printerId)
    {
        try {
            $printer = DB::table('tblprinters')
                ->where('printerid', $printerId)
                ->first();

            if (!$printer || !$printer->married_to_printer_id) {
                return response()->json([
                    'success' => false,
                    'message' => 'Printer not found or not married'
                ], 404);
            }

            $marriedPartnerId = $printer->married_to_printer_id;

            // Remove marriage from both printers
            DB::transaction(function () use ($printerId, $marriedPartnerId) {
                DB::table('tblprinters')
                    ->whereIn('printerid', [$printerId, $marriedPartnerId])
                    ->update([
                        'married_to_printer_id' => null,
                        'marriage_name' => null,
                        'marriage_description' => null,
                        'updated_at' => now()
                    ]);
            });

            return response()->json([
                'success' => true,
                'message' => 'Printers divorced successfully'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error divorcing printers: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Perform actual printer test
     */
    private function performPrinterTest($ip, $port)
    {
        try {
            $connection = @fsockopen($ip, $port, $errno, $errstr, 5);
            if ($connection) {
                fclose($connection);
                return true;
            }
            return false;
        } catch (\Exception $e) {
            return false;
        }
    }
}