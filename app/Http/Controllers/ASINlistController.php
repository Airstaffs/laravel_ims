<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Http;

require base_path('app/Helpers/aws_helpers.php');

class ASINlistController extends BasetablesController
{
    /**
     * Display a listing of ASINs with their FNSKU data
     */
    public function index(Request $request)
    {
        try {
            $perPage = min($request->input('per_page', 15), 100);
            $search = $request->input('search', '');
            $store = $request->input('store', '');
            $page = $request->input('page', 1);

            // Build the main query - ASIN with FNSKU aggregated data
            $asinQuery = DB::table($this->asinTable . ' as asin')
                ->select([
                    'asin.ASIN',
                    'asin.internal as AStitle',
                    'asin.system_title', // Added system_title
                    'asin.metakeyword',
                    'asin.EAN',
                    'asin.UPC',
                    'asin.ParentAsin',
                    'asin.CousinASIN',
                    'asin.UpgradeASIN',
                    'asin.GrandASIN',
                    'asin.instructioncard',
                    'asin.instructioncard2',
                    'asin.instructioncard3',
                    'asin.instructionlink',
                    'asin.usermanuallink',
                    'asin.asinimg',
                    'asin.vectorimage',
                    'asin.TRANSPARENCY_QR_STATUS',
                    'asin.color',
                    'asin.QuantityInside',
                    // Amazon dimensions (read-only)
                    'asin.dimension_length',
                    'asin.dimension_width',
                    'asin.dimension_height',
                    'asin.weight_value',
                    'asin.weight_unit',
                    // White/Default dimensions (editable)
                    'asin.white_length',
                    'asin.white_width',
                    'asin.white_height',
                    'asin.white_value',
                    'asin.white_unit',
                    DB::raw('COUNT(fnsku.FNSKU) as fnsku_count')
                ])
                ->leftJoin($this->fnskuTable . ' as fnsku', 'asin.ASIN', '=', 'fnsku.ASIN')
                ->where('asin.ASIN', '!=', '')
                ->whereNotNull('asin.ASIN');

            // Apply search filters
            if (!empty($search)) {
                $asinQuery->where(function ($query) use ($search) {
                    $query->where('asin.ASIN', 'like', "%{$search}%")
                        ->orWhere('asin.internal', 'like', "%{$search}%")
                        ->orWhere('asin.metakeyword', 'like', "%{$search}%")
                        ->orWhere('asin.EAN', 'like', "%{$search}%")
                        ->orWhere('asin.UPC', 'like', "%{$search}%")
                        ->orWhere('asin.system_title', 'like', "%{$search}%") // Added system_title to search
                        ->orWhere('fnsku.FNSKU', 'like', "%{$search}%");
                });
            }

            // Apply store filter
            if (!empty($store)) {
                $asinQuery->where('fnsku.storename', $store);
            }

            // Group by ASIN - Added system_title
            $asinQuery->groupBy(
                'asin.ASIN',
                'asin.internal',
                'asin.system_title',
                'asin.metakeyword',
                'asin.EAN',
                'asin.UPC',
                'asin.ParentAsin',
                'asin.CousinASIN',
                'asin.UpgradeASIN',
                'asin.GrandASIN',
                'asin.instructioncard',
                'asin.instructioncard2',
                'asin.instructioncard3',
                'asin.instructionlink',
                'asin.usermanuallink',
                'asin.asinimg',
                'asin.vectorimage',
                'asin.TRANSPARENCY_QR_STATUS',
                'asin.color',
                'asin.QuantityInside',
                'asin.dimension_length',
                'asin.dimension_width',
                'asin.dimension_height',
                'asin.weight_value',
                'asin.weight_unit',
                'asin.white_length',
                'asin.white_width',
                'asin.white_height',
                'asin.white_value',
                'asin.white_unit'
            )
                ->having('fnsku_count', '>', 0);

            // Order by ASIN
            $asinQuery->orderBy('asin.ASIN', 'asc');

            // Get paginated results
            $asins = $asinQuery->paginate($perPage);

            // Get ASINs for batch loading FNSKU details
            $asinList = $asins->getCollection()->pluck('ASIN')->toArray();

            if (empty($asinList)) {
                $result = $asins->toArray();
                $result['data'] = [];
                return response()->json($result);
            }

            // Batch load detailed FNSKU data for all ASINs including grading
            $fnskuDetails = DB::table($this->fnskuTable)
                ->select([
                    'ASIN',
                    'FNSKU',
                    'MSKU',
                    'storename',
                    'grading',
                    'Units'
                ])
                ->whereIn('ASIN', $asinList)
                ->orderBy('FNSKU', 'asc')
                ->get()
                ->groupBy('ASIN');

            // Process results with batch-loaded FNSKU data
            $results = $asins->getCollection()->map(function ($item) use ($fnskuDetails) {
                if (empty($item->ASIN)) {
                    return null;
                }

                // Add FNSKU details from batch-loaded data
                $item->fnskus = isset($fnskuDetails[$item->ASIN])
                    ? $fnskuDetails[$item->ASIN]->toArray()
                    : [];

                // Add instruction card URLs from database
                $item->instruction_card_urls = [
                    'card1' => $item->instructioncard ? url($item->instructioncard) : null,
                    'card2' => $item->instructioncard2 ? url($item->instructioncard2) : null,
                    'card3' => $item->instructioncard3 ? url($item->instructioncard3) : null
                ];

                // Add user manual URL if exists
                $item->user_manual_url = $item->usermanuallink ? url($item->usermanuallink) : null;

                // Add ASIN image URL if exists
                $item->asin_image_url = $item->asinimg ? url($item->asinimg) : null;

                // Add vector image URL if exists
                $item->vector_image_url = $item->vectorimage ? url($item->vectorimage) : null;

                // Ensure numeric values are properly typed
                $item->fnsku_count = (int) $item->fnsku_count;

                // Add display_title property for frontend - prioritize system_title over internal
                $item->display_title = !empty($item->system_title) ? $item->system_title : $item->AStitle;

                return $item;
            })->filter(); // Remove null items

            // Update the collection
            $asins->setCollection($results);
            $result = $asins->toArray();

            return response()->json($result);

        } catch (\Exception $e) {
            Log::error('Error in ASINlistController@index: ' . $e->getMessage());
            Log::error($e->getTraceAsString());

            return response()->json([
                'error' => 'An error occurred while retrieving ASIN data',
                'message' => $e->getMessage(),
                'data' => [],
                'current_page' => 1,
                'last_page' => 1,
                'total' => 0
            ], 500);
        }
    }


