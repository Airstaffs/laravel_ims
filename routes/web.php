<?php

use App\Http\Controllers\Amzn\FBACartController;
use App\Http\Controllers\Amzn\FBAShipmentController;
use App\Http\Controllers\Amzn\Listing\CatalogController;
use App\Http\Controllers\Amzn\OutboundOrders\ShippingLabel\ShippingLabelController;
use App\Http\Controllers\ASINlistController;
use App\Http\Controllers\AttendanceController;
use App\Http\Controllers\AwsInventoryController;
use App\Http\Controllers\DaysSupplyController;
use App\Http\Controllers\Ebay\EbayController;
use App\Http\Controllers\EmployeeClockController;
use App\Http\Controllers\FbmOrderController;
use App\Http\Controllers\Fbmorders\ManualShipmentLabelController;
use App\Http\Controllers\Fbmorders\PrintInvoiceController;
use App\Http\Controllers\Fbmorders\PrintShippingLabelController;
use App\Http\Controllers\Fbmorders\WorkhistoryController;
use App\Http\Controllers\FnskuController;
use App\Http\Controllers\HouseageController;
use App\Http\Controllers\HrController;
use App\Http\Controllers\LabelingController;
use App\Http\Controllers\LoginController;
use App\Http\Controllers\notfoundController;
use App\Http\Controllers\NotificationController;
use App\Http\Controllers\OrdersController;
use App\Http\Controllers\PackagingController;
use App\Http\Controllers\printer\PrinterController;
use App\Http\Controllers\PrinterManagementController;
use App\Http\Controllers\ProductionAreaController;
use App\Http\Controllers\ReceivedController;
use App\Http\Controllers\ReturnScannerController;
use App\Http\Controllers\RTSController;
use App\Http\Controllers\StockroomController;
use App\Http\Controllers\StoreController;
use App\Http\Controllers\SystemDesignController;
use App\Http\Controllers\tblproductController;
use App\Http\Controllers\TestTableController;
use App\Http\Controllers\UnreceivedController;
use App\Http\Controllers\UPSController;
use App\Http\Controllers\UserController;
use App\Http\Controllers\UserLogsController;
use App\Http\Controllers\UserSessionController;
use App\Http\Controllers\USPSController;
use App\Http\Controllers\ValidationController;
use App\Http\Middleware\PreventBackHistory;
use App\Http\Models\Store;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Session;

Route::get('/', function () {
    return redirect()->route('login');
});

// 🚧 TEMPORARY DEV-ONLY LOGIN BYPASS
Route::get('/dev-login', function () {
    // Never allow this in production
    if (app()->environment('production')) {
        abort(403, 'Not allowed in production.');
    }

    // Find the first SuperAdmin user
    $user = User::where('role', 'SuperAdmin')->first();

    if (! $user) {
        return '❌ No SuperAdmin found. Please create one in phpMyAdmin first.';
    }

    // Log in the user and regenerate session
    Auth::login($user);
    session()->regenerate();

    return redirect()->route('dashboard.system')
        ->with('login_success', '✅ Dev bypass active — logged in as '.$user->username);
});

Route::get('/dashboard', [LoginController::class, 'showSystemDashboard'])->name('dashboard');

// Guest routes (accessible only when not authenticated)
Route::middleware('guest')->group(function () {
    // Login routes
    Route::get('/login', [LoginController::class, 'showLoginForm'])->name('login');
    Route::post('/login', [LoginController::class, 'authenticate']);

    // Google OAuth routes
    Route::get('/auth/google', [LoginController::class, 'googlepage'])->name('google.redirect');
    Route::get('/auth/google/callback', [LoginController::class, 'handleGoogleCallback'])->name('google.callback');
});

// FIXED LOGOUT ROUTE - Changed session key to prevent audio confusion
Route::post('/logout', function (Request $request) {
    try {
        \Log::info('Logout attempt', [
            'user_id' => auth()->id(),
            'ip' => $request->ip(),
            'user_agent' => $request->userAgent(),
        ]);

        // Force logout regardless of token issues
        if (Auth::check()) {
            \Log::info('User logout: '.Auth::user()->username);
        }

        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        // Handle AJAX requests
        if ($request->ajax() || $request->wantsJson()) {
            return response()->json([
                'success' => true,
                'message' => 'Logged out successfully',
                'redirect' => route('login'),
            ]);
        }

        // FIXED: Use 'logout_success' instead of 'success' to avoid audio confusion
        return redirect('/login')->with('logout_success', 'You have been logged out successfully.');
    } catch (\Exception $e) {
        \Log::error('Logout error: '.$e->getMessage());

        // Even if there's an error, try to clear session
        try {
            Auth::logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();
        } catch (\Exception $sessionError) {
            \Log::error('Session clearing error: '.$sessionError->getMessage());
        }

        if ($request->ajax() || $request->wantsJson()) {
            return response()->json([
                'success' => true,
                'message' => 'Logged out',
                'redirect' => route('login'),
            ]);
        }

        return redirect('/login')->with('logout_success', 'You have been logged out.');
    }
})->middleware(['web'])->name('logout');

