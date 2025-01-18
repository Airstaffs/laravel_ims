<?php
namespace App\Http\Controllers;

use App\Models\Store; // Assuming Store model is used to manage stores
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;

class StoreController extends Controller
{
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
            ]);
    
            $store = new Store();
            $store->storename = $request->storename;
            $store->owner_id = auth()->id(); // Assuming the logged-in user is the owner
            $store->client_id = ''; // Add as necessary
            $store->client_secret = ''; // Add as necessary
            $store->refresh_token = ''; // Add as necessary
            $store->MerchantID = ''; // Add as necessary
            $store->MarketplaceID = ''; // Add as necessary
            $store->save();
    
            // Return the store data in the response
            return response()->json([
                'success' => true,
                'store' => $store // Send the store data back
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
                $updatedData['client_id'] = !empty($request->client_id ) ? $request->client_id : null;
                $updatedData['client_secret'] = !empty($request->client_secret) ? $request->client_secret : null;
                $updatedData['refresh_token'] = !empty($request->refresh_token) ? $request->refresh_token : null;
                $updatedData['MerchantID'] = !empty($request->MerchantID) ? $request->MerchantID : null;
                $updatedData['Marketplace'] = !empty($request->Marketplace) ? $request->Marketplace : null;
                $updatedData['MarketplaceID'] = !empty($request->MarketplaceID) ? $request->MarketplaceID : null;
        
                // Update the store with the new data
                $store->update($updatedData);
        
                return response()->json(['success' => true, 'message' => 'Store updated successfully.']);
            } catch (\Exception $e) {
                Log::error('Error updating store: ' . $e->getMessage());
                return response()->json(['success' => false, 'message' => 'An error occurred while updating the store.']);
            }
        }
        
         public function getStoreID($id) {
                $store = Store::find($id);
            
                if (!$store) {
                    return response()->json(['error' => 'Store not found'], 404);
                }
            
                return response()->json(['store' => $store], 200);
            }
                       
        // Delete Store
        public function delete($id)
        {
            $store = Store::findOrFail($id);
            $store->delete();
        
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
                ->where('store_name', $store)
                ->get(['marketplace as marketplace', 'marketplace_id as marketplace_id']);
            
            // Combine the marketplaces into a key-value pair
            $marketplaceData = [];
            foreach ($marketplaces as $marketplace) {
                // Set the marketplace name as key and marketplace_id as value
                $marketplaceData[$marketplace->marketplace] = $marketplace->marketplace_id;
            }
        
            return response()->json([
                'success' => true,
                'marketplaces' => $marketplaceData, // Return key-value pairs
            ]);
        }
}