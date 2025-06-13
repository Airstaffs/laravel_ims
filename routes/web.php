<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\LoginController;
use Illuminate\Support\Facades\Auth;
use App\Http\Controllers\SystemDesignController;
use App\Http\Controllers\UserController;
use App\Http\Controllers\AwsInventoryController;
use App\Http\Controllers\StoreController;
use App\Http\Models\Store;
use App\Http\Controllers\AttendanceController;
use App\Http\Controllers\UserPrivilegesController;
use Illuminate\Support\Facades\Session;
use App\Models\User;
use Illuminate\Support\Facades\Schema;
use App\Http\Controllers\USPSController;
use App\Http\Controllers\UPSController;
use App\Http\Controllers\UserSessionController;
use App\Http\Controllers\EmployeeClockController;
use App\Http\Controllers\UserLogsController;
use App\Http\Controllers\StockroomController;
use App\Http\Controllers\UnreceivedController;
use App\Http\Controllers\ReceivedController;
use App\Http\Controllers\LabelingController;
use App\Http\Controllers\ValidationController;
use App\Http\Controllers\EbayAuthController;
use App\Http\Controllers\OrdersController;
use App\Http\Controllers\ProductionAreaController;
use App\Http\Controllers\PackagingController;
use App\Http\Controllers\ReturnScannerController;
use App\Http\Controllers\FbmOrderController;
use App\Http\Controllers\notfoundController;
use App\Http\Controllers\Fbmorders\WorkhistoryController;

use Illuminate\Support\Facades\Http;
use Illuminate\Http\Request;


Route::get('/', function () {
    return view('welcome');
});

// Secure POST route for normal logout
Route::post('/logout', function (Request $request) {
    Auth::logout();
    $request->session()->invalidate();
    $request->session()->regenerateToken();
    return redirect('/login')->with('message', 'You have been logged out successfully.');
})->name('logout');

// GET route only for session expiration redirect, NOT manual logout
Route::get('/logout', function (Request $request) {
    Auth::logout();
    $request->session()->invalidate();
    $request->session()->regenerateToken();
    return redirect('/login')->with('message', 'Your session has expired. Please login again.');
})->name('logout.expired');

// Login Routes
Route::get('/login', [LoginController::class, 'showLoginForm'])->name('login');
Route::post('/login', [LoginController::class, 'authenticate'])->name('login');

// Dashboard Route (Protected with auth middleware)
Route::get('/dashboard/Systemdashboard', [LoginController::class, 'showSystemDashboard'])->middleware('auth');
Route::get('/get-user-privileges/{userId}', [UserController::class, 'getUserPrivileges']);
Route::post('/save-user-privileges', [UserController::class, 'saveUserPrivileges'])->name('saveUserPrivileges');
Route::post('/refresh-user-session', [UserController::class, 'refreshUserSession'])->name('refresh.user.session');


Route::get('/fetchNewlyAddedStoreCol', [UserController::class, 'fetchNewlyAddedStoreCol']);


Route::get('/get-store-columns', [UserController::class, 'getStoreColumns']);

Route::get('/dashboard/Systemdashboard', function () {
    return view('dashboard.Systemdashboard');
})->middleware('auth');

// User Routes
Route::post('/add-user', [UserController::class, 'store'])->name('add-user');
Route::post('/update-password', [UserController::class, 'updatepassword'])->name('update-password');
Route::get('/myprivileges', [UserController::class, 'showmyprivileges'])->name('myprivileges');
Route::get('/users', [UserController::class, 'createdusers'])->name('user');
Route::post('/update-user/{id}', [UserController::class, 'update'])->name('update-user');
Route::delete('/delete-user/{id}', [UserController::class, 'destroy'])->name('delete-user');

// System Design Routes
Route::post('/update-system-design', [SystemDesignController::class, 'update'])->name('update.system.design');



