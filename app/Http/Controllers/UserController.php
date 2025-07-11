<?php

namespace App\Http\Controllers;
use Illuminate\Support\Facades\DB;
use Illuminate\Http\Request;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Auth;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Schema;
use App\Services\UserLogService;

class UserController extends Controller
{

    protected $userLogService;

    public function __construct(UserLogService $userLogService) {
        $this->userLogService = $userLogService;
    } 
    
    public function showmyprivileges()
    {
        // Get the current user's ID
        $currentUserId = Auth::user()->id;

        // Fetch user privileges
        $myprivileges = DB::table('tbluser')
            ->select(
                'order',
                'unreceived',
                'receiving',
                'labeling',
                'testing',
                'cleaning',
                'packing',
                'fnsku',
                'stockroom',
                'validation',
                'productionarea',
                'returnscanner',
                'fbmorder',
                'notfound',
                'asinoption',
                'houseage',
                'asinlist',
                'printer',
            )
            ->where('id', $currentUserId)
            ->first();

        // Return privileges as JSON
        return response()->json([
            'status' => 'success',
            'message' => 'User privileges retrieved successfully',
            'data' => $myprivileges
        ]);
    }

    public function store(Request $request)
    {
        try {
            $validated = $request->validate([
                'username' => 'required|string|max:255|unique:tbluser,username',
                'password' => 'required|min:6|confirmed',
                'role' => 'required|in:SuperAdmin,SubAdmin,User',
            ]);

            $user = Auth::user();
        
            // Get company data - assuming user has a company relation or attribute
            $companyColumn = $user ? $user->company : '';
    
            User::create([
                'username' => $validated['username'],
                'password' => Hash::make($validated['password']),
                'role' => $validated['role'],
                'company' => $companyColumn,
            ]);

            // Log using service
                    $this->userLogService->log('add user - ' . $validated['username']);
    
            return response()->json([
                'success' => true,
                'message' => 'User added successfully!'
            ]);
        } catch (ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => $e->errors(),
            ], 422);
        } catch (\Exception $e) {
            Log::error('Failed to add user: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to add user. Please try again.'
            ], 500);
        }
    }
        
    public function updatepassword(Request $request)
        {
            $currentUserId = Auth::user()->id; // Get the current user's ID
    
            // Validate the request
            $request->validate([
                'password' => 'required|min:6|confirmed',
            ]);
    
            try {
                // Find the current user by ID
                $user = User::findOrFail($currentUserId);
    
                // Update the user's password
                $user->update([
                    'password' => Hash::make($request->password),
                ]);

                // Log using service
                        $this->userLogService->log('User Update Password');
    
                return back()->with('success', 'Password updated successfully!');
            } catch (\Exception $e) {
                Log::error('Failed to update password: ' . $e->getMessage());
                return back()->with('error', 'Failed to update password. Please try again.');
            }
        }
    
    public function showStoreColumns()
            {
                $user = new User();
                $storeColumns = $user->getStoreColumns();
    
                return response()->json($storeColumns); // Returns the list of store columns as JSON
            }
    
    public function getStoreColumns()
            {
                // Dynamically fetch columns from the 'tbluser' table
                $columns = Schema::getColumnListing('tbluser');  // Get all columns for the 'tbluser' table
                
                // Filter out only the columns that start with 'store_'
                $storeColumns = array_filter($columns, function ($column) {
                    return str_starts_with($column, 'store_');  // Only return columns with the 'store_' prefix
                });
            
                // Return the store columns as a JSON response
                return response()->json(['stores' => array_values($storeColumns)]);
            }
        
        
    // Controller method to get user privileges
    public function getUserPrivileges($userId)
    {
        $selectedUser = User::find($userId);
        $userPrivileges = null;

        if ($selectedUser) {
            // Fetch all columns in the 'tbluser' table starting with 'store_'
            $storeColumns = Schema::getColumnListing('tbluser');
            $storePrivileges = collect($storeColumns)
                ->filter(function ($column) {
                    return str_starts_with($column, 'store_');
                })
                ->map(function ($store) use ($selectedUser) {
                    $storeName = str_replace('store_', '', $store); // Remove 'store_' prefix
                    $storeName = str_replace('_', ' ', $storeName); // Replace underscores with spaces
                    return [
                        'store_column' => $store, // Original column name
                        'store_name' => $storeName, // User-friendly name
                        'is_checked' => (bool) $selectedUser->{$store}, // Check if the privilege is enabled
                    ];
                })
                ->values();

            // Ensure main_module is always in the correct format (lowercase, no spaces)
            $mainModule = $selectedUser->main_module;
            if ($mainModule) {
                // Remove any spaces and convert to lowercase
                $mainModule = strtolower(str_replace(' ', '', $mainModule));
            }

            $userPrivileges = [
                'main_module' => $mainModule,
                'sub_modules' => [
                    'order' => (bool) $selectedUser->order,
                    'unreceived' => (bool) $selectedUser->unreceived,
                    'receiving' => (bool) $selectedUser->receiving,
                    'labeling' => (bool) $selectedUser->labeling,
                    'testing' => (bool) $selectedUser->testing,
                    'cleaning' => (bool) $selectedUser->cleaning,
                    'packing' => (bool) $selectedUser->packing,
                    'fnsku' => (bool) $selectedUser->fnsku,
                    'stockroom' => (bool) $selectedUser->stockroom,
                    'validation' => (bool) $selectedUser->validation,
                    'productionarea' => (bool) $selectedUser->productionarea,
                    'returnscanner' => (bool) $selectedUser->returnscanner,
                    'fbmorder' => (bool) $selectedUser->fbmorder,
                    'notfound' => (bool) $selectedUser->notfound,
                    'asinoption' => (bool) $selectedUser->asinoption,
                    'houseage' => (bool) $selectedUser->houseage,
                    'asinlist' => (bool) $selectedUser->asinlist, 
                    'printer' => (bool) $selectedUser->printer,
                ],
                'privileges_stores' => $storePrivileges, // Pass the processed store privileges
            ];
        }

        return response()->json($userPrivileges);
    }
    
    public function fetchNewlyAddedStoreCol(Request $request)
    {
        // First, let's log that we've received the request
        Log::info('Starting store fetch process', [
            'user_id' => $request->input('user_id'),
            'request_url' => $request->fullUrl()
        ]);
    
        try {
            $stores = []; 
            $userId = $request->input('user_id');
    
            // Let's verify we can connect to the database
            try {
                DB::connection()->getPdo();
                Log::info('Database connection successful');
            } catch (\Exception $e) {
                Log::error('Database connection failed', ['error' => $e->getMessage()]);
                throw new \Exception('Database connection failed: ' . $e->getMessage());
            }
    
            // Check if we can access the schema
            try {
                $storeColumns = Schema::getColumnListing('tbluser');
                Log::info('Successfully retrieved columns', ['columns' => $storeColumns]);
            } catch (\Exception $e) {
                Log::error('Failed to get table columns', ['error' => $e->getMessage()]);
                throw new \Exception('Schema access failed: ' . $e->getMessage());
            }
    
            // Verify user exists if user_id is provided
            if ($userId) {
                $user = User::find($userId);
                if (!$user) {
                    Log::warning('User not found', ['user_id' => $userId]);
                    return response()->json(['error' => 'User not found'], 404);
                }
                Log::info('User found', ['user_id' => $userId]);
            }
    
            // Process the store columns
            $stores = collect($storeColumns)
                ->filter(function ($column) {
                    return str_starts_with($column, 'store_');
                })
                ->map(function ($store) use ($userId, $user) {
                    Log::info('Processing store column', ['store' => $store]);
                    
                    $storeName = str_replace('store_', '', $store);
                    $storeName = str_replace('_', ' ', $storeName);
    
                    // Safely check privileges
                    try {
                        $isChecked = false;
                        if ($userId && isset($user) && method_exists($user, 'privileges_stores')) {
                            $isChecked = $user->privileges_stores->contains($store);
                        }
                    } catch (\Exception $e) {
                        Log::error('Error checking store privileges', [
                            'store' => $store,
                            'error' => $e->getMessage()
                        ]);
                        throw new \Exception('Privilege check failed: ' . $e->getMessage());
                    }
    
                    return [
                        'store_column' => $store,
                        'store_name' => $storeName,
                        'is_checked' => $isChecked,
                    ];
                })
                ->values();
    
            Log::info('Successfully processed stores', ['store_count' => count($stores)]);
            return response()->json(['stores' => $stores]);
    
        } catch (\Exception $e) {
            Log::error('Store fetching failed', [
                'error_message' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'trace' => $e->getTraceAsString()
            ]);
            
            return response()->json([
                'error' => 'Failed to fetch stores',
                'message' => $e->getMessage()
            ], 500);
        }
    }

    public function saveUserPrivileges(Request $request)
{
    try {
        // Typecast user_id to integer before validation
        $request->merge(['user_id' => (int) $request->input('user_id')]);

        // Validate the request
        $data = $request->validate([
            'user_id' => 'required|numeric|exists:tbluser,id',
            'main_module' => 'required|string',
            'sub_modules' => 'array|nullable',
            'privileges_stores' => 'array|nullable',
        ]);

        // Log the request data for debugging
        Log::info('Request Data:', $data);

        // Fetch the user
        $user = User::find($data['user_id']);
        $username = $user->username;

        if (!$user) {
            return response()->json(['success' => false, 'message' => 'User not found']);
        }

        // Define module mapping (display name to database column)
        $moduleMapping = [
            'Order' => 'order',
            'Unreceived' => 'unreceived',
            'Received' => 'receiving',
            'Labeling' => 'labeling',
            'Testing' => 'testing',
            'Cleaning' => 'cleaning',
            'Packing' => 'packing',
            'Stockroom' => 'stockroom',
            'Validation' => 'validation',
            'FNSKU' => 'fnsku',
            'Production Area' => 'productionarea',
            'Return Scanner' => 'returnscanner',
            'FBM Order' => 'fbmorder',
            'Not Found' => 'notfound',
            'ASIN Option' => 'asinoption',
            'Houseage' => 'houseage',
            'ASIN List' => 'asinlist',
            'Printer' => 'printer',
        ];

        // Convert main module from display name to database column name
        $mainModuleDb = null;
        foreach ($moduleMapping as $displayName => $columnName) {
            if (strcasecmp($data['main_module'], $displayName) === 0 || 
                strcasecmp($data['main_module'], str_replace(' ', '', $displayName)) === 0) {
                $mainModuleDb = $columnName;
                break;
            }
        }

        // If no mapping found, try to convert it directly
        if (!$mainModuleDb) {
            $mainModuleDb = strtolower(str_replace(' ', '', $data['main_module']));
        }

        // Update main module
        $user->main_module = $mainModuleDb;

        // Define all possible sub-modules
        $subModules = ['order', 'unreceived', 'receiving', 'labeling', 'testing', 
                      'cleaning', 'packing', 'stockroom', 'validation', 'fnsku', 
                      'productionarea', 'returnscanner', 'fbmorder','notfound','asinoption','houseage','asinlist','printer'];
        
        // First reset all modules to 0
        foreach ($subModules as $module) {
            $user->{$module} = 0;
        }
        
        // Process sub-modules - they're already coming as database column names
        if (!empty($data['sub_modules'])) {
            Log::info('Processing sub_modules:', $data['sub_modules']);
            
            foreach ($data['sub_modules'] as $selectedModule) {
                // The sub_modules are already database column names (e.g., "receiving")
                // so we can use them directly
                if (in_array($selectedModule, $subModules) && $selectedModule !== $mainModuleDb) {
                    $user->{$selectedModule} = 1;
                    Log::info("Enabling sub-module: {$selectedModule}");
                }
            }
        }

        // Always ensure the main module is enabled
        if ($mainModuleDb && in_array($mainModuleDb, $subModules)) {
            $user->{$mainModuleDb} = 1;
        }

        // Handle stores (rest of the code remains the same)
        $storeColumns = DB::select("SHOW COLUMNS FROM tbluser LIKE 'store_%'");
        $storeColumns = array_map(fn($column) => $column->Field, $storeColumns);

        // Reset all store columns to 0
        foreach ($storeColumns as $storeColumn) {
            $user->{$storeColumn} = 0;
        }

        // Enable selected stores
        if (!empty($data['privileges_stores'])) {
            foreach ($data['privileges_stores'] as $store) {
                if (in_array($store, $storeColumns)) {
                    $user->{$store} = 1;
                }
            }
        }

        // Prepare logging information
        $mainModuleDisplay = array_search($mainModuleDb, $moduleMapping) ?: ucfirst($mainModuleDb);
        $enabledSubModules = [];
        $enabledStores = [];

        // Collect enabled sub-modules for logging
        foreach ($subModules as $module) {
            if ($user->{$module} == 1 && $module !== $mainModuleDb) {
                $displayName = array_search($module, $moduleMapping) ?: ucfirst($module);
                $enabledSubModules[] = $displayName;
            }
        }

        // Collect enabled stores
        foreach ($storeColumns as $storeColumn) {
            if ($user->{$storeColumn} == 1) {
                $storeName = str_replace('store_', '', $storeColumn);
                $storeName = str_replace('_', ' ', $storeName);
                $enabledStores[] = ucfirst($storeName);
            }
        }

        // Format the log message
        $logMessage = sprintf(
            'Update Privileges for User %s - Main: %s | Sub-Modules: %s | Stores: %s',
            $username,
            $mainModuleDisplay,
            $enabledSubModules ? implode(', ', $enabledSubModules) : 'None',
            $enabledStores ? implode(', ', $enabledStores) : 'None'
        );

        // Save the user
        $user->save();

        // Log using service
        $this->userLogService->log($logMessage);

        // Prepare response
        $responseSubModules = [];
        foreach ($subModules as $module) {
            if ($user->{$module} == 1 && $module !== $mainModuleDb) {
                $responseSubModules[] = $module;
            }
        }

        return response()->json([
            'success' => true, 
            'message' => 'User privileges updated successfully!',
            'main_module' => $mainModuleDb,
            'sub_modules' => $responseSubModules
        ]);
        
    } catch (\Exception $e) {
        Log::error('Error saving user privileges:', [
            'error' => $e->getMessage(),
            'trace' => $e->getTraceAsString()
        ]);
        return response()->json(['success' => false, 'message' => $e->getMessage()]);
    }
}     



  public function refreshUserSession(Request $request)
{
    try {
        $user = Auth::user();
        
        if (!$user) {
            return response()->json(['success' => false, 'message' => 'User not authenticated']);
        }
        
        // Get fresh user data from database
        $freshUser = User::find($user->id);
        
        if (!$freshUser) {
            return response()->json(['success' => false, 'message' => 'User not found']);
        }
        
        // Define all possible modules as stored in the database
        // 🔴 FIXED: Removed 'fbashipmentinbound' which doesn't exist in database
        $modules = [
            'order', 'unreceived', 'receiving', 'labeling', 'testing', 
            'cleaning', 'packing', 'stockroom', 'validation', 'fnsku', 
            'productionarea', 'returnscanner', 'fbmorder', 
            'notfound', 'asinoption', 'houseage', 'asinlist', 'printer'
        ];
        
        Log::info('=== REFRESH SESSION DEBUG ===', [
            'user_id' => $freshUser->id,
            'main_module_raw' => $freshUser->main_module,
            'printer_value' => $freshUser->printer,
            'printer_type' => gettype($freshUser->printer),
            'available_modules' => $modules
        ]);
        
        // Get main module and ensure it's lowercase with no spaces
        $mainModule = $freshUser->main_module;
        if ($mainModule) {
            // Remove any spaces and convert to lowercase
            $mainModule = strtolower(str_replace(' ', '', $mainModule));
        }
        
        Log::info('Main module processed:', [
            'original' => $freshUser->main_module,
            'processed' => $mainModule
        ]);
        
        // Get active modules - ensure all are lowercase for consistency and exclude main module
        $activeModules = [];
        foreach ($modules as $module) {
            // Check if property exists and is enabled
            if (property_exists($freshUser, $module) && $freshUser->{$module} == 1 && $module !== $mainModule) {
                $activeModules[] = strtolower($module);
                Log::info("✓ Added module to activeModules: {$module}");
            } else {
                Log::info("✗ Skipped module: {$module}", [
                    'exists' => property_exists($freshUser, $module),
                    'value' => $freshUser->{$module} ?? 'property_not_found',
                    'enabled' => ($freshUser->{$module} ?? 0) == 1,
                    'not_main' => $module !== $mainModule
                ]);
            }
        }
        
        Log::info('Active modules before final filtering:', $activeModules);
        
        // Ensure main module is not duplicated in sub-modules
        $activeModules = array_filter($activeModules, function($mod) use ($mainModule) {
            return $mod !== $mainModule;
        });
        
        // Reset array keys
        $activeModules = array_values($activeModules);
        
        Log::info('Final active modules:', $activeModules);
        
        // Save to session
        session()->forget(['main_module', 'sub_modules']);
        session(['main_module' => $mainModule]);
        session(['sub_modules' => $activeModules]);
        session()->save();
        
        return response()->json([
            'success' => true,
            'message' => 'User session refreshed successfully',
            'main_module' => $mainModule,
            'sub_modules' => $activeModules,
            'debug' => [
                'fresh_main_module' => $freshUser->main_module,
                'processed_main_module' => $mainModule,
                'all_enabled_modules' => array_filter($modules, function($mod) use ($freshUser) {
                    return property_exists($freshUser, $mod) && $freshUser->{$mod} == 1;
                }),
                'printer_debug' => [
                    'exists' => property_exists($freshUser, 'printer'),
                    'value' => $freshUser->printer ?? 'not_found',
                    'enabled' => ($freshUser->printer ?? 0) == 1
                ]
            ]
        ]);
    } catch (\Exception $e) {
        Log::error('Failed to refresh user session: ' . $e->getMessage(), [
            'file' => $e->getFile(),
            'line' => $e->getLine(),
            'user_id' => Auth::id()
        ]);
        return response()->json([
            'success' => false, 
            'message' => 'Failed to refresh user session: ' . $e->getMessage()
        ]);
    }
}


        
    public function createdusers()
        {
            $user = User::select('id', 'username', 'role', 'created_at')
                ->orderBy('created_at', 'desc')
                ->get();
        
            // Return privileges as JSON
        return response()->json([
            'status' => 'success',
            'message' => 'Users retrieved successfully',
            'data' => $user
        ]);
        }

    public function update(Request $request, $id)
        {
            $request->validate([
                'username' => 'required|string|max:255|unique:tbluser,username,'.$id,
                'password' => 'nullable|min:6',
                'role' => 'required|in:SuperAdmin,SubAdmin,User',
            ]);
        
            try {
                $user = User::findOrFail($id);
                
                $updateData = [
                    'username' => $request->username,
                    'role' => $request->role,
                ];
        
                if ($request->filled('password')) {
                    $updateData['password'] = Hash::make($request->password);
                }
        
                $user->update($updateData);

                // Log using service
                        $this->userLogService->log('Update data of User - ' . $request->username);
        
                return response()->json([
                    'success' => true,
                    'message' => 'User updated successfully!'
                ]);
            } catch (\Exception $e) {
                Log::error('Failed to update user: ' . $e->getMessage());
                return response()->json([
                    'success' => false,
                    'message' => 'Failed to update user. Please try again.'
                ]);
            }
        }
        
        public function destroy($id)
        {
            try {
                // Get the user and username before deleting
                $user = User::findOrFail($id);
                $username = $user->username; // Store username for logging
        
                // Delete the user
                $user->delete();
        
                // Log using service
                $this->userLogService->log('Deleted User - ' . $username);
        
                return response()->json([
                    'success' => true,
                    'message' => 'User deleted successfully!'
                ]);
            } catch (\Exception $e) {
                Log::error('Failed to delete user: ' . $e->getMessage());
                return response()->json([
                    'success' => false,
                    'message' => 'Failed to delete user. Please try again.'
                ]);
            }
        }

    public function updateTimezone(Request $request)
    {
        $userId = session('userid');
        $autoSync = $request->has('auto_sync');
        $timezone = $request->input('usertimezone', 'UTC');

        $tzSetting = json_encode([
            'auto_sync' => $autoSync,
            'usertimezone' => $timezone,
        ]);

        DB::table('tbluser')->where('id', $userId)->update([
            'timezone_setting' => $tzSetting
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Timezone updated successfully!',
        ]);
    }

        public function showTimezoneSettings(Request $request)
        {
            $userId = session('userid');

            $settingJson = DB::table('tbluser')->where('id', $userId)->value('timezone_setting');
            $setting = json_decode($settingJson, true) ?? ['auto_sync' => true, 'usertimezone' => 'UTC'];

        return view('dashboard.Systemdashboard',  [
                'timezone_setting' => $setting
            ]);
        }

        
}