// BACKUP LOGOUT ROUTE (No CSRF check)
Route::get('/force-logout', function (Request $request) {
    Auth::logout();
    $request->session()->invalidate();
    $request->session()->regenerateToken();

    return redirect('/login')->with('logout_success', 'You have been logged out.');
})->name('force.logout');

// CHECK AUTHENTICATION STATUS (for preventing back button access)
Route::get('/check-auth', function () {
    if (auth()->check()) {
        return response()->json(['authenticated' => true]);
    }

    return response()->json(['authenticated' => false], 401);
});

// Apply PreventBackHistory middleware to all authenticated routes
Route::middleware(['auth', PreventBackHistory::class])->group(function () {
    // Dashboard
    Route::get('/dashboard', [LoginController::class, 'showSystemDashboard'])->name('dashboard.system');

    // CSRF token refresh endpoint (Enhanced with better headers)
    Route::get('/csrf-token', function () {
        return response()->json([
            'token' => csrf_token(),
            'timestamp' => now()->toIso8601String(),
        ])->header('Cache-Control', 'no-store, no-cache, must-revalidate, max-age=0')
            ->header('Pragma', 'no-cache')
            ->header('Expires', '0');
    })->name('csrf.token');

    Route::post('/keep-alive', function () {
        try {
            // Check authentication first
            if (! auth()->check()) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Not authenticated',
                ], 401);
            }

            // Touch the session to keep it alive
            session()->put('last_activity', now()->timestamp);

            // ❌ REMOVE OR COMMENT OUT session migration during keep-alive
            // This can cause CSRF token issues during rapid retries
            // if (random_int(1, 100) <= 5) {
            //     session()->migrate();
            // }

            return response()->json([
                'status' => 'alive',
                'timestamp' => now()->toIso8601String(),
                'user' => auth()->user()->name ?? auth()->user()->email,
                'session_id' => session()->getId(),
            ]);

        } catch (\Exception $e) {
            \Log::error('Keep-alive failed', [
                'error' => $e->getMessage(),
                'user_id' => auth()->id(),
            ]);

            return response()->json([
                'status' => 'error',
                'message' => $e->getMessage(),
            ], 500);
        }
    })->name('session.keepalive');

     Route::get('/check-session', function () {
        return response()->json([
            'session_driver' => config('session.driver'),
            'session_lifetime' => config('session.lifetime'),
            'session_secure' => config('session.secure'),
            'session_cookie' => config('session.cookie'),
            'app_url' => config('app.url'),
            'session_count' => DB::table('sessions')->count(),
            'authenticated' => auth()->check(),
        ]);
    });

    // All other authenticated routes
    Route::get('/dashboard/Systemdashboard', [LoginController::class, 'showSystemDashboard']);
    Route::get('/get-user-privileges/{userId}', [UserController::class, 'getUserPrivileges']);
    Route::post('/save-user-privileges', [UserController::class, 'saveUserPrivileges'])->name('saveUserPrivileges');
    Route::post('/refresh-user-session', [UserController::class, 'refreshUserSession'])->name('refresh.user.session');

    Route::get('/fetchNewlyAddedStoreCol', [UserController::class, 'fetchNewlyAddedStoreCol']);
    Route::get('/get-store-columns', [UserController::class, 'getStoreColumns']);

    // User Routes
    Route::post('/add-user', [UserController::class, 'store'])->name('add-user');
    Route::post('/update-password', [UserController::class, 'updatepassword'])->name('update-password');
    Route::get('/myprivileges', [UserController::class, 'showmyprivileges'])->name('myprivileges');
    Route::get('/users', [UserController::class, 'createdusers'])->name('user');
    Route::post('/update-user/{id}', [UserController::class, 'update'])->name('update-user');
    Route::delete('/delete-user/{id}', [UserController::class, 'destroy'])->name('delete-user');

    // System Design Routes
    Route::get('/get-system-design-data', [SystemDesignController::class, 'getData'])->name('get.system.design.data')->middleware('auth');
    Route::post('/update-system-design', [SystemDesignController::class, 'update'])->name('update.system.design')->middleware('auth');

    // Store Routes
    Route::get('/get-stores', [StoreController::class, 'getStores']);
    Route::get('/get-store/{id}', [StoreController::class, 'getStoreID'])->name('get-store');
    Route::post('/update-store/{id}', [StoreController::class, 'updateStore'])->name('update-store');
    Route::post('/add-store', [StoreController::class, 'addstore'])->name('add-store');
    Route::delete('/delete-store/{id}', [StoreController::class, 'delete'])->name('delete-store');
    Route::get('/fetch-marketplaces', [StoreController::class, 'fetchMarketplaces']);
    Route::get('/fetch-marketplaces-tblstores', [StoreController::class, 'fetchMarketplacestblstores'])->name('fetchMarketplacestblstores');

    // Attendance Routes
    Route::post('/attendance/clockin', [AttendanceController::class, 'clockIn'])->name('attendance.clockin');
    Route::post('/attendance/clockout', [AttendanceController::class, 'clockOut'])->name('attendance.clockout');
    Route::post('/update-computed-hours', [AttendanceController::class, 'updateComputedHours'])->name('update.computed.hours');
    Route::post('/attendance/update-hours', [AttendanceController::class, 'updateHours'])->name('attendance.update.hours');
    Route::post('/attendance/filter', [AttendanceController::class, 'filterAttendanceAjax'])->name('attendance.filter.ajax');
    Route::post('/attendance/auto-clockout', [AttendanceController::class, 'autoClockOut'])->name('auto-clockout');
    Route::post('/update-notes/{id}', [AttendanceController::class, 'updateNotes'])->name('update-notes');

    Route::get('/get-user-logs', [UserLogsController::class, 'getUserLogs']);
    Route::get('/get-time-records/{user_id}', [EmployeeClockController::class, 'getUserTimeRecords']);

    Route::get('/check-user-privileges', [UserSessionController::class, 'checkUserPrivileges']);
    Route::post('/refresh-user-session', [UserSessionController::class, 'refreshSession']);
});

