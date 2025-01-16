<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Store;

class AddStoreController extends Controller
{
    public function store(Request $request)
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
}
