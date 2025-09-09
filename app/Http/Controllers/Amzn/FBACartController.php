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

    $items = DB::table('tblfbacart as cart')
        ->join('tblproduct as p', 'cart.ProdID', '=', 'p.ProductID')
        // FNSKU join: use product.FNSKUviewer -> fnsku.FNSKU
        ->leftJoin('tblfnsku as f', 'p.FNSKUviewer', '=', 'f.FNSKU')
        // ASIN join: use fnsku.ASIN -> asin.ASIN
        ->leftJoin('tblasin as a', 'f.ASIN', '=', 'a.ASIN')
        ->select(
            'cart.ID',
            'cart.ProdID',
            'cart.DateInserted',
            'cart.processby',
            'p.serialnumber',
            // From tblfnsku
            'f.FNSKU as FNSKUviewer',
            'f.MSKU as MSKUviewer',
            'f.ASIN as ASINviewer',
            // From tblasin (internal = title)
            DB::raw('a.internal as ProductTitle')
        )
        ->where('cart.processby', $processby)
        ->orderByDesc('cart.DateInserted')
        ->get();

    return response()->json($items);
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

    public function commitCart(Request $request)
    {
        $user = session('user_name');
        if (!$user) {
            return response()->json(['error' => 'Session expired'], 401);
        }

        $store = $request->input('store');
        $storeShort = $store === 'Renovar Tech' ? 'RT' : 'AR';

        // Generate initial values
        $dateshipped = now();
        $shipmentID = 'FBA' . strtoupper(Str::random(10));

        // Retry until shipmentID + dateshipped is unique
        $attempts = 0;
        while (
            DB::table('tblfbashipmenthistory')
            ->where('shipmentID', $shipmentID)
            ->where('dateshipped', $dateshipped)
            ->exists()
        ) {
            $attempts++;
            if ($attempts > 10) {
                return response()->json(['error' => 'Could not generate unique shipment ID'], 500);
            }

            $shipmentID = 'FBA' . strtoupper(Str::random(10));
            $dateshipped = $dateshipped->addSeconds(10); // shift by 10 seconds
        }

        // Fetch all cart items by this user
        $cartItems = DB::table('tblfbacart AS cart')
            ->join('tblproduct AS p', 'cart.ProdID', '=', 'p.ProductID')
            ->where('cart.processby', $user)
            ->select(
                'p.ProductTitle',
                'p.ASINviewer',
                'p.FNSKUviewer',
                'p.MSKUviewer',
                'p.serialnumber',
                'p.warehouselocation'
            )
            ->get();

        if ($cartItems->isEmpty()) {
            return response()->json(['message' => 'No items in cart.'], 400);
        }

        foreach ($cartItems as $item) {
            DB::table('tblfbashipmenthistory')->insert([
                'ProductName' => $item->ProductTitle,
                'ASIN' => $item->ASINviewer,
                'FNSKU' => $item->FNSKUviewer,
                'MSKU' => $item->MSKUviewer,
                'dateshipped' => $dateshipped,
                'shipmentID' => $shipmentID,
                'Location' => $item->warehouselocation,
                'Serialnumber' => $item->serialnumber,
                'store' => $storeShort,
                'groupid' => null,
                'processby' => $user,
                'row_show' => 1,
                'PrepOwner' => 'SELLER'
            ]);
        }

        // Clear cart
        DB::table('tblfbacart')->where('processby', $user)->delete();

        return response()->json([
            'message' => 'Cart committed to shipment history.',
            'shipmentID' => $shipmentID,
            'date_shipped' => $dateshipped
        ]);
    }

    public function commitCart(Request $request)
{
    $user = session('user_name');
    if (!$user) {
        return response()->json(['error' => 'Session expired'], 401);
    }

    $store = $request->input('store');
    $storeShort 

    // Generate unique (shipmentID, dateshipped) pair
    $dateshipped = now();
    $shipmentID = 'FBA' . strtoupper(Str::random(10));
    $attempts = 0;

    while (
        DB::table('tblfbashipmenthistory')
            ->where('shipmentID', $shipmentID)
            ->where('dateshipped', $dateshipped)
            ->exists()
    ) {
        if (++$attempts > 10) {
            return response()->json(['error' => 'Could not generate unique shipment ID'], 500);
        }
        $shipmentID = 'FBA' . strtoupper(Str::random(10));
        $dateshipped = $dateshipped->copy()->addSeconds(10);
    }

    // Pull cart with joins:
    // p.FNSKUviewer -> f.FNSKU -> (FNSKU, MSKU, ASIN)
    // f.ASIN -> a.ASIN -> a.internal (Title)
    $cartItems = DB::table('tblfbacart AS cart')
        ->join('tblproduct AS p', 'cart.ProdID', '=', 'p.ProductID')
        ->leftJoin('tblfnsku AS f', 'p.FNSKUviewer', '=', 'f.FNSKU')
        ->leftJoin('tblasin AS a', 'f.ASIN', '=', 'a.ASIN')
        ->where('cart.processby', $user)
        ->select(
            'p.ProductTitle',
            'p.serialnumber',
            'p.warehouselocation',
            'f.FNSKU',
            'f.MSKU',
            'f.ASIN as ASIN_from_f',
            'a.ASIN as ASIN_from_a',
            'a.internal as Title'
        )
        ->get();

    if ($cartItems->isEmpty()) {
        return response()->json(['message' => 'No items in cart.'], 400);
    }

    DB::beginTransaction();
    try {
        foreach ($cartItems as $item) {
            DB::table('tblfbashipmenthistory')->insert([
                // Prefer a.internal as the product title; fallback to legacy p.ProductTitle
                'ProductName'  => $item->Title ?? $item->ProductTitle ?? '',
                // Prefer ASIN from tblasin; fallback to fnsku.ASIN
                'ASIN'         => $item->ASIN_from_a ?? $item->ASIN_from_f ?? null,
                'FNSKU'        => $item->FNSKU,
                'MSKU'         => $item->MSKU,
                'dateshipped'  => $dateshipped,
                'shipmentID'   => $shipmentID,
                'Location'     => $item->warehouselocation,
                'Serialnumber' => $item->serialnumber,
                'store'        => $store,
                'groupid'      => null,
                'processby'    => $user,
                'row_show'     => 1,
                'PrepOwner'    => 'SELLER',
            ]);
        }

        // Clear cart for this user after successful inserts
        DB::table('tblfbacart')->where('processby', $user)->delete();

        DB::commit();
    } catch (\Throwable $e) {
        DB::rollBack();
        return response()->json(['error' => 'Failed to commit cart', 'detail' => $e->getMessage()], 500);
    }

    return response()->json([
        'message' => 'Cart committed to shipment history.',
        'shipmentID' => $shipmentID,
        'date_shipped' => $dateshipped
    ]);
}
}
