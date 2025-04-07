<?php

namespace App\Http\Controllers\Amzn;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Carbon\Carbon;

require base_path('app/Helpers/aws_helpers.php');

class FBACartController extends Controller
{
    public function addToCart(Request $request)
    {
        $processby = session('user_name');
        if (!$processby) {
            return response()->json(['error' => 'User session expired or missing.'], 401);
        }

        $request->validate([
            'ProdID' => 'required|integer',
        ]);

        // Prevent duplicates
        $exists = DB::table('tblfbacart')
            ->where('ProdID', $request->ProdID)
            ->where('processby', $processby)
            ->first();

        if ($exists) {
            return response()->json(['message' => 'Item already in cart.'], 409);
        }

        DB::table('tblfbacart')->insert([
            'ProdID' => $request->ProdID,
            'DateInserted' => now(),
            'processby' => $processby
        ]);

        return response()->json(['message' => 'Item added to cart.']);
    }

    public function list(Request $request)
    {
        $processby = session('user_name');
        if (!$processby) {
            return response()->json(['error' => 'User session expired or missing.'], 401);
        }

        $items = DB::table('tblfbacart AS cart')
            ->join('tblproduct AS p', 'cart.ProdID', '=', 'p.ProductID')
            ->select(
                'cart.ID',
                'cart.ProdID',
                'cart.DateInserted',
                'cart.processby',
                'p.ProductTitle',
                'p.ASINviewer',
                'p.MSKUviewer',
                'p.FNSKUviewer',
                'p.serialnumber'
            )
            ->where('cart.processby', $processby)
            ->orderByDesc('cart.DateInserted')
            ->get();

        return response()->json($items);
    }

    public function getOrCreateCart(Request $request)
    {
        $processby = session('user_name');
        if (!$processby) {
            return response()->json(['error' => 'User session expired or missing.'], 401);
        }
    
        $existingCart = DB::table('tblfbacart')
            ->where('processby', $processby)
            ->orderByDesc('DateInserted')
            ->first();
    
        if ($existingCart) {
            return response()->json(['CartID' => $existingCart->CartID]);
        }
    
        $newCartID = 'FBA' . strtoupper(Str::random(10));
    
        return response()->json(['CartID' => $newCartID]);
    }    

    public function removeFromCart(Request $request)
    {
        $request->validate([
            'ProdID' => 'required|integer'
        ]);

        $processby = session('user_name');
        if (!$processby) {
            return response()->json(['error' => 'User session expired or missing.'], 401);
        }

        $deleted = DB::table('tblfbacart')
            ->where('ProdID', $request->ProdID)
            ->where('processby', $processby)
            ->delete();

        if ($deleted) {
            return response()->json(['message' => 'Item removed from cart.']);
        } else {
            return response()->json(['message' => 'Item not found in your cart.'], 404);
        }
    }

}