    /**
     * Get list of store names for the dropdown
     */
    public function getStores()
    {
        try {
            return response()->json(Cache::remember('asin_stores', 3600, function () {
                return DB::table($this->fnskuTable)
                    ->select('storename')
                    ->distinct()
                    ->whereNotNull('storename')
                    ->where('storename', '!=', '')
                    ->orderBy('storename')
                    ->pluck('storename');
            }));
        } catch (\Exception $e) {
            Log::error('Error getting stores: ' . $e->getMessage());

            return response()->json([
                'error' => 'An error occurred while retrieving store list',
                'message' => $e->getMessage()
            ], 500);
        }
    }

    protected static array $allowedUsers = ['Jundell', 'Admin', 'Julius', 'Glen'];

    public function searchAsin(Request $request)
    {
        // Get current username (if logged in)
        $currentUser = Auth::user();
        $username = $currentUser?->username;

        // Check if this user should bypass store filter
        $isAllowedUser = $username && in_array($username, static::$allowedUsers, true);

        $keyword = strtolower(trim($request->query('keyword')));
        $storeFilter = strtolower(str_replace(' ', '', trim($request->query('storename'))));

        $results = DB::table('tblasin as a')
            ->leftJoin('tblfnsku as f', 'a.ASIN', '=', 'f.ASIN')
            ->select('a.ASIN', 'a.internal AS title', DB::raw('f.storename'))
            ->where(function ($query) use ($keyword) {
                $query->whereRaw('LOWER(a.ASIN) LIKE ?', ["%{$keyword}%"])
                    ->orWhereRaw('LOWER(a.internal) LIKE ?', ["%{$keyword}%"])
                    ->orWhereRaw("REPLACE(LOWER(f.storename), ' ', '') LIKE ?", ["%{$keyword}%"]);
            })
            // 🧠 Only apply store filter if NOT in allowed user list
            ->when(!$isAllowedUser && $storeFilter, function ($query) use ($storeFilter) {
                $query->where(function ($sub) use ($storeFilter) {
                    $sub->whereNull('f.storename')
                        ->orWhereRaw("REPLACE(LOWER(f.storename), ' ', '') = ?", [$storeFilter]);
                });
            })
            ->groupBy('a.ASIN', 'a.internal', 'f.storename')
            ->limit(15)
            ->get();

        return response()->json($results);
    }

    public function getAllowedConditions(Request $request)
    {
        $request->validate([
            'asin' => 'required|string',
            'storename' => 'required|string',
        ]);

        $asin = strtoupper(trim($request->input('asin')));
        $storename = trim($request->input('storename'));

        $marketplaceId = $this->getMarketplaceIdForStore($storename) ?? 'ATVPDKIKX0DER';

        $allConditions = [
            'new_new',
            'new_open_box',
            'new_oem',
            'refurbished_refurbished',
            'used_like_new',
            'used_very_good',
            'used_good',
            'used_acceptable',
            'collectible_like_new',
            'collectible_very_good',
            'collectible_good',
            'collectible_acceptable',
            'club_club',
        ];

        $allowed = [];
        $blocked = [];
        $conditionsDebug = [];   // 👈 new

        foreach ($allConditions as $cond) {
            $restriction = $this->checkListingRestriction(
                $storename,
                $asin,
                $cond,
                $marketplaceId
            );

            $isRestricted = (bool)($restriction['restricted'] ?? false);
            $reason = $restriction['reason'] ?? null;
            $raw = $restriction['raw'] ?? null; // this should contain callListingsRestrictions result

            if ($isRestricted) {
                $blocked[] = [
                    'condition' => $cond,
                    'reason' => $reason,
                ];
            } else {
                $allowed[] = $cond;
            }

            // Store per-condition debug info
            $conditionsDebug[] = [
                'condition' => $cond,
                'restricted' => $isRestricted,
                'reason' => $reason,
                'amazon_raw' => $raw, // includes success, data, parsed, errors
            ];
        }

        return response()->json([
            'success' => true,
            'asin' => $asin,
            'storename' => $storename,
            'allowed_conditions' => $allowed,
            'blocked_conditions' => $blocked,
            'conditions_debug' => $conditionsDebug,   // 👈 Amazon responses per condition
        ]);
    }