// Fallback route for undefined routes
Route::fallback(function () {
    if (auth()->check()) {
        return redirect()->route('dashboard.system');
    }

    return redirect()->route('login');
});

// Module Routes
Route::get('/Systemmodule/{module}Module/{moduleName}', function ($module, $moduleName) {
    $availableModules = ['Order', 'Unreceived', 'Receiving', 'Labeling', 'Validation', 'Testing', 'Cleaning', 'Packing', 'Stockroom'];

    if (in_array($moduleName, $availableModules)) {
        return view("Systemmodule.{$module}Module.{$moduleName}");
    }

    abort(404);
})->name('modules');

// AWS Inventory Routes
Route::get('/aws-inventory', function () {
    return view('tests.aws_inventory');
})->name('aws.inventory.view');
Route::post('/aws/inventory/summary', [AwsInventoryController::class, 'fetchInventorySummary'])->name('aws.inventory.summary');

// USPS
Route::get('/uspstracking', function () {
    return view('tests.usps');
})->name('usps.tracking');

Route::post('/usps/tracking', [USPSController::class, 'USPSfetchTrackDetails'])->name('usps.trackingnumber');

// UPS
Route::get('/apis/upstracking', function () {
    return view('tests.ups');
})->name('ups.tracking');

Route::post('/apis/upstracking', [UPSController::class, 'UPSfetchTrackDetails'])->name('UPS.trackingnumber');

// eBay Routes
Route::get('/apis/ebay-callback', action: function () {
    require app_path('Helpers/ebay_helpers.php');
    echo 'Hello';
    if (isset($_GET['code'])) {
        $authorizationCode = $_GET['code'];
        $accessToken = getAccessToken($authorizationCode);

        if ($accessToken) {
            return response()->json(['access_token' => $accessToken]);
        } else {
            return response()->json(['error' => 'Unable to obtain access token.'], 500);
        }
    } else {
        return response()->json(['error' => 'Authorization code not provided.'], 400);
    }
});

Route::get('/apis/ebay-login', action: function () {
    $clientId = 'JuliusSa-IMSV2-PRD-58e8cc815-c6b0ffc8';
    $redirectUrl = 'https://test.tecniquality.com/apis/ebay-callback';
    $scopes = 'https://api.ebay.com/oauth/api_scope https://api.ebay.com/oauth/api_scope/sell.marketing.readonly https://api.ebay.com/oauth/api_scope/sell.inventory.readonly https://api.ebay.com/oauth/api_scope/sell.account.readonly https://api.ebay.com/oauth/api_scope/sell.fulfillment.readonly';

    $authUrl = "https://auth.ebay.com/oauth2/authorize?client_id={$clientId}&redirect_uri={$redirectUrl}&response_type=code&scope=".urlencode($scopes);

    echo "<a href='{$authUrl}'>Authorize with eBay</a>";
});

Route::get('/ebay/orders', [EbayController::class, 'fetchOrders']);

Route::get('/ebay/orders/cron-automation/{token}', function ($token) {
    if ($token !== env('CRON_SECRET')) {
        abort(403, 'Unauthorized');
    }

    return app(EbayController::class)->fetchOrders(request());
});

