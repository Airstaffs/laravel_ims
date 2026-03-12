<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

class SwitcheruController extends BasetablesController
{
   public function index(Request $request)
{
    try {
        Log::info('SwitcheruController@index called', [
            'productTable'        => $this->productTable,
            'capturedImagesTable' => $this->capturedImagesTable,
            'company'             => $this->company,
        ]);

        $perPage = $request->input('per_page', 10);
        $search  = $request->input('search', '');

        if (!Schema::hasTable('tblswitcherus')) {
            Log::error('tblswitcherus table does not exist');
            return response()->json(['data' => [], 'total' => 0, 'error' => 'tblswitcherus table not found']);
        }

        $query = DB::table('tblswitcherus as sw')->select(['sw.*']);

        if (!empty($search)) {
            $query->where(function ($q) use ($search) {
                $q->where('sw.sendserial',    'like', "%{$search}%")
                  ->orWhere('sw.receiveserial', 'like', "%{$search}%")
                  ->orWhere('sw.buyer',          'like', "%{$search}%")
                  ->orWhere('sw.rtcounter',      'like', "%{$search}%")
                  ->orWhere('sw.product_title',  'like', "%{$search}%");
            });
        }

        $query->orderByDesc('sw.created_at');
        $results = $query->paginate($perPage);

        Log::info('Switcheru records fetched', ['count' => $results->count()]);

        try {
            $capturedImagesTable = $this->capturedImagesTable;
            $productTable        = $this->productTable;
            $company             = $this->company;

            $hasCapturedImagesTable = Schema::hasTable($capturedImagesTable);

            // Collect every serial we need to look up
            $allSerials = [];
            foreach ($results as $row) {
                if (!empty($row->sendserial))   $allSerials[] = $row->sendserial;
                if (!empty($row->receiveserial)) $allSerials[] = $row->receiveserial;
            }
            $allSerials = array_values(array_unique($allSerials));

            Log::info('Serials to look up', ['serials' => $allSerials]);

            $serialToProduct = [];
            $capturedImages  = [];

            if (count($allSerials) && Schema::hasTable($productTable)) {

                // ✅ Only select columns that actually exist on tblproduct
                $productRows = DB::table($productTable)
                    ->where(function ($q) use ($allSerials) {
                        $q->whereIn('serialnumber',  $allSerials)
                          ->orWhereIn('serialnumberb', $allSerials);
                    })
                    ->select(['ProductID', 'serialnumber', 'serialnumberb', 'FNSKUviewer'])
                    ->get();

                Log::info('Product rows found for serials', ['count' => $productRows->count()]);

                // Build serial → product lookup
                foreach ($productRows as $p) {
                    if ($p->serialnumber)  $serialToProduct[strtolower(trim($p->serialnumber))]  = $p;
                    if ($p->serialnumberb) $serialToProduct[strtolower(trim($p->serialnumberb))] = $p;
                }

                // Fetch captured images by ProductID
                if ($hasCapturedImagesTable) {
                    $productIds = $productRows->pluck('ProductID')->unique()->toArray();
                    if (count($productIds)) {
                        $imgRows = DB::table($capturedImagesTable)
                            ->whereIn('ProductID', $productIds)
                            ->get();
                        foreach ($imgRows as $img) {
                            $capturedImages[$img->ProductID] = $img;
                        }
                        Log::info('Captured images found', ['count' => count($capturedImages)]);
                    }
                }
            }

            // Attach to each switcheru row
            $results->getCollection()->transform(function ($row) use ($serialToProduct, $capturedImages, $company) {
                $row->company = $company;

                // ── Sent serial ──
                $row->sentProduct = null;
                $row->sentImages  = (object) [];
                if (!empty($row->sendserial)) {
                    $key = strtolower(trim($row->sendserial));
                    if (isset($serialToProduct[$key])) {
                        $prod = $serialToProduct[$key];
                        $row->sentProduct = $prod;
                        if (isset($capturedImages[$prod->ProductID])) {
                            $row->sentImages = $capturedImages[$prod->ProductID];
                        }
                    }
                }

                // ── Received serial ──
                $row->receivedProduct = null;
                $row->receivedImages  = (object) [];
                if (!empty($row->receiveserial)) {
                    $key = strtolower(trim($row->receiveserial));
                    if (isset($serialToProduct[$key])) {
                        $prod = $serialToProduct[$key];
                        $row->receivedProduct = $prod;
                        if (isset($capturedImages[$prod->ProductID])) {
                            $row->receivedImages = $capturedImages[$prod->ProductID];
                        }
                    }
                }

                return $row;
            });

        } catch (\Exception $imgError) {
            Log::error('Error attaching images to switcheru records', [
                'message' => $imgError->getMessage(),
                'trace'   => $imgError->getTraceAsString(),
            ]);

            $results->getCollection()->transform(function ($row) {
                $row->company         = 'Airstaffs';
                $row->sentProduct     = null;
                $row->sentImages      = (object) [];
                $row->receivedProduct = null;
                $row->receivedImages  = (object) [];
                return $row;
            });
        }

        return response()->json($results);

    } catch (\Exception $e) {
        Log::error('SwitcheruController@index fatal error', [
            'message' => $e->getMessage(),
            'line'    => $e->getLine(),
            'file'    => $e->getFile(),
            'trace'   => $e->getTraceAsString(),
        ]);

        return response()->json([
            'error'   => 'An error occurred while fetching switcheru records',
            'message' => $e->getMessage(),
        ], 500);
    }
}
}