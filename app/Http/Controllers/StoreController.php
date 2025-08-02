<?php
namespace App\Http\Controllers;

use App\Models\Store; // Assuming Store model is used to manage stores
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use App\Services\UserLogService;

class StoreController extends Controller
{
    protected $userLogService;

    public function __construct(UserLogService $userLogService)
    {
        $this->userLogService = $userLogService;
    }

    // Method to fetch stores
    public function getStores()
    {
        $stores = Store::all(); // Get all stores from the database
        return response()->json(['stores' => $stores]); // Return stores in JSON format
    }


    public function addstore(Request $request)
    {
        $request->validate([
            'storename' => 'required|string|max:255',
            'Strabbreviation' => 'nullable|string|max:10',
        ]);

        $storename = $request->storename;
        $storeAbbreviation = $request->Strabbreviation;

        // Sanitize the storename for column naming
        $sanitizedStorename = str_replace(' ', '_', $storename);

        // Insert into database using query builder
        $storeId = DB::table('stores')->insertGetId([
            'storename' => $storename,
            'abbreviation' => $storeAbbreviation,
            'owner_id' => auth()->id(),
            'client_id' => '',
            'client_secret' => '',
            'refresh_token' => '',
            'MerchantID' => '',
            'MarketplaceID' => '',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // Log the action
        $this->userLogService->log('Store Added: ' . $storename);

        // Dynamically add a column in tbluser if it doesn't exist
        if (!Schema::hasColumn('tbluser', 'store_' . $sanitizedStorename)) {
            Schema::table('tbluser', function (Blueprint $table) use ($sanitizedStorename) {
                $table->boolean('store_' . $sanitizedStorename)->default(false);
            });
        }

        // Retrieve the inserted store record
        $store = DB::table('stores')->where('id', $storeId)->first();

        return response()->json([
            'success' => true,
            'store' => $store
        ]);
    }

    public function updateStore(Request $request, $id)
    {
        try {
            // Find the store by ID
            $store = Store::where('store_id', $id)->first();

            if (!$store) {
                return response()->json(['success' => false, 'message' => 'Store not found.']);
            }

            // Ensure 'storename' is provided and not empty
            if (empty($request->storename)) {
                return response()->json(['success' => false, 'message' => 'Store name is required.']);
            }

            // Check if the updated store name already exists (excluding the current store)
            $existingStore = Store::where('storename', $request->storename)
                ->where('store_id', '!=', $id) // Exclude the current store
                ->first();

            if ($existingStore) {
                return response()->json(['success' => false, 'message' => 'The store name already exists in the list.']);
            }

            // Prepare the data to update, ensuring only 'storename' is required
            $updatedData = [
                'storename' => $request->storename
            ];

            // Update other fields only if they are provided, otherwise set them to null
            $updatedData['client_id'] = !empty($request->client_id) ? $request->client_id : null;
            $updatedData['client_secret'] = !empty($request->client_secret) ? $request->client_secret : null;
            $updatedData['refresh_token'] = !empty($request->refresh_token) ? $request->refresh_token : null;
            $updatedData['MerchantID'] = !empty($request->MerchantID) ? $request->MerchantID : null;
            $updatedData['Marketplace'] = !empty($request->Marketplace) ? $request->Marketplace : null;
            $updatedData['MarketplaceID'] = !empty($request->MarketplaceID) ? $request->MarketplaceID : null;

            // Update the store with the new data
            $store->update($updatedData);

            // Log after successful save
            $this->userLogService->log('Store Updated: ' . $request->storename);

            return response()->json(['success' => true, 'message' => 'Store updated successfully.']);
        } catch (\Exception $e) {
            Log::error('Error updating store: ' . $e->getMessage());
            return response()->json(['success' => false, 'message' => 'An error occurred while updating the store.']);
        }
    }

    public function getStoreID($id)
    {
        $store = Store::find($id);

        if (!$store) {
            return response()->json(['error' => 'Store not found'], 404);
        }

        return response()->json(['store' => $store], 200);
    }

    // Delete Store
    public function delete($id)
    {
        // Find the store by ID
        $store = Store::findOrFail($id);

        // Get the sanitized store name (in case the column name has underscores)
        $sanitizedStoreName = str_replace(' ', '_', $store->storename);

        // Drop the corresponding column in tbluser
        if (Schema::hasColumn('tbluser', 'store_' . $sanitizedStoreName)) {
            Schema::table('tbluser', function (Blueprint $table) use ($sanitizedStoreName) {
                $table->dropColumn('store_' . $sanitizedStoreName);
            });
        }

        // Delete the store
        $store->delete();

        // Log after successful save
        $this->userLogService->log('Store Deleted: ' . $sanitizedStoreName);

        // Return a success response
        return response()->json(['success' => true]);
    }
    public function fetchMarketplaces()
    {
        $marketplaces = DB::table('tbldefinitions')
            ->where('category', 'Marketplace')
            ->select('value', 'name') // Fetch both 'value' and 'name' fields
            ->get();

        return response()->json($marketplaces);
    }

    public function fetchMarketplacestblstores(Request $request)
    {
        $store = $request->input('store');

        // Query tblstores to get marketplace details for the given store
        $marketplaces = DB::table('tblstores')
            ->where('storename', $store)
            ->get(['Marketplace as marketplace', 'MarketplaceID as marketplace_id']);

        // Split the marketplaces and marketplace IDs into individual key-value pairs
        $marketplaceData = [];
        foreach ($marketplaces as $marketplace) {
            $names = explode(',', $marketplace->marketplace); // Split by comma
            $ids = explode(',', $marketplace->marketplace_id); // Split by comma

            // Combine each name with its corresponding ID
            for ($i = 0; $i < count($names); $i++) {
                $name = trim($names[$i]); // Remove extra spaces
                $id = $ids[$i] ?? null; // Use corresponding ID or null if not available
                if ($id) {
                    $marketplaceData[$name] = $id;
                }
            }
        }

        return response()->json([
            'success' => true,
            'marketplaces' => $marketplaceData, // Return the structured key-value pairs
        ]);
    }



}