// Module Routes
Route::get('/Systemmodule/{module}Module/{moduleName}', function ($module, $moduleName) {
    $availableModules = ['Order', 'Unreceived', 'Receiving', 'Labeling', 'Validation', 'Testing', 'Cleaning', 'Packing', 'Stockroom'];

    if (in_array($moduleName, $availableModules)) {
        return view("Systemmodule.{$module}Module.{$moduleName}");
    }

    abort(404);
})->name('modules');


Route::get('/get-stores', [StoreController::class, 'getStores']);
Route::get('/get-store/{id}', [StoreController::class, 'getStoreID'])->name('get-store');
Route::post('/update-store/{id}', [StoreController::class, 'updateStore'])->name('update-store');

Route::post('/add-store', [StoreController::class, 'addstore'])->name('add-store');
Route::delete('/delete-store/{id}', [StoreController::class, 'delete'])->name('delete-store');
Route::get('/fetch-marketplaces', [StoreController::class, 'fetchMarketplaces']);
Route::get('/fetch-marketplaces-tblstores', [StoreController::class, 'fetchMarketplacestblstores'])->name('fetchMarketplacestblstores');

Route::get('/dashboard/Systemdashboard', [AttendanceController::class, 'attendance']);
Route::post('/attendance/clockin', [AttendanceController::class, 'clockIn'])->name('attendance.clockin');
Route::post('/attendance/clockout', [AttendanceController::class, 'clockOut'])->name('attendance.clockout');
Route::post('/update-computed-hours', [AttendanceController::class, 'updateComputedHours'])->name('update.computed.hours');
Route::post('/attendance/update-hours', [AttendanceController::class, 'updateHours'])->name('attendance.update.hours');
Route::post('/attendance/filter', [AttendanceController::class, 'filterAttendanceAjax'])->name('attendance.filter.ajax');
Route::post('/attendance/auto-clockout', [AttendanceController::class, 'autoClockOut'])->name('auto-clockout');
Route::post('/update-notes/{id}', [AttendanceController::class, 'updateNotes'])->name('update-notes');

Route::get('/get-user-logs', [UserLogsController::class, 'getUserLogs']);
Route::get('/get-time-records/{user_id}', [EmployeeClockController::class, 'getUserTimeRecords']);


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

Route::get('/apis/ebay-callback', action: function () {
    require app_path('Helpers/ebay_helpers.php');
    // Check if the 'code' parameter is present in the URL
    echo "Hello";
    if (isset($_GET['code'])) {
        $authorizationCode = $_GET['code']; // Get the authorization code from the URL
        // Call the getAccessToken function to exchange the authorization code for an access token
        $accessToken = getAccessToken($authorizationCode);

        if ($accessToken) {
            // Access token obtained successfully
            return response()->json(['access_token' => $accessToken]);
        } else {
            // Failed to retrieve access token
            return response()->json(['error' => 'Unable to obtain access token.'], 500);
        }
    } else {
        // No authorization code received in the request
        return response()->json(['error' => 'Authorization code not provided.'], 400);
    }
});

Route::get('/apis/ebay-login', action: function () {
    $clientId = 'LevieRos-imsweb-PRD-7abfbb41d-7a45e67e'; // Replace with your client ID
    $redirectUrl = 'https://ims.tecniquality.com/Admin/modules/orders/callback.php'; // Replace with your redirect URL
    $scopes = 'https://api.ebay.com/oauth/api_scope https://api.ebay.com/oauth/api_scope/sell.marketing.readonly https://api.ebay.com/oauth/api_scope/sell.inventory.readonly https://api.ebay.com/oauth/api_scope/sell.account.readonly https://api.ebay.com/oauth/api_scope/sell.fulfillment.readonly';

    $authUrl = "https://auth.ebay.com/oauth2/authorize?client_id={$clientId}&redirect_uri={$redirectUrl}&response_type=code&scope=" . urlencode($scopes);

    echo "<a href='{$authUrl}'>Authorize with eBay</a>";
});