Route::post('/amzn/fba-cart/add', [FBACartController::class, 'addToCart']);
Route::get('/amzn/fba-cart/list', [FBACartController::class, 'list']);
Route::get('/amzn/fba-cart/get-or-create-cart', [FBACartController::class, 'getOrCreateCart']);
Route::delete('/amzn/fba-cart/remove', [FBACartController::class, 'removeFromCart']);
Route::post('/amzn/fba-cart/commit', [FBACartController::class, 'commitCart']);

Route::post('/amzn/fba-shipment/add-item', [FBAShipmentController::class, 'addItemToShipment']);
Route::get('/amzn/fba-shipment/fetch-shipments', [FBAShipmentController::class, 'fetch_shipment']);
Route::post('/amzn/fba-shipment/delete-item', [FBAShipmentController::class, 'deleteShipmentItem']);
Route::post('/amzn/fba-shipment/fetch_package_dimensions', [FBAShipmentController::class, 'package_dimension_fetcher']);
Route::get('/amzn/fba-shipment/get_inbound_plans', [FBAShipmentController::class, 'fetchinboundplans']);
Route::get('/amzn/fba-shipment/step1/cancel-shipment', [FBAShipmentController::class, 'cancel_inboundplan']);

Route::get('/amzn/fba-shipment/step1/create-shipment', [FBAShipmentController::class, 'step1_createShipment']);
Route::get('/amzn/fba-shipment/step2/generate-packing', [FBAShipmentController::class, 'step2a_generate_packing']);
Route::get('/amzn/fba-shipment/step2/list-packing-options', [FBAShipmentController::class, 'step2b_list_packing_options']);
Route::get('/amzn/fba-shipment/step2/list-items-packing-option', [FBAShipmentController::class, 'step2c_list_items_by_packing_options']);
Route::get('/amzn/fba-shipment/step2/confirm-packing-option', [FBAShipmentController::class, 'step2d_confirm_packing_option']);
Route::get('/amzn/fba-shipment/step3/packing_information', [FBAShipmentController::class, 'step3a_packing_information']);
Route::get('/amzn/fba-shipment/step4/placement_option', [FBAShipmentController::class, 'step4a_placement_option']);
Route::get('/amzn/fba-shipment/step4/list_placement_option', [FBAShipmentController::class, 'step4b_list_placement_option']);
Route::get('/amzn/fba-shipment/step4/get_shipment', [FBAShipmentController::class, 'step4c_get_shipment']);
Route::get('/amzn/fba-shipment/step5/transportation_options', [FBAShipmentController::class, 'step5a_transportation_options']);
Route::get('/amzn/fba-shipment/step5/generate_delivery_options', [FBAShipmentController::class, 'step5b_generate_delivery_options']);
Route::get('/amzn/fba-shipment/step5/transportation_options_view', [FBAShipmentController::class, 'step5c_transportation_options_view']);
Route::get('/amzn/fba-shipment/step6/list_delivery_window_options', [FBAShipmentController::class, 'step6a_list_delivery_window_options']);

Route::get('/amzn/fba-shipment/step6/confirm_placement_option', [FBAShipmentController::class, 'step6b_confirm_placement_option']);
Route::get('/amzn/fba-shipment/step7/confirm_delivery_window_options', [FBAShipmentController::class, 'step7a_confirm_delivery_window_options']);
Route::get('/amzn/fba-shipment/step8/confirm_transportation_options', [FBAShipmentController::class, 'step8a_confirm_transportation_options']);

Route::get('/amzn/fba-shipment/step9/get_shipment', [FBAShipmentController::class, 'step9a_get_shipment']);
Route::get('/amzn/fba-shipment/step10/print_label', [FBAShipmentController::class, 'step10a_print_label']);

Route::get('/test', [TestTableController::class, 'index']);

Route::get('/products', [tblproductController::class, 'index']);
Route::get('/stockroom/products/by-fnsku', [tblproductController::class, 'fetchPerFnsku']);

// Session management routes
Route::get('/keep-alive', [App\Http\Controllers\UserSessionController::class, 'keepAlive'])
    ->middleware('web');

Route::get('/csrf-token', [App\Http\Controllers\UserSessionController::class, 'csrfToken'])
    ->middleware('web');

Route::middleware(['web', \App\Http\Middleware\RefreshSession::class])->group(function () {
    // Your existing routes go here
});