    public function saveMsku(Request $request)
    {
        $request->validate([
            'mskus' => 'required|array',
            'mskus.*.asin' => 'required|string',
            'mskus.*.msku' => 'required|string',
            'mskus.*.condition' => 'required|string',
            'mskus.*.storename' => 'required|string',
        ]);

        $success = [];
        $duplicate = [];
        $failed = [];

        foreach ($request->mskus as $row) {
            $existing = DB::table('tblfnsku')->where('MSKU', $row['msku'])->exists();

            if ($existing) {
                $duplicate[] = $row['msku'];
                continue;
            }

            try {
                DB::table('tblfnsku')->insert([
                    'ASIN' => $row['asin'],
                    'MSKU' => $row['msku'],
                    'FNSKU' => $row['msku'],
                    'grading' => $this->convertConditionToGrading($row['condition']),
                    'storename' => $row['storename'],
                    'insert_date' => now(),
                    'amazon_status' => 'Not Existed',
                    'fnsku_status' => 'available',
                    'LimitStatus' => 'False',
                    'donotreplenish' => 'none',
                    'Units' => 11,
                ]);
                $success[] = $row['msku'];
            } catch (\Exception $e) {
                $failed[] = ['msku' => $row['msku'], 'error' => $e->getMessage()];
            }
        }

        return response()->json([
            'success' => $success,
            'duplicates' => $duplicate,
            'failed' => $failed,
            'message' => 'Processed MSKUs with duplicate and error checking.'
        ]);
    }

    private function convertConditionToGrading($condition)
    {
        return match ($condition) {
            'new_new' => 'New',
            'new_open_box' => 'OpenBox',
            'new_oem' => 'OEM',
            'refurbished_refurbished' => 'Refurbished',
            'used_like_new' => 'UsedLikeNew',
            'used_very_good' => 'UsedVeryGood',
            'used_good' => 'UsedGood',
            'used_acceptable' => 'UsedAcceptable',
            'collectible_like_new' => 'CollectibleLikeNew',
            'collectible_very_good' => 'CollectibleVeryGood',
            'collectible_good' => 'CollectibleGood',
            'collectible_acceptable' => 'CollectibleAcceptable',
            'club_club' => 'Club',
            default => 'Unknown',
        };
    }

    public function generateMsku(Request $request)
    {
        $request->validate([
            'asin' => 'required|string',
            'condition' => 'required|string',
            'storename' => 'required|string',
        ]);

        $asin = strtoupper(trim($request->asin));
        $condition = $request->condition;
        $storeInput = trim($request->storename);

        // 1) Get store abbreviation
        $abbreviation = DB::table('tblstores')
            ->where('storename', $storeInput)
            ->value('abbreviation');

        if (!$abbreviation) {
            return response()->json([
                'error' => "No abbreviation found for store: {$storeInput}",
            ], 404);
        }

        // 2) Condition → short code map
        $prefixMap = [
            "new_new" => "NN",
            "new_open_box" => "NOB",
            "new_oem" => "NOEM",
            "refurbished_refurbished" => "RR",
            "used_like_new" => "ULN",
            "used_very_good" => "UVG",
            "used_good" => "UG",
            "used_acceptable" => "UA",
            "collectible_like_new" => "CLN",
            "collectible_very_good" => "CVG",
            "collectible_good" => "CG",
            "collectible_acceptable" => "CA",
            "club_club" => "CLUB",
        ];

        $code = $prefixMap[$condition] ?? 'UNK';

        // 3) Generate unique MSKU
        $attempt = 0;
        $maxAttempts = 30;
        $msku = null;

        do {
            $rand4 = strtoupper(Str::random(4));
            $msku = "{$asin}-{$abbreviation}-{$code}-{$rand4}";
            $exists = DB::table('tblfnsku')->where('MSKU', $msku)->exists();
            $attempt++;
        } while ($exists && $attempt < $maxAttempts);

        if ($attempt >= $maxAttempts) {
            Log::warning('Failed to generate unique MSKU after multiple attempts', [
                'asin' => $asin,
                'storename' => $storeInput,
                'condition' => $condition,
            ]);

            return response()->json([
                'error' => 'Unable to generate unique MSKU after multiple attempts.',
            ], 422);
        }

        Log::info('Generated MSKU', ['msku' => $msku]);

        // 🔙 Back to the “former” response shape
        return response()->json([
            'msku' => $msku,
            'condition' => $condition,
        ]);
    }


    private function getMarketplaceIdForStore(string $store): string
    {
        // Adjust to your real mapping if needed
        return 'ATVPDKIKX0DER'; // Amazon US
    }