Route::get('/apis/ebay-callback', action: function () {
    require app_path('Helpers/ebay_helpers.php');
    // Check if the 'code' parameter is present in the URL
    echo "Hello";
    if (isset($_GET['code'])) {
        $authorizationCode = $_GET['code']; // Get the authorization code from the URL
        // Call the getAccessToken function to exchange the authorization code for an access token
        $accessToken = getAccessToken($authorizationCode);

        if ($accessToken) {
            // Access token obtained successfully
            return response()->json(['access_token' => $accessToken]);
        } else {
            // Failed to retrieve access token
            return response()->json(['error' => 'Unable to obtain access token.'], 500);
        }
    } else {
        // No authorization code received in the request
        return response()->json(['error' => 'Authorization code not provided.'], 400);
    }
});

use App\Http\Controllers\Ebay\EbayController;

Route::get('/ebay/orders', [EbayController::class, 'fetchOrders']);

use App\Http\Controllers\Amzn\FBACartController;

Route::post('/amzn/fba-cart/add', [FBACartController::class, 'addToCart']);
Route::get('/amzn/fba-cart/list', [FBACartController::class, 'list']);
Route::get('/amzn/fba-cart/get-or-create-cart', [FBACartController::class, 'getOrCreateCart']);
Route::delete('/amzn/fba-cart/remove', [FBACartController::class, 'removeFromCart']);
Route::post('/amzn/fba-cart/commit', [FBACartController::class, 'commitCart']);

use App\Http\Controllers\Amzn\FBAShipmentController;
// localhost:8000
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

Route::get('/amzn/fba-shipment/step9/get_shipment', [FBAShipmentController::class, 'step9a_get_shipment']); // medyo sheeshables
Route::get('/amzn/fba-shipment/step10/print_label', [FBAShipmentController::class, 'step10a_print_label']);

use App\Http\Controllers\TestTableController;
Route::get('/test', [TestTableController::class, 'index']);

use App\Http\Controllers\tblproductController;
Route::get('/products', [tblproductController::class, 'index']);

// Session management routes
Route::get('/keep-alive', [App\Http\Controllers\UserSessionController::class, 'keepAlive'])
    ->middleware('web');

Route::get('/csrf-token', [App\Http\Controllers\UserSessionController::class, 'csrfToken'])
    ->middleware('web');

Route::middleware(['web', \App\Http\Middleware\RefreshSession::class])->group(function () {
    // Your existing routes go here
});

Route::get('/apis/ebay-login', action: function () {
    $clientId = 'Christia-LaravelI-SBX-8f72598d8-73053ea8'; // Replace with your client ID
    $redirectUrl = 'https://ims.tecniquality.com/Admin/modules/orders/callback.php'; // Replace with your redirect URL
    $scopes = 'https://api.ebay.com/oauth/api_scope https://api.ebay.com/oauth/api_scope/sell.marketing.readonly https://api.ebay.com/oauth/api_scope/sell.inventory.readonly https://api.ebay.com/oauth/api_scope/sell.account.readonly https://api.ebay.com/oauth/api_scope/sell.fulfillment.readonly';

    $authUrl = "https://auth.ebay.com/oauth2/authorize?client_id={$clientId}&redirect_uri={$redirectUrl}&response_type=code&scope=" . urlencode($scopes);

    echo "<a href='{$authUrl}'>Authorize with eBay</a>";
});


Route::get('/check-user-privileges', [UserSessionController::class, 'checkUserPrivileges'])->middleware('auth');

// In routes/web.php
Route::post('/refresh-user-session', [UserSessionController::class, 'refreshSession']);

/*Route::get('/keep-alive', function () {
    // Refresh the session
    request()->session()->regenerate();
    return response()->json(['status' => 'ok']);
})->middleware('web');

Route::get('/csrf-token', function () {
    return response()->json(['token' => csrf_token()]);
})->middleware('web'); */

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
});

// Routes for Unreceived scanner
Route::prefix('api/unreceived')->group(function () {
    Route::get('products', [UnreceivedController::class, 'index']);
    Route::get('verify-tracking', [UnreceivedController::class, 'verifyTracking']);
    Route::get('get-next-rpn', [UnreceivedController::class, 'getNextRpn']);
    Route::post('process-scan', [UnreceivedController::class, 'processScan']);
    // Other unreceived-specific endpoints
});

