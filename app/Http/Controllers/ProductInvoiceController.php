<?php

namespace App\Http\Controllers;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB; 

class ProductInvoiceController extends BasetablesController
{
   public function index(Request $request)
        {
            $validated = $request->validate([
                'productIds' => 'required|array',
            ]);

            $products = DB::table('tblproduct as p')
            ->join('tblsuppliers as s', 's.name', '=', 'p.seller')
            ->whereIn('p.ProductID', $validated['productIds'])
            ->select(
                'p.ProductTitle',
                'p.price',
                'p.tax',
                's.name',
                's.contact',
                's.address1',
                's.address2',
                's.websiteAddress',
                's.email',
                DB::raw('SUM(p.quantity) as quantity'),
                DB::raw('p.price * SUM(p.quantity) as totalPrice'),
            )
            ->groupBy(
                'p.ProductTitle',
                'p.price',
                'p.tax',
                'p.seller', // important
                's.name',
                's.contact',
                's.address1',
                's.address2',
                's.websiteAddress',
                's.email',
            )
            ->get();

            // Check for missing supplier contact information
            $suppliersWithMissingFields = [];

            foreach ($products as $product) {
                $missingFields = [];

                if (empty($product->contact))        $missingFields[] = 'contact';
                if (empty($product->address1))       $missingFields[] = 'address1';
                if (empty($product->address2))       $missingFields[] = 'address2';
                if (empty($product->email))          $missingFields[] = 'email';
                if (empty($product->websiteAddress)) $missingFields[] = 'website address';

                if (!empty($missingFields)) {
                    $suppliersWithMissingFields[] = $product->name;
                }
            }

            if (!empty($suppliersWithMissingFields)) {
                $sellersList = implode(', ', array_unique($suppliersWithMissingFields));
                return response()->json([
                    'message' => "{$sellersList} has missing information. Verify it in the Suppliers List"
                ], 400);
            }

            // Group products by supplier name
        $grouped = $products->groupBy('name');

        $result = $grouped->map(function ($supplierProducts) {
            $supplier = $supplierProducts->first();

            return [
                'name'           => $supplier->name,
                'contact'        => $supplier->contact,
                'address1'       => $supplier->address1,
                'address2'       => $supplier->address2,
                'websiteAddress' => $supplier->websiteAddress,
                'email'          => $supplier->email,
                'products'       => $supplierProducts->map(fn($p) => [
                    'ProductTitle' => $p->ProductTitle,
                    'price'        => $p->price,
                    'quantity'     => $p->quantity,
                    'tax'          => $p->tax,
                    'totalPrice'   => $p->totalPrice,
                ])->values(),
            ];
        })->values();

        return response()->json($result);
        }
}