    private function callListingsRestrictions(
        string $store,
        string $asin,
        ?string $conditionType,
        string $marketplaceId = 'ATVPDKIKX0DER'
    ): array {
        $endpoint = 'https://sellingpartnerapi-na.amazon.com';
        $canonicalHeaders = "host:sellingpartnerapi-na.amazon.com";
        $path = '/listings/2021-08-01/restrictions';

        // 1) Credentials
        $credentials = AWSCredentials($store);
        if (!$credentials) {
            return [
                'success' => false,
                'message' => "No AWS credentials found for store {$store}",
                'status' => 500,
                'data' => null,
                'parsed' => null,
                'errors' => [],
            ];
        }

        // 2) Query params (build FIRST)
        $customParams = [
            'asin' => $asin,
            'marketplaceIds' => $marketplaceId,
            'reasonLocale' => 'en_US',
            'sellerId' => $credentials['MerchantID'],
        ];

        // ✅ now conditionType will actually be included
        if (!empty($conditionType)) {
            $customParams['conditionType'] = $conditionType;
        }

        $nextToken = null;

        // 3) Access token
        $accessToken = fetchAccessToken($credentials, $returnRaw = false);
        if (!$accessToken) {
            return [
                'success' => false,
                'message' => "Failed to fetch access token for store {$store}",
                'status' => 500,
                'data' => null,
                'parsed' => null,
                'errors' => [],
            ];
        }

        // 4) Headers + URL
        $headers = buildHeaders(
            $credentials,
            $accessToken,
            'GET',
            'execute-api',
            'us-east-1',
            $path,
            $nextToken,
            $customParams,
            $endpoint,
            $canonicalHeaders
        );

        $headers['accept'] = 'application/json';

        $queryString = buildQueryString($nextToken, $customParams);
        $url = "{$endpoint}{$path}?{$queryString}";

        Log::info('GetListingsRestrictions request', [
            'url' => $url,
            'query' => $customParams,
        ]);

        try {
            $response = Http::timeout(30)
                ->withHeaders($headers)
                ->get($url);

            $rawBody = $response->body();
            $parsed = null;

            try {
                $parsed = $response->json();
            } catch (\Throwable $e) {
                $parsed = null;
            }

            if ($response->successful()) {
                return [
                    'success' => true,
                    'message' => 'Data fetched successfully',
                    'status' => $response->status(),
                    'data' => $rawBody,
                    'parsed' => $parsed,
                    'errors' => [],
                ];
            }

            $errorsFromAmazon = [];
            if (is_array($parsed)) {
                if (isset($parsed['errors']) && is_array($parsed['errors'])) {
                    $errorsFromAmazon = $parsed['errors'];
                } elseif (array_keys($parsed) === range(0, count($parsed) - 1)) {
                    $errorsFromAmazon = $parsed;
                }
            }

            return [
                'success' => false,
                'message' => 'Amazon returned an error for GetListingsRestrictions.',
                'status' => $response->status(),
                'data' => $rawBody,
                'parsed' => $parsed,
                'errors' => $errorsFromAmazon,
            ];
        } catch (\Throwable $e) {
            Log::error('GetListingsRestrictions exception', [
                'asin' => $asin,
                'condition' => $conditionType,
                'store' => $store,
                'error' => $e->getMessage(),
            ]);

            return [
                'success' => false,
                'message' => 'Exception during GetListingsRestrictions call.',
                'status' => 500,
                'data' => null,
                'parsed' => null,
                'errors' => [
                    [
                        'code' => 'EXCEPTION',
                        'message' => $e->getMessage(),
                        'details' => '',
                    ]
                ],
            ];
        }
    }


    private function checkListingRestriction(
        string $store,
        string $asin,
        string $conditionType,
        string $marketplaceId = 'ATVPDKIKX0DER'
    ): array {
        $result = $this->callListingsRestrictions($store, $asin, $conditionType, $marketplaceId);

        // 1️⃣ If the SP-API call itself failed → treat as restricted
        // (this is where your 403 "Unauthorized" lives)
        if (!($result['success'] ?? false)) {
            $errors = $result['errors'] ?? [];

            $reason = $result['message'] ?? 'GetListingsRestrictions call failed.';
            if (!empty($errors)) {
                $reason = $errors[0]['message'] ?? $reason;
            }

            return [
                'restricted' => true,      // 🔴 FAIL-CLOSED HERE
                'reason' => $reason,
                'raw' => $result,
            ];
        }

        // 2️⃣ SP-API call succeeded → inspect restrictions
        $parsed = $result['parsed'];
        if (!is_array($parsed)) {
            $parsed = json_decode($result['data'] ?? '', true);
        }

        $restrictions = $parsed['restrictions'] ?? [];

        if (empty($restrictions)) {
            // No restrictions returned → allowed
            return [
                'restricted' => false,
                'reason' => null,
                'raw' => $result,
            ];
        }

        // 3️⃣ If any restriction entry has reasons, treat as restricted
        foreach ($restrictions as $restriction) {
            $reasons = $restriction['reasons'] ?? [];
            if (!empty($reasons)) {
                $firstReason = $reasons[0] ?? [];
                $reasonText = $firstReason['message'] ?? ($firstReason['reasonCode'] ?? 'Restricted by Amazon');

                return [
                    'restricted' => true,
                    'reason' => $reasonText,
                    'raw' => $result,
                ];
            }
        }

        // If we somehow reach here, treat as not restricted
        return [
            'restricted' => false,
            'reason' => null,
            'raw' => $result,
        ];
    }