// Routes for Received scanner 
Route::prefix('api/received')->group(function () {
    Route::get('products', [ReceivedController::class, 'index']);
    Route::get('verify-tracking', [ReceivedController::class, 'verifyTracking']);
    Route::post('validate-pcn', [ReceivedController::class, 'validatePcn']); // <-- Now points
    Route::post('process-scan', [ReceivedController::class, 'processScan']);
});


Route::post('api/images/upload', [App\Http\Controllers\ImageUploadController::class, 'upload']);


// Routes Orders
Route::prefix('api/orders')->group(function () {
    Route::get('products', [OrdersController::class, 'index']);
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
    // GET routes
    Route::get('products', [ReturnScannerController::class, 'index']);
    Route::get('stores', [ReturnScannerController::class, 'getStores']);
    Route::get('check-serial', [ReturnScannerController::class, 'checkSerial']);

    // POST routes - REMOVE the withoutMiddleware call to make it consistent with Stockroom
    Route::post('process-scan', [ReturnScannerController::class, 'processScan']);
});


// Routes for Labeling Function 
Route::prefix('api/labeling')->group(function () {
    Route::get('products', [LabelingController::class, 'index']);
});
Route::post('/api/labeling/move-to-validation', [LabelingController::class, 'moveToValidation']);
Route::post('/api/labeling/move-to-stockroom', [LabelingController::class, 'moveToStockroom']);
Route::get('/test-labeling-controller', function () {
    return response()->json([
        'message' => 'LabelingController is accessible',
        'timestamp' => now()
    ]);
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
    // Add new routes for auto dispense functionality
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


// Routes for FNSKU Function 
use App\Http\Controllers\FnskuController;

Route::get('/fnsku-list', [FnskuController::class, 'getFnskuList']);
Route::post('/update-fnsku', [FnskuController::class, 'updateFnsku']);
Route::get('/fnsku', [FnskuController::class, 'index']);
Route::post('/insert-fnsku', [FnskuController::class, 'insertFnsku']);

Route::get('/clone-table-form', [App\Http\Controllers\TableController::class, 'showCloneForm'])->name('clone.table.form');
Route::post('/clone-table', [App\Http\Controllers\TableController::class, 'cloneTable'])->name('clone.table');


// FBM Orders Shipping Label
use App\Http\Controllers\Amzn\OutboundOrders\ShippingLabel\ShippingLabelController;

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

Route::get('/dashboard', [AttendanceController::class, 'attendance'])->name('dashboard.system');


use App\Http\Controllers\Fbmorders\PrintInvoiceController;

Route::post('/fbm-orders-invoice', [PrintInvoiceController::class, 'printInvoice']);
/*
Route::get('/fbm-orders-invoice-test', function () {
    $testPayload = [
        'platform_order_id' => '111-1234567-8900000',
        'platform_order_item_ids' => ['ITEM-1', 'ITEM-2'],
        'action' => 'ViewInvoice', // or 'PrintInvoice'
        'settings' => [
            'displayPrice' => true,
            'testPrint' => false,
            'signatureRequired' => true
        ],
    ];

    $response = Http::post(url('/api/fbm-orders-invoice'), $testPayload);

    return $response->json(); // Show result in browser
});
*/

Route::get('/fbm-orders-invoice-test', function () {
    $controller = new PrintInvoiceController();

    $request = Request::create('/fbm-orders-invoice', 'POST', [
        'platform_order_ids' => ['111-9674483-2472244'],
        'action' => 'PrintInvoice',
        'settings' => [
            'displayPrice' => true,
            'testPrint' => true,
            'signatureRequired' => true
        ],
    ]);

    return $controller->printInvoice($request);
});

Route::post('/fbm-orders-shippinglabel', [PrintInvoiceController::class, 'printshippinglabel']);