// Routes for Stockroom scanner
Route::prefix('api/stockroom')->group(function () {
    Route::get('products', [StockroomController::class, 'index']);
    Route::get('check-fnsku', [StockroomController::class, 'checkFnsku']);
    Route::post('process-scan', [StockroomController::class, 'processScan']);
    Route::post('print-label', [StockroomController::class, 'printLabel']);
    Route::get('stores', [StockroomController::class, 'getStores']);

    // New routes for Process functionality
    Route::post('/process-items', [StockroomController::class, 'processItems']);
    Route::post('merge-items', [StockroomController::class, 'mergeItems']);
    Route::post('update-location', [StockroomController::class, 'updateLocation']);

    // amazon items post
    Route::post('post-items-to-amazon', [StockroomController::class, 'PostItemstoAmazon']);

    // NEW: New Scanned Items functionality
    Route::get('new-scanned-count', [StockroomController::class, 'getNewScannedCount']);
    Route::get('new-scanned-items', [StockroomController::class, 'getNewScannedItems']);
    Route::post('update-fbm-status', [StockroomController::class, 'updateFbmStatus']);

});

// Routes for Unreceived scanner
Route::prefix('api/unreceived')->group(function () {
    Route::get('products', [UnreceivedController::class, 'index']);
    Route::get('verify-tracking', [UnreceivedController::class, 'verifyTracking']);
    Route::get('get-next-rpn', [UnreceivedController::class, 'getNextRpn']);
    Route::post('process-scan', [UnreceivedController::class, 'processScan']);
});

// Routes for Received scanner
Route::prefix('api/received')->group(function () {
    Route::get('products', [ReceivedController::class, 'index']);
    Route::get('verify-tracking', [ReceivedController::class, 'verifyTracking']);
    Route::post('validate-pcn', [ReceivedController::class, 'validatePcn']);
    Route::post('process-scan', [ReceivedController::class, 'processScan']);
});

Route::post('api/images/upload', [App\Http\Controllers\ImageUploadController::class, 'upload']);

// Routes Orders
Route::prefix('api/orders')->group(function () {
    Route::get('products', [OrdersController::class, 'index']);
    Route::post('products', [OrdersController::class, 'store']);
});

// Routes Production Area
Route::prefix('api/productionArea')->group(function () {
    Route::get('products', [ProductionAreaController::class, 'index']);
});

// Routes Packaging
Route::prefix('api/packaging')->group(function () {
    Route::get('products', [PackagingController::class, 'index']);
});

// Routes Returns
Route::prefix('api/returns')->group(function () {
    Route::get('products', [ReturnScannerController::class, 'index']);
    Route::get('stores', [ReturnScannerController::class, 'getStores']);
    Route::get('check-serial', [ReturnScannerController::class, 'checkSerial']);
    Route::post('process-scan', [ReturnScannerController::class, 'processScan']);
});

// Routes for Labeling Function
Route::prefix('api/labeling')->group(function () {
    Route::get('products', [LabelingController::class, 'index']);
    Route::post('products', [LabelingController::class, 'store']);
    Route::post('split-item', [LabelingController::class, 'splitItem']);

    // ADD THESE MISSING ROUTES:
    Route::post('move-to-validation', [LabelingController::class, 'moveToValidation']);
    Route::post('move-to-stockroom', [LabelingController::class, 'moveToStockroom']);
});

// Routes for RTS Function
Route::prefix('api/rts')->group(function () {
    Route::get('products', [RTSController::class, 'index']);
    Route::post('save-rts-options', [RTSController::class, 'saveRTSOptions']);
    Route::get('get-rts-options', [RTSController::class, 'getRTSOptions']);
    Route::post('products', [RTSController::class, 'store']);
});

Route::post('/test-move-validation', [LabelingController::class, 'moveToValidation']);
Route::post('/test-move-stockroom', [LabelingController::class, 'moveToStockroom']);

// Routes for Validation Function
Route::prefix('api/validation')->group(function () {
    Route::get('products', [ValidationController::class, 'index']);
    Route::post('move-to-stockroom', [ValidationController::class, 'moveToStockroom']);
    Route::post('move-to-labeling', [ValidationController::class, 'moveToLabeling']);
    Route::post('validate', [ValidationController::class, 'validate']);
});

// Routes for Fbm Order Function
Route::prefix('api/fbm-orders')->group(function () {
    Route::get('/', [FbmOrderController::class, 'index']);
    Route::get('/stores', [FbmOrderController::class, 'getStores']);
    Route::post('/process', [FbmOrderController::class, 'processOrder']);
    Route::post('/packing-slip', [FbmOrderController::class, 'generatePackingSlip']);
    Route::post('/shipping-label', [FbmOrderController::class, 'printShippingLabel']);
    Route::post('/cancel', [FbmOrderController::class, 'cancelOrder']);
    Route::post('/auto-dispense', [FbmOrderController::class, 'autoDispense']);
    Route::post('/find-dispense-products', [FbmOrderController::class, 'findDispenseProducts']);
    Route::post('/dispense', [FbmOrderController::class, 'dispense']);
    Route::post('/cancel-dispense', [FbmOrderController::class, 'cancelDispense']);
    Route::get('/detail', [FbmOrderController::class, 'getOrderDetail']);
    Route::post('/mark-not-found', [FbmOrderController::class, 'markProductNotFound']);
    Route::get('/shipping-label-selected-items', [FbmOrderController::class, 'shippinglabelselecteditem']);

    Route::post('/work-history', [WorkhistoryController::class, 'fetchWorkHistory']);
    Route::post('/export-work-history', [WorkhistoryController::class, 'exportWorkHistory']);
});

