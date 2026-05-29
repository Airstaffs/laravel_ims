<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class SuppliesComponentsController extends BasetablesController
{
    const CATEGORIES = ['Components', 'Supplies', 'Office Equipment'];

    /**
     * Get paginated items from tblproduct only
     */
    public function index(Request $request)
    {
        try {
            $perPage = $request->input('per_page', 10);
            $page = $request->input('page', 1);
            $search = $request->input('search', '');
            $category = $request->input('category', '');

            $query = DB::table('tblproduct as p')
                ->leftJoin($this->capturedImagesTable . ' as img', 'p.ProductID', '=', 'img.ProductID')
                ->leftJoin('tblEbayOrderImages as ebayimgs', 'p.ProductID', '=', 'ebayimgs.ProductID')
                ->leftJoin('tblproduct_sid as ps', 'p.ProductID', '=', 'ps.product_id')
                ->leftJoin('tblsid as sid', 'ps.sid_id', '=', 'sid.id')
                ->select(
                    'p.ProductID',
                    'p.rtcounter',
                    'p.ProductTitle',
                    'p.quantity',
                    'p.orderdate',
                    'p.datedelivered',
                    'p.ProductModuleLoc',
                    'sid.sid_number',
                    'ebayimgs.img1',
                    'ebayimgs.img2',
                    'ebayimgs.img3',
                    'ebayimgs.img4',
                    'ebayimgs.img5',
                    'ebayimgs.img6',
                    'ebayimgs.img7',
                    'ebayimgs.img8',
                    'ebayimgs.img9',
                    'ebayimgs.img10',
                    'ebayimgs.img11',
                    'ebayimgs.img12',
                    'ebayimgs.img13',
                    'ebayimgs.img14',
                    'ebayimgs.img15',
                    'img.capturedimg1',
                    'img.capturedimg2',
                    'img.capturedimg3',
                    'img.capturedimg4',
                    'img.capturedimg5',
                    'img.capturedimg6',
                    'img.capturedimg7',
                    'img.capturedimg8',
                    'img.capturedimg9',
                    'img.capturedimg10',
                    'img.capturedimg11',
                    'img.capturedimg12',
                    'img.serialimg1',
                    'img.serialimg2',
                    'img.trackingimg1',
                    'img.trackingimg2'
                )
                ->whereIn('p.ProductModuleLoc', self::CATEGORIES);

            // Category filter
            if (!empty($category) && in_array($category, self::CATEGORIES)) {
                $query->where('p.ProductModuleLoc', $category);
            }

            // Search filter
            if (!empty($search)) {
                $query->where(function ($q) use ($search) {
                    $q->where('p.ProductID', 'LIKE', "%{$search}%")
                        ->orWhere('p.ProductTitle', 'LIKE', "%{$search}%")
                        ->orWhere('p.rtcounter', 'LIKE', "%{$search}%");
                });
            }

            $totalCount = $query->count();
            $totalPages = ceil($totalCount / $perPage);

            $rows = $query
                ->orderBy('p.orderdate', 'desc')
                ->skip(($page - 1) * $perPage)
                ->take($perPage)
                ->get();

            $formatted = $rows->map(function ($r) {
                // Build capturedImages object
                $capturedImages = [];

                for ($i = 1; $i <= 12; $i++) {
                    $key = "capturedimg{$i}";
                    if (!empty($r->$key) && $r->$key !== 'NULL') {
                        $capturedImages[$key] = $r->$key;
                    }
                }

                foreach (['serialimg1', 'serialimg2', 'trackingimg1', 'trackingimg2'] as $key) {
                    if (!empty($r->$key) && $r->$key !== 'NULL') {
                        $capturedImages[$key] = $r->$key;
                    }
                }

                // ── Thumbnail priority ───────────────────────────────────────
                // 1st: captured image  (path: /images/product_images/{company}/)
                // 2nd: eBay image      (path: /images/thumbnails/)
                if (!empty($capturedImages['capturedimg1'])) {
                    $thumbnail = $capturedImages['capturedimg1'];
                    $img1Source = 'captured';
                } elseif (!empty($r->img1)) {
                    $thumbnail = $r->img1;
                    $img1Source = 'ebay';
                } else {
                    $thumbnail = null;
                    $img1Source = null;
                }

                return [
                    'product_id' => $r->ProductID,
                    'rt_counter' => $r->rtcounter,
                    'product_title' => $r->ProductTitle ?? 'N/A',
                    'quantity' => $r->quantity,
                    'order_date' => $r->orderdate,
                    'delivered_date' => $r->datedelivered,
                    'category' => $r->ProductModuleLoc,
                    'sid_number' => $r->sid_number ?? null,
                    'company' => 'Airstaffs',
                    'img1' => $thumbnail,
                    'img1_source' => $img1Source,
                    'img2' => $r->img2 ?? null,
                    'img3' => $r->img3 ?? null,
                    'img4' => $r->img4 ?? null,
                    'img5' => $r->img5 ?? null,
                    'img6' => $r->img6 ?? null,
                    'img7' => $r->img7 ?? null,
                    'img8' => $r->img8 ?? null,
                    'img9' => $r->img9 ?? null,
                    'img10' => $r->img10 ?? null,
                    'img11' => $r->img11 ?? null,
                    'img12' => $r->img12 ?? null,
                    'img13' => $r->img13 ?? null,
                    'img14' => $r->img14 ?? null,
                    'img15' => $r->img15 ?? null,
                    'capturedImages' => !empty($capturedImages) ? $capturedImages : null,
                ];
            });

            return response()->json([
                'success' => true,
                'data' => $formatted,
                'total' => $totalCount,
                'per_page' => $perPage,
                'current_page' => $page,
                'last_page' => $totalPages,
            ]);

        } catch (\Exception $e) {
            Log::error('SuppliesComponents index error: ' . $e->getMessage());

            return response()->json(['success' => false, 'message' => 'Error fetching items', 'error' => $e->getMessage()], 500);
        }
    }

    /**
     * Get count statistics per category
     */
    public function getStats()
    {
        try {
            $byCategory = DB::table('tblproduct')
                ->whereIn('ProductModuleLoc', self::CATEGORIES)
                ->select('ProductModuleLoc', DB::raw('COUNT(*) as count'))
                ->groupBy('ProductModuleLoc')
                ->get()
                ->keyBy('ProductModuleLoc');

            $stats = [
                'total' => $byCategory->sum('count'),
                'components' => $byCategory->get('Components')?->count ?? 0,
                'supplies' => $byCategory->get('Supplies')?->count ?? 0,
                'office_equipment' => $byCategory->get('Office Equipment')?->count ?? 0,
            ];

            return response()->json(['success' => true, 'stats' => $stats]);

        } catch (\Exception $e) {
            Log::error('SuppliesComponents getStats error: ' . $e->getMessage());

            return response()->json(['success' => false, 'message' => 'Error fetching statistics'], 500);
        }
    }

    /**
     * Move a product to Labeling
     */
    public function moveToLabeling(Request $request)
    {
        $data = $request->validate([
            'product_id' => ['required', 'integer'],
        ]);

        $productId = (int) $data['product_id'];

        try {
            $product = DB::table('tblproduct')
                ->where('ProductID', $productId)
                ->whereIn('ProductModuleLoc', self::CATEGORIES)
                ->first();

            if (!$product) {
                return response()->json([
                    'success' => false,
                    'message' => "Product {$productId} not found or not in Supplies/Components/Office Equipment.",
                ], 422);
            }

            DB::table('tblproduct')
                ->where('ProductID', $productId)
                ->update([
                    'ProductModuleLoc' => 'Labeling',
                    'materialtype' => 'Inventory',
                ]);

            Log::info("Product {$productId} moved from {$product->ProductModuleLoc} to Labeling.");

            return response()->json([
                'success' => true,
                'message' => "Product {$productId} moved to Labeling successfully.",
            ]);

        } catch (\Throwable $e) {
            Log::error('moveToLabeling error: ' . $e->getMessage());

            return response()->json(['success' => false, 'message' => $e->getMessage()], 422);
        }
    }

    /**
     * Get all SID list entries (global, not tied to a product)
     */
    public function sidList()
    {
        try {
            $items = DB::table('tblsid')
                ->select('id', 'sid_number', 'alias', 'price', 'quantity', 'threshold', 'image_path')
                ->orderBy('sid_number', 'asc')
                ->get();

            return response()->json([
                'success' => true,
                'data' => $items,
                'total' => $items->count(),
            ]);

        } catch (\Exception $e) {
            Log::error('SID list fetch error: ' . $e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'Error fetching SID list.',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Add a new SID entry
     */
    public function storeSid(Request $request)
    {
        $data = $request->validate([
            'sid_number' => ['required', 'string', 'max:100', 'unique:tblsid,sid_number'],
            'alias' => ['nullable', 'string', 'max:255'],
            'price' => ['nullable', 'numeric', 'min:0'],
            'quantity' => ['nullable', 'integer', 'min:0'],
            'threshold' => ['nullable', 'integer', 'min:0'],
        ]);

        // Server-side safety: if somehow not prefixed, auto-format it
        if (!preg_match('/^SID\d+$/i', $data['sid_number'])) {
            $last = DB::table('tblsid')
                ->whereRaw("sid_number REGEXP '^SID[0-9]+$'")
                ->orderByRaw("CAST(SUBSTRING(sid_number, 4) AS UNSIGNED) DESC")
                ->value('sid_number');

            $next = $last
                ? str_pad((int) substr($last, 3) + 1, 5, '0', STR_PAD_LEFT)
                : '00001';

            $data['sid_number'] = 'SID' . $next;
        }

        try {
            $id = DB::table('tblsid')->insertGetId([
                'sid_number' => $data['sid_number'],
                'alias' => $data['alias'] ?? null,
                'price' => $data['price'] ?? null,
                'quantity' => $data['quantity'] ?? 0,
                'threshold' => $data['threshold'] ?? 0,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            Log::info("SID entry created: ID {$id}, SID {$data['sid_number']}");

            return response()->json([
                'success' => true,
                'message' => 'SID entry added.',
                'id' => $id,
                'sid_number' => $data['sid_number'],
            ]);

        } catch (\Exception $e) {
            Log::error('SID store error: ' . $e->getMessage());
            return response()->json(['success' => false, 'message' => 'Error saving SID entry.'], 500);
        }
    }

    /**
     * Delete a SID entry by ID
     */
    public function deleteSid(int $id)
    {
        try {
            $exists = DB::table('tblsid')->where('id', $id)->exists();

            if (!$exists) {
                return response()->json([
                    'success' => false,
                    'message' => "SID entry with ID {$id} not found.",
                ], 404);
            }

            DB::table('tblsid')->where('id', $id)->delete();

            Log::info("SID entry {$id} deleted.");

            return response()->json([
                'success' => true,
                'message' => 'SID entry deleted successfully.',
            ]);

        } catch (\Exception $e) {
            Log::error('SID delete error: ' . $e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'Error deleting SID entry.',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Upload image for a SID entry
     */
    public function uploadSidImage(Request $request, int $id)
    {
        $request->validate([
            'image' => ['required', 'image', 'mimes:jpg,jpeg,png,webp,gif', 'max:5120'],
        ]);

        try {
            $sid = DB::table('tblsid')->where('id', $id)->first();
            if (!$sid) {
                return response()->json(['success' => false, 'message' => 'SID entry not found.'], 404);
            }

            // Delete old image if exists
            if (!empty($sid->image_path)) {
                $oldPath = public_path("images/sid/{$sid->image_path}");
                if (file_exists($oldPath)) {
                    unlink($oldPath);
                }
            }

            $file = $request->file('image');
            $filename = 'sid_' . $id . '_' . time() . '.' . $file->getClientOriginalExtension();
            $file->move(public_path('images/sid'), $filename);

            DB::table('tblsid')->where('id', $id)->update([
                'image_path' => $filename,
                'updated_at' => now(),
            ]);

            Log::info("SID {$id} image uploaded: {$filename}");

            return response()->json(['success' => true, 'image_path' => $filename]);

        } catch (\Exception $e) {
            Log::error('SID upload image error: ' . $e->getMessage());

            return response()->json(['success' => false, 'message' => 'Error uploading image.'], 500);
        }
    }

    /**
     * Delete image for a SID entry
     */
    public function deleteSidImage(int $id)
    {
        try {
            $sid = DB::table('tblsid')->where('id', $id)->first();
            if (!$sid) {
                return response()->json(['success' => false, 'message' => 'SID entry not found.'], 404);
            }

            if (!empty($sid->image_path)) {
                $path = public_path("images/sid/{$sid->image_path}");
                if (file_exists($path)) {
                    unlink($path);
                }
            }

            DB::table('tblsid')->where('id', $id)->update([
                'image_path' => null,
                'updated_at' => now(),
            ]);

            Log::info("SID {$id} image deleted.");

            return response()->json(['success' => true, 'message' => 'Image deleted.']);

        } catch (\Exception $e) {
            Log::error('SID delete image error: ' . $e->getMessage());

            return response()->json(['success' => false, 'message' => 'Error deleting image.'], 500);
        }
    }

    /**
     * Update a SID entry
     */
    public function updateSid(Request $request, int $id)
    {
        $data = $request->validate([
            'sid_number' => ['required', 'string', 'max:100', "unique:tblsid,sid_number,{$id}"],
            'alias' => ['nullable', 'string', 'max:255'],
            'price' => ['nullable', 'numeric', 'min:0'],
            'quantity' => ['nullable', 'integer', 'min:0'],
            'threshold' => ['nullable', 'integer', 'min:0'],
        ]);

        try {
            $exists = DB::table('tblsid')->where('id', $id)->exists();

            if (!$exists) {
                return response()->json(['success' => false, 'message' => "SID entry with ID {$id} not found."], 404);
            }

            DB::table('tblsid')->where('id', $id)->update([
                'sid_number' => $data['sid_number'],
                'alias' => $data['alias'] ?? null,
                'price' => $data['price'] ?? null,
                'quantity' => $data['quantity'] ?? 0,
                'threshold' => $data['threshold'] ?? 0,
                'updated_at' => now(),
            ]);

            Log::info("SID entry {$id} updated.");

            return response()->json(['success' => true, 'message' => 'SID entry updated successfully.']);

        } catch (\Exception $e) {
            Log::error('SID update error: ' . $e->getMessage());

            return response()->json(['success' => false, 'message' => 'Error updating SID entry.'], 500);
        }
    }

    /**
     * Get currently assigned SID for a product
     */
    public function getProductSid(int $productId)
    {
        try {
            $link = DB::table('tblproduct_sid as ps')
                ->join('tblsid as s', 'ps.sid_id', '=', 's.id')
                ->where('ps.product_id', $productId)
                ->select('s.id', 's.sid_number', 's.alias', 's.price', 's.quantity', 's.threshold')
                ->first();

            return response()->json([
                'success' => true,
                'sid' => $link ?? null,
            ]);

        } catch (\Exception $e) {
            Log::error('getProductSid error: ' . $e->getMessage());

            return response()->json(['success' => false, 'message' => 'Error fetching product SID.'], 500);
        }
    }

    /**
     * Assign (or replace) a SID to a product
     */
    public function assignProductSid(Request $request)
    {
        $data = $request->validate([
            'product_id' => ['required', 'integer'],
            'sid_id' => ['required', 'integer'],
        ]);

        try {
            DB::table('tblproduct_sid')->updateOrInsert(
                ['product_id' => $data['product_id']],
                ['sid_id' => $data['sid_id'], 'updated_at' => now(), 'created_at' => now()]
            );

            Log::info("Product {$data['product_id']} assigned SID {$data['sid_id']}.");

            return response()->json(['success' => true, 'message' => 'SID assigned successfully.']);

        } catch (\Exception $e) {
            Log::error('assignProductSid error: ' . $e->getMessage());

            return response()->json(['success' => false, 'message' => 'Error assigning SID.'], 500);
        }
    }

    /**
     * Unlink SID from a product
     */
    public function unlinkProductSid(int $productId)
    {
        try {
            DB::table('tblproduct_sid')->where('product_id', $productId)->delete();

            Log::info("SID unlinked from product {$productId}.");

            return response()->json(['success' => true, 'message' => 'SID unlinked successfully.']);

        } catch (\Exception $e) {
            Log::error('unlinkProductSid error: ' . $e->getMessage());

            return response()->json(['success' => false, 'message' => 'Error unlinking SID.'], 500);
        }
    }
}