    public function fetchStores()
    {
        try {
            return response()->json(
                DB::table('tblstores')
                    ->select('storename')
                    ->whereNotNull('storename')
                    ->where('storename', '!=', '')
                    ->distinct()
                    ->orderBy('storename')
                    ->pluck('storename')
            );
        } catch (\Exception $e) {
            Log::error('Error fetching stores from tblstores: ' . $e->getMessage());

            return response()->json([
                'error' => 'An error occurred while fetching store list',
                'message' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Update ASIN details (EAN/UPC/Instruction Link/Meta Keyword/Transparency)
     */
    public function updateAsinDetails(Request $request)
    {
        try {
            $validated = $request->validate([
                'asin' => 'required|string',
                'ean' => 'nullable|string|max:20',
                'upc' => 'nullable|string|max:20',
                'instruction_link' => 'nullable|string|max:1000',
                'metakeyword' => 'nullable|string|max:500',
                'transparency_qr_status' => 'nullable|string|max:1000',
                'quantity_inside' => 'nullable|integer|min:1|max:4',
                'system_title' => 'nullable|string|max:500'
            ]);

            // Check if ASIN exists
            $asin = DB::table($this->asinTable)
                ->where('ASIN', $validated['asin'])
                ->first();

            if (!$asin) {
                return response()->json([
                    'success' => false,
                    'message' => 'ASIN not found'
                ], 404);
            }

            // Prepare update data
            $updateData = [
                'EAN' => $validated['ean'],
                'UPC' => $validated['upc'],
                'instructionlink' => $validated['instruction_link'],
                'metakeyword' => $validated['metakeyword'],
                'TRANSPARENCY_QR_STATUS' => $validated['transparency_qr_status'],
                'QuantityInside' => $validated['quantity_inside'],
                'system_title' => $validated['system_title'] // Added system_title
            ];

            // Update ASIN details
            $updated = DB::table($this->asinTable)
                ->where('ASIN', $validated['asin'])
                ->update($updateData);

            Log::info("ASIN details update attempt for: {$validated['asin']}", $updateData);

            if ($updated !== false) {
                Log::info("ASIN details updated: {$validated['asin']}");

                return response()->json([
                    'success' => true,
                    'message' => 'ASIN details updated successfully'
                ]);
            }

            return response()->json([
                'success' => true,
                'message' => 'ASIN details saved successfully'
            ]);

        } catch (\Exception $e) {
            Log::error('Error updating ASIN details: ' . $e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'An error occurred while updating ASIN details',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Update default dimensions and weight
     */
    public function updateDefaultDimensions(Request $request)
    {
        try {
            $validated = $request->validate([
                'asin' => 'required|string',
                'def_length' => 'nullable|numeric|min:0',
                'def_width' => 'nullable|numeric|min:0',
                'def_height' => 'nullable|numeric|min:0',
                'def_weight' => 'nullable|numeric|min:0',
                'def_weight_unit' => 'nullable|string|max:10'
            ]);

            // Check if ASIN exists
            $asin = DB::table($this->asinTable)
                ->where('ASIN', $validated['asin'])
                ->first();

            if (!$asin) {
                return response()->json([
                    'success' => false,
                    'message' => 'ASIN not found'
                ], 404);
            }

            // Update default dimensions
            $updateData = [
                'white_length' => $validated['def_length'],
                'white_width' => $validated['def_width'],
                'white_height' => $validated['def_height'],
                'white_value' => $validated['def_weight'],
                'white_unit' => $validated['def_weight_unit']
            ];

            $updated = DB::table($this->asinTable)
                ->where('ASIN', $validated['asin'])
                ->update($updateData);

            if ($updated !== false) {
                Log::info("Default dimensions updated for: {$validated['asin']}");

                return response()->json([
                    'success' => true,
                    'message' => 'Default dimensions updated successfully'
                ]);
            }

            return response()->json([
                'success' => true,
                'message' => 'Default dimensions saved successfully'
            ]);

        } catch (\Exception $e) {
            Log::error('Error updating default dimensions: ' . $e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'An error occurred while updating default dimensions',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Update related ASINs
     */
    public function updateRelatedAsins(Request $request)
    {
        try {
            $validated = $request->validate([
                'asin' => 'required|string',
                'parent_asin' => 'nullable|string|max:20',
                'cousin_asin' => 'nullable|string|max:20',
                'upgrade_asin' => 'nullable|string|max:20',
                'grand_asin' => 'nullable|string|max:20'
            ]);

            // Check if ASIN exists
            $asin = DB::table($this->asinTable)
                ->where('ASIN', $validated['asin'])
                ->first();

            if (!$asin) {
                return response()->json([
                    'success' => false,
                    'message' => 'ASIN not found'
                ], 404);
            }

            // Update related ASINs
            $updateData = [
                'ParentAsin' => $validated['parent_asin'],
                'CousinASIN' => $validated['cousin_asin'],
                'UpgradeASIN' => $validated['upgrade_asin'],
                'GrandASIN' => $validated['grand_asin']
            ];

            $updated = DB::table($this->asinTable)
                ->where('ASIN', $validated['asin'])
                ->update($updateData);

            if ($updated !== false) {
                Log::info("Related ASINs updated for: {$validated['asin']}");

                return response()->json([
                    'success' => true,
                    'message' => 'Related ASINs updated successfully'
                ]);
            }

            return response()->json([
                'success' => true,
                'message' => 'Related ASINs saved successfully'
            ]);

        } catch (\Exception $e) {
            Log::error('Error updating related ASINs: ' . $e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'An error occurred while updating related ASINs',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Upload instruction card image - Updated to support cards 1, 2, and 3
     */
    public function uploadInstructionCard(Request $request)
    {
        try {
            $validated = $request->validate([
                'instruction_card' => 'required|image|mimes:jpeg,png,jpg,gif|max:5120', // 5MB max
                'asin' => 'required|string',
                'card_slot' => 'required|in:1,2,3' // Updated to include card 3
            ]);

            // Check if ASIN exists
            $asin = DB::table($this->asinTable)
                ->where('ASIN', $validated['asin'])
                ->first();

            if (!$asin) {
                return response()->json([
                    'success' => false,
                    'message' => 'ASIN not found'
                ], 404);
            }

            $file = $request->file('instruction_card');
            $asinCode = $validated['asin'];
            $cardSlot = $validated['card_slot'];

            // Create instruction cards directory if it doesn't exist
            $uploadPath = public_path('images/instructioncard');
            if (!file_exists($uploadPath)) {
                mkdir($uploadPath, 0755, true);
            }

            // Generate filename: {ASIN}_card{slot}.{extension}
            $extension = $file->getClientOriginalExtension();
            $filename = $asinCode . '_card' . $cardSlot . '.' . $extension;

            // Remove old instruction card if exists (different extensions) for this slot
            $oldFiles = glob($uploadPath . '/' . $asinCode . '_card' . $cardSlot . '.*');
            foreach ($oldFiles as $oldFile) {
                if (file_exists($oldFile)) {
                    unlink($oldFile);
                }
            }

            // Move file to destination
            if ($file->move($uploadPath, $filename)) {
                $relativePath = 'images/instructioncard/' . $filename;
                $fileUrl = url($relativePath);

                // Update database with just the filename - Updated to handle card 3
                $columnName = match ($cardSlot) {
                    '1' => 'instructioncard',
                    '2' => 'instructioncard2',
                    '3' => 'instructioncard3', // Added card 3 support
                    default => 'instructioncard'
                };

                DB::table($this->asinTable)
                    ->where('ASIN', $asinCode)
                    ->update([
                        $columnName => $filename // Store only filename
                    ]);

                Log::info("Instruction card {$cardSlot} uploaded for ASIN: {$asinCode}");

                return response()->json([
                    'success' => true,
                    'message' => 'Instruction card uploaded successfully',
                    'file_url' => $fileUrl,
                    'filename' => $filename,
                    'card_slot' => $cardSlot,
                    'relative_path' => $relativePath
                ]);
            }

            return response()->json([
                'success' => false,
                'message' => 'Failed to upload file'
            ], 500);

        } catch (\Exception $e) {
            Log::error('Error uploading instruction card: ' . $e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'An error occurred while uploading instruction card',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Upload user manual PDF
     */
    public function uploadUserManual(Request $request)
    {
        try {
            $validated = $request->validate([
                'user_manual' => 'required|file|mimes:pdf|max:10240', // 10MB max for PDF
                'asin' => 'required|string'
            ]);

            // Check if ASIN exists
            $asin = DB::table($this->asinTable)
                ->where('ASIN', $validated['asin'])
                ->first();

            if (!$asin) {
                return response()->json([
                    'success' => false,
                    'message' => 'ASIN not found'
                ], 404);
            }

            $file = $request->file('user_manual');
            $asinCode = $validated['asin'];

            // Create user manual directory if it doesn't exist
            $uploadPath = public_path('images/usermanual');
            if (!file_exists($uploadPath)) {
                mkdir($uploadPath, 0755, true);
            }

            // Generate filename: {ASIN}.pdf
            $filename = $asinCode . '.pdf';

            // Remove old user manual if exists
            $oldFile = $uploadPath . '/' . $filename;
            if (file_exists($oldFile)) {
                unlink($oldFile);
            }

            // Move file to destination
            if ($file->move($uploadPath, $filename)) {
                $relativePath = 'images/usermanual/' . $filename;
                $fileUrl = url($relativePath);

                // Update database with just the filename
                DB::table($this->asinTable)
                    ->where('ASIN', $asinCode)
                    ->update([
                        'usermanuallink' => $filename // Store only filename
                    ]);

                Log::info("User manual uploaded for ASIN: {$asinCode}");

                return response()->json([
                    'success' => true,
                    'message' => 'User manual uploaded successfully',
                    'file_url' => $fileUrl,
                    'filename' => $filename,
                    'relative_path' => $relativePath
                ]);
            }

            return response()->json([
                'success' => false,
                'message' => 'Failed to upload user manual'
            ], 500);

        } catch (\Exception $e) {
            Log::error('Error uploading user manual: ' . $e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'An error occurred while uploading user manual',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Upload ASIN main image
     */
    public function uploadAsinImage(Request $request)
    {
        try {
            $validated = $request->validate([
                'asin_image' => 'required|image|mimes:jpeg,png,jpg,gif|max:5120', // 5MB max
                'asin' => 'required|string'
            ]);

            // Check if ASIN exists
            $asin = DB::table($this->asinTable)
                ->where('ASIN', $validated['asin'])
                ->first();

            if (!$asin) {
                return response()->json([
                    'success' => false,
                    'message' => 'ASIN not found'
                ], 404);
            }

            $file = $request->file('asin_image');
            $asinCode = $validated['asin'];

            // Create ASIN image directory if it doesn't exist
            $uploadPath = public_path('images/asinimg');
            if (!file_exists($uploadPath)) {
                mkdir($uploadPath, 0755, true);
            }

            // Generate filename: {ASIN}_0.{extension}
            $extension = $file->getClientOriginalExtension();
            $filename = $asinCode . '_0.' . $extension;

            // Remove old ASIN images if exists (different extensions)
            $oldFiles = glob($uploadPath . '/' . $asinCode . '_0.*');
            foreach ($oldFiles as $oldFile) {
                if (file_exists($oldFile)) {
                    unlink($oldFile);
                }
            }

            // Move file to destination
            if ($file->move($uploadPath, $filename)) {
                $relativePath = 'images/asinimg/' . $filename;
                $fileUrl = url($relativePath);

                // Update database with just the filename
                DB::table($this->asinTable)
                    ->where('ASIN', $asinCode)
                    ->update([
                        'asinimg' => $filename // Store only filename
                    ]);

                Log::info("ASIN image uploaded for ASIN: {$asinCode}");

                return response()->json([
                    'success' => true,
                    'message' => 'ASIN image uploaded successfully',
                    'file_url' => $fileUrl,
                    'filename' => $filename,
                    'relative_path' => $relativePath
                ]);
            }

            return response()->json([
                'success' => false,
                'message' => 'Failed to upload ASIN image'
            ], 500);

        } catch (\Exception $e) {
            Log::error('Error uploading ASIN image: ' . $e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'An error occurred while uploading ASIN image',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Upload ASIN vector image
     */
    public function uploadAsinVectorImage(Request $request)
    {
        try {
            $validated = $request->validate([
                'vector_image' => 'required|image|mimes:jpeg,png,jpg,gif|max:5120', // 5MB max
                'asin' => 'required|string'
            ]);

            // Check if ASIN exists
            $asin = DB::table($this->asinTable)
                ->where('ASIN', $validated['asin'])
                ->first();

            if (!$asin) {
                return response()->json([
                    'success' => false,
                    'message' => 'ASIN not found'
                ], 404);
            }

            $file = $request->file('vector_image');
            $asinCode = $validated['asin'];

            // Create vector image directory if it doesn't exist
            $uploadPath = public_path('images/asinvectorsimg');
            if (!file_exists($uploadPath)) {
                mkdir($uploadPath, 0755, true);
            }

            // Generate filename: {ASIN}.{extension}
            $extension = $file->getClientOriginalExtension();
            $filename = $asinCode . '.' . $extension;

            // Remove old vector images if exists (different extensions)
            $oldFiles = glob($uploadPath . '/' . $asinCode . '.*');
            foreach ($oldFiles as $oldFile) {
                if (file_exists($oldFile)) {
                    unlink($oldFile);
                }
            }

            // Move file to destination
            if ($file->move($uploadPath, $filename)) {
                $relativePath = 'images/asinvectorsimg/' . $filename;
                $fileUrl = url($relativePath);

                // Update database with the relative path
                DB::table($this->asinTable)
                    ->where('ASIN', $asinCode)
                    ->update([
                        'vectorimage' => $relativePath
                    ]);

                Log::info("Vector image uploaded for ASIN: {$asinCode}");

                return response()->json([
                    'success' => true,
                    'message' => 'Vector image uploaded successfully',
                    'file_url' => $fileUrl,
                    'filename' => $filename,
                    'relative_path' => $relativePath
                ]);
            }

            return response()->json([
                'success' => false,
                'message' => 'Failed to upload vector image'
            ], 500);

        } catch (\Exception $e) {
            Log::error('Error uploading vector image: ' . $e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'An error occurred while uploading vector image',
                'error' => $e->getMessage()
            ], 500);
        }
    }


    public function bulkUploadInstructionCards(Request $request)
    {
        try {
            $validated = $request->validate([
                'asin_list' => 'required|string',
                'instruction_cards' => 'array|min:1|max:3',
                'instruction_cards.*' => 'required|image|mimes:jpeg,png,jpg,gif|max:5120', // 5MB max per image
            ]);

            // Parse ASIN list
            $asinList = array_filter(
                array_map(
                    fn($asin) => trim(strtoupper($asin)),
                    explode(',', $validated['asin_list'])
                )
            );

            if (empty($asinList)) {
                return response()->json([
                    'success' => false,
                    'message' => 'No valid ASINs provided'
                ], 400);
            }

            if (count($asinList) > 50) {
                return response()->json([
                    'success' => false,
                    'message' => 'Maximum 50 ASINs allowed per bulk upload'
                ], 400);
            }

            // Check which ASINs exist in database
            $existingAsins = DB::table($this->asinTable)
                ->whereIn('ASIN', $asinList)
                ->pluck('ASIN')
                ->toArray();

            $nonExistentAsins = array_diff($asinList, $existingAsins);

            $results = [
                'success' => [],
                'failed' => [],
                'skipped' => $nonExistentAsins
            ];

            // Create instruction cards directory if it doesn't exist
            $uploadPath = public_path('images/instructioncard');
            if (!file_exists($uploadPath)) {
                mkdir($uploadPath, 0755, true);
            }

            // Process uploads for existing ASINs
            foreach ($existingAsins as $asin) {
                $asinResults = [];
                $asinErrors = [];

                // Process each uploaded card
                foreach ($request->file('instruction_cards', []) as $cardSlot => $file) {
                    $cardNumber = $cardSlot + 1; // Convert 0,1,2 to 1,2,3

                    try {
                        // Generate filename: {ASIN}_card{slot}.{extension}
                        $extension = $file->getClientOriginalExtension();
                        $filename = $asin . '_card' . $cardNumber . '.' . $extension;

                        // Remove old instruction card if exists (different extensions) for this slot
                        $oldFiles = glob($uploadPath . '/' . $asin . '_card' . $cardNumber . '.*');
                        foreach ($oldFiles as $oldFile) {
                            if (file_exists($oldFile)) {
                                unlink($oldFile);
                            }
                        }

                        // Copy file to destination (we need to copy since multiple ASINs use same file)
                        if (copy($file->getPathname(), $uploadPath . '/' . $filename)) {
                            $relativePath = 'images/instructioncard/' . $filename;

                            // Update database
                            $columnName = match ($cardNumber) {
                                1 => 'instructioncard',
                                2 => 'instructioncard2',
                                3 => 'instructioncard3',
                                default => 'instructioncard'
                            };

                            DB::table($this->asinTable)
                                ->where('ASIN', $asin)
                                ->update([
                                    $columnName => $filename
                                ]);

                            $asinResults[] = "Card {$cardNumber}";
                        } else {
                            $asinErrors[] = "Failed to upload Card {$cardNumber}";
                        }
                    } catch (\Exception $e) {
                        $asinErrors[] = "Card {$cardNumber}: " . $e->getMessage();
                    }
                }

                // Record results for this ASIN
                if (!empty($asinResults)) {
                    $results['success'][] = [
                        'asin' => $asin,
                        'cards' => implode(', ', $asinResults)
                    ];
                }

                if (!empty($asinErrors)) {
                    $results['failed'][] = [
                        'asin' => $asin,
                        'errors' => $asinErrors
                    ];
                }
            }

            Log::info('Bulk instruction card upload completed', [
                'total_asins' => count($asinList),
                'successful' => count($results['success']),
                'failed' => count($results['failed']),
                'skipped' => count($results['skipped'])
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Bulk upload completed',
                'results' => $results,
                'summary' => [
                    'total_asins' => count($asinList),
                    'successful' => count($results['success']),
                    'failed' => count($results['failed']),
                    'skipped' => count($results['skipped'])
                ]
            ]);

        } catch (\Exception $e) {
            Log::error('Error in bulk instruction card upload: ' . $e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'An error occurred during bulk upload',
                'error' => $e->getMessage()
            ], 500);
        }
    }


   /**
     * Update Color for a specific ASIN
     */
    public function updateColor(Request $request)
    {
        try {
            $validated = $request->validate([
                'asin' => 'required|string',
                'color' => 'nullable|string|in:Black,White,Gray,Blue,Green,Red,Yellow'
            ]);

            $asin = DB::table($this->asinTable)
                ->where('ASIN', $validated['asin'])
                ->first();

            if (!$asin) {
                return response()->json([
                    'success' => false,
                    'message' => 'ASIN not found'
                ], 404);
            }

            DB::table($this->asinTable)
                ->where('ASIN', $validated['asin'])
                ->update(['color' => $validated['color']]);

            Log::info("Color updated for: {$validated['asin']}", [
                'color' => $validated['color']
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Color updated successfully'
            ]);

        } catch (\Exception $e) {
            Log::error('Error updating Color: ' . $e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'An error occurred while updating Color',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Update Quantity Inside for a specific ASIN
     */
    public function updateQuantityInside(Request $request)
    {
        try {
            $validated = $request->validate([
                'asin' => 'required|string',
                'quantity_inside' => 'nullable|integer|min:1|max:4'
            ]);

            $asin = DB::table($this->asinTable)
                ->where('ASIN', $validated['asin'])
                ->first();

            if (!$asin) {
                return response()->json([
                    'success' => false,
                    'message' => 'ASIN not found'
                ], 404);
            }

            DB::table($this->asinTable)
                ->where('ASIN', $validated['asin'])
                ->update(['QuantityInside' => $validated['quantity_inside']]);

            Log::info("Quantity Inside updated for: {$validated['asin']}", [
                'quantity' => $validated['quantity_inside']
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Quantity Inside updated successfully'
            ]);

        } catch (\Exception $e) {
            Log::error('Error updating Quantity Inside: ' . $e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'An error occurred while updating Quantity Inside',
                'error' => $e->getMessage()
            ], 500);
        }
    }



}