// Routes Not Found
Route::prefix('api/notfound')->group(function () {
    Route::get('products', [notfoundController::class, 'index']);
    Route::post('move-to-stockroom', [notfoundController::class, 'moveToStockroom']);
});

// Routes for ASIN List Function
Route::prefix('api/asinlist')->group(function () {

    // Get ASIN products list
    Route::get('products', [ASINlistController::class, 'index']);

    // Get stores for dropdown
    Route::get('stores', [ASINlistController::class, 'getStores']);
    Route::get('/asin/search', [ASINlistController::class, 'searchAsin']);
    Route::post('/msku/save', [ASINlistController::class, 'saveMsku']);
    Route::post('/msku/generate', [ASINlistController::class, 'generateMsku']);
    Route::get('/all/stores', [ASINlistController::class, 'fetchstores']);

    // Update ASIN details (EAN/UPC/Instruction Link/Meta Keyword/Transparency)
    Route::post('update-asin-details', [ASINlistController::class, 'updateAsinDetails']);

    // Update default dimensions and weight (NEW)
    Route::post('update-default-dimensions', [ASINlistController::class, 'updateDefaultDimensions']);

    // Update related ASINs
    Route::post('update-related-asins', [ASINlistController::class, 'updateRelatedAsins']);

    // Upload instruction card
    Route::post('upload-instruction-card', [ASINlistController::class, 'uploadInstructionCard']);

    // Upload user manual
    Route::post('upload-user-manual', [ASINlistController::class, 'uploadUserManual']);

    // Upload ASIN main image
    Route::post('upload-asin-image', [ASINlistController::class, 'uploadAsinImage']);

    // Upload vector image
    Route::post('upload-vector-image', [ASINlistController::class, 'uploadAsinVectorImage']);

    // Bulk Upload asin instruction card
    Route::post('bulk-upload-instruction-cards', [ASINlistController::class, 'bulkUploadInstructionCards']);
});

// Routes for Houseage Function
Route::prefix('api/houseage')->group(function () {
    Route::get('products', [HouseageController::class, 'index']);
    Route::post('products', [HouseageController::class, 'store']);
    Route::post('check-duplicate-serial', [HouseageController::class, 'checkDuplicateSerial']);

    Route::post('serial-image', [HouseageController::class, 'uploadSerialNumber'])
        ->name('houseage.serial-image');

    Route::get('serial-image', [HouseageController::class, 'getSerialImage']);
});

// Printer API routes
Route::prefix('api/printer')->group(function () {
    // Check if serial number meets print conditions
    Route::post('check-serial', [PrinterController::class, 'checkSerial']);

    // Print label for a product (now supports married printers)
    Route::post('print-label', [PrinterController::class, 'printLabel']);

    // Get printer status
    Route::get('status', [PrinterController::class, 'getStatus']);

    // Test printer connection
    Route::get('test-connection', [PrinterController::class, 'testConnection']);

    // Test print functionality
    Route::post('test-print', [PrinterController::class, 'testPrint']);

    // Get all printers with marriage information (enhanced version)
    Route::get('get-printers', [PrinterController::class, 'getPrinters']);

    // NEW: Get married printer pairs for synchronized printing
    Route::get('get-married-pairs', [PrinterController::class, 'getMarriedPrinterPairs']);

    // REPRINT ROUTES (now support smart routing)
    Route::post('search-for-reprint', [PrinterController::class, 'searchForReprint']);
    Route::post('reprint-single-label', [PrinterController::class, 'reprintSingleLabel']);

    // Debug routes (helpful for troubleshooting)
    Route::post('debug-database', [PrinterController::class, 'debugDatabase']);

    Route::post('clear-cache', [PrinterController::class, 'clearCache']);
});

Route::prefix('api/printer-management')->middleware(['auth'])->group(function () {
    Route::get('get-printers', [PrinterManagementController::class, 'getAllPrinters']);
    Route::get('get-printer/{id}', [PrinterManagementController::class, 'getPrinter']);
    Route::post('add-printer', [PrinterManagementController::class, 'addPrinter']);
    Route::post('update-printer/{id}', [PrinterManagementController::class, 'updatePrinter']);
    Route::delete('delete-printer/{id}', [PrinterManagementController::class, 'deletePrinter']);
    Route::post('test-printer/{id}', [PrinterManagementController::class, 'testPrinter']);
    Route::get('get-available-printers', [PrinterManagementController::class, 'getAvailablePrinters']);
    Route::post('marry-printers', [PrinterManagementController::class, 'marryPrinters']);
    Route::get('get-married-printers', [PrinterManagementController::class, 'getMarriedPrinters']);
    Route::delete('divorce-printers/{id}', [PrinterManagementController::class, 'divorcePrinters']);
});

