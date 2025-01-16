<?php
namespace App\Http\Controllers;

use App\Models\Store; // Assuming Store model is used to manage stores
use Illuminate\Http\Request;

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
            $store->ClientID = ''; // Add as necessary
            $store->clientsecret = ''; // Add as necessary
            $store->refreshtoken = ''; // Add as necessary
            $store->MerchantID = ''; // Add as necessary
            $store->MarketplaceID = ''; // Add as necessary
            $store->save();
    
            // Return the store data in the response
            return response()->json([
                'success' => true,
                'store' => $store // Send the store data back
            ]);
        }


        public function update(Request $request, $id)
        {
            $request->validate([
                'storename' => 'required|string|max:255',
            ]);
        
            $store = Store::findOrFail($id);
            $store->storename = $request->storename;
            $store->save();
        
            return response()->json(['success' => true, 'store' => $store]);
        }
        
        // Delete Store
        public function delete($id)
        {
            $store = Store::findOrFail($id);
            $store->delete();
        
            return response()->json(['success' => true]);
        }        
}