// Routes for FNSKU List Function
Route::get('api/fnsku/fnsku-list', [FnskuController::class, 'getFnskuList']);
Route::post('api/fnsku/update-fnsku', [FnskuController::class, 'updateFnsku']);
Route::get('api/fnsku/fnsku', [FnskuController::class, 'index']);
Route::post('api/fnsku/insert-fnsku', [FnskuController::class, 'insertFnsku']);
Route::get('api/labeling/product/{productId}', [LabelingController::class, 'getProduct']);
Route::get('api/fnsku/availability', [FnskuController::class, 'getFnskuAvailability']);

Route::get('/clone-table-form', [App\Http\Controllers\TableController::class, 'showCloneForm'])->name('clone.table.form');
Route::post('/clone-table', [App\Http\Controllers\TableController::class, 'cloneTable'])->name('clone.table');

// FBM Orders Shipping Label

Route::post('/amzn/fbm-orders/purchase-label/rates', [ShippingLabelController::class, 'get_rates']);
Route::post('/amzn/fbm-orders/purchase-label/createshipment', [ShippingLabelController::class, 'create_shipment']);
Route::post('/amzn/fbm-orders/purchase-label/manualshipment', [ShippingLabelController::class, 'manual_shipment']);

Route::match(['get', 'post'], '/fbmorders/fetch-work-history', [WorkhistoryController::class, 'fetchWorkHistory']);

// Automations
Route::get('/postmaster', function () {
    return include base_path('automations/postmaster.php');
});

Route::get('/usps_tracking', function () {
    ob_start();
    include base_path('automations/usps_tracking_updater.php');

    return response(ob_get_clean());
});

Route::get('/ups_tracking', function () {
    ob_start();

    return include base_path('automations/ups_tracking_updater.php');

    return response(ob_get_clean());
});

Route::get('auth/google', [LoginController::class, 'googlepage']);
Route::get('auth/google/callback', [LoginController::class, 'handleGoogleCallback']);

Route::post('/fbm-orders-invoice', [PrintInvoiceController::class, 'printInvoice']);

Route::get('/fbm-orders-invoice-test', function () {
    $controller = new PrintInvoiceController;

    $request = Request::create('/fbm-orders-invoice', 'POST', [
        'platform_order_ids' => ['111-9674483-2472244'],
        'action' => 'PrintInvoice',
        'settings' => [
            'displayPrice' => true,
            'testPrint' => true,
            'signatureRequired' => true,
        ],
    ]);

    return $controller->printInvoice($request);
});

// print shipping label fbm orders

Route::post('/fbm-orders-shippinglabel', [PrintShippingLabelController::class, 'printshippinglabel']);

// timezone system
Route::post('/update-timezone', [UserController::class, 'updateTimezone'])->name('update-timezone');
Route::get('/user/settings/timezone', [UserController::class, 'showTimezoneSettings'])->name('timezone.settings');

Route::get('/fbm-orders-shippinglabel-test', function () {
    $controller = new PrintShippingLabelController;

    $request = Request::create('/fbm-orders-shippinglabel', 'POST', [
        'platform_order_ids' => ['114-0083765-2829867'],
        'action' => 'PrintShipmentLabel',
        'settings' => [
            'displayPrice' => true,
            'testPrint' => true,
            'signatureRequired' => true,
        ],
    ]);

    return $controller->printshippinglabel($request);
});

Route::get('/session-warmup', function () {
    return response()->noContent(); // Or just return 200 OK
});

// Fbm Orders Manual Shipment Label

Route::post('/fbm-orders-manualshipmentlabel', [ManualShipmentLabelController::class, 'store']);
Route::post('/fbm-orders-add-new-carrier', [ManualShipmentLabelController::class, 'newCarrierDescription']);
Route::get('/fbm-orders-carrier-options', [ManualShipmentLabelController::class, 'getCarrierDescriptions']);

/*
//Listings FNSKU Creation


Route::post('/amzn/listing/search-asin-data', [CatalogController::class, 'get_asin_catalog']);
Route::get('/amzn/test-asin-data', function () {
    $controller = new CatalogController();

    $request = Request::create('/fbm-orders-shippinglabel', 'POST', [
        'platform_order_ids' => ['114-0083765-2829867'],
        'action' => 'PrintShipmentLabel',
        'settings' => [
            'displayPrice' => true,
            'testPrint' => true,
            'signatureRequired' => true
        ],
    ]);

    return $controller->printshippinglabel($request);
});
*/

Route::get('/amzn/catalog/get_asin_catalog', [CatalogController::class, 'get_asin_catalog']);

Route::post('/notifications/create', [NotificationController::class, 'create']);
Route::get('/notifications/user/{id}', [NotificationController::class, 'getByUser']);
Route::post('/notifications/mark-read', [NotificationController::class, 'markAsRead']);
Route::post('/notifications/mark-unread', [NotificationController::class, 'markAsUnread']);
Route::get('/notifications/unread-count/{id}', [NotificationController::class, 'getUnreadCount']);

Route::get('/joined-fnsku-data', [LabelingController::class, 'getFnskuData']);

// HR Controller
Route::prefix('hr')->group(function () {
    Route::get('/employees', [HrController::class, 'getEmployees']);
    Route::get('/employee-rate-history', [HrController::class, 'getEmployeeRateHistory']);
    Route::get('/employees/{employee}/rates', [HrController::class, 'indexRate']);
    Route::get('/employees/{employee}/rates/current', [HrController::class, 'currentRate']);
    Route::post('/employees/{employee}/rates', [HrController::class, 'storeRate']);

    Route::get('/time-records', [HrController::class, 'getTimeRecords']);
    Route::post('/time-records/{id}/edit', [HrController::class, 'editTimeRecord']);
    Route::post('/time-records/edit-history', [HrController::class, 'listClockEditHistory']);
    Route::post('/time-records/{id}/edit-history', [HrController::class, 'getClockEditHistoryByClock']);

    Route::get('/leave-history', [HrController::class, 'getLeaveHistory']);
    Route::get('/violations', [HrController::class, 'getViolations']);

    Route::post('/holidays/list', [HrController::class, 'listHolidays']);
    Route::post('/holidays/store', [HrController::class, 'storeHoliday']);
    Route::post('/holidays/update', [HrController::class, 'updateHoliday']);
    Route::post('/holidays/delete', [HrController::class, 'deleteHoliday']);

    // Announcements (inside HrController)
    Route::get('/announcements', [HrController::class, 'listAnnouncements']);
    Route::post('/announcements', [HrController::class, 'storeAnnouncement']);
    Route::get('/dash/announcements', [HrController::class, 'dashviewAnnouncement']);
    Route::post('/dash/announcements/acknowledge', [HrController::class, 'acknowledgeAnnouncement']);
    Route::get('/announcements/admin', [HrController::class, 'adminListAnnouncements']);
    Route::post('/announcements/save', [HrController::class, 'saveAnnouncement']);     // create or update (draft/active)
    Route::post('/announcements/toggle-active', [HrController::class, 'toggleAnnouncementActive']);

    Route::get('/timesched', [HrController::class, 'listTimesched']);
    Route::post('/timesched', [HrController::class, 'createTimesched']);
    Route::put('/timesched/{id}', [HrController::class, 'updateTimesched']);
    Route::delete('/timesched/{id}', [HrController::class, 'deleteTimesched']);
    Route::get('/usersched', [HrController::class, 'listUserSched']);
    Route::post('/usersched', [HrController::class, 'createUserSched']);
    Route::put('/usersched/{id}', [HrController::class, 'updateUserSched']);
    Route::delete('/usersched/{id}', [HrController::class, 'deleteUserSched']);

    Route::get('/account/details', [HrController::class, 'getUserProfileDetails'])
        ->name('account.details')->middleware('auth');

    Route::get('/profile/{userId}', [HrController::class, 'getUserProfileDetailsById']);
    Route::post('/account/update-details', [HrController::class, 'updateUserProfileDetails'])
        ->name('account.update-details')->middleware('auth');
    // new, for the permissions UI
    Route::get('/stores', [HrController::class, 'listStores']); // list stores for UI
    Route::get('/employees/{id}/permissions', [HrController::class, 'getEmployeePermissions']);
    Route::post('/employees/{id}/permissions', [HrController::class, 'updateEmployeePermissions']);

    Route::get('/break/status', [AttendanceController::class, 'status'])->name('hr.break.status');
    Route::post('/break/start', [AttendanceController::class, 'start'])->name('hr.break.start');
    Route::post('/break/end', [AttendanceController::class, 'end'])->name('hr.break.end');
});

Route::get('/schedule/month', [AttendanceController::class, 'month']);

// routes/web.php
Route::middleware(['auth'])->get('/account/complete', function () {
    return view('account.complete');  // the small Blade that calls your HR endpoints
})->name('account.complete.view');

Route::get('/ds7oos', [DaysSupplyController::class, 'index']); // public for now
