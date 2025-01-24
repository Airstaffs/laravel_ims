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

class UserController extends Controller
{
    public function store(Request $request)
    {
        $request->validate([
            'username' => 'required|string|max:255|unique:tbluser,username',
            'password' => 'required|min:6|confirmed',
            'role' => 'required|in:SuperAdmin,SubAdmin,User',
        ]);

        try {
            User::create([
                'username' => $request->username,
                'password' => Hash::make($request->password),
                'role' => $request->role,
            ]);

            return back()->with('success', 'User added successfully!');
        } catch (\Exception $e) {
            Log::error('Failed to add user: ' . $e->getMessage());
            return back()->with('error', 'Failed to add user. Please try again.');
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

        $userPrivileges = [
            'main_module' => $selectedUser->main_module,
            'sub_modules' => [
                'Order' => (bool) $selectedUser->order,
                'Unreceived' => (bool) $selectedUser->unreceived,
                'Receiving' => (bool) $selectedUser->receiving,
                'Labeling' => (bool) $selectedUser->labeling,
                'Testing' => (bool) $selectedUser->testing,
                'Cleaning' => (bool) $selectedUser->cleaning,
                'Packing' => (bool) $selectedUser->packing,
                'Stockroom' => (bool) $selectedUser->stockroom,
            ],
            'privileges_stores' => $storePrivileges, // Pass the processed store privileges
        ];
    }

    return response()->json($userPrivileges);
<<<<<<< HEAD
=======
}


public function fetchNewlyAddedStoreCol(Request $request)
{
    $stores = []; // Initialize the store list
    $userId = $request->input('user_id'); // Get selected user ID

    // Fetch the stores dynamically from the schema
    $storeColumns = Schema::getColumnListing('tbluser');
    $stores = collect($storeColumns)
        ->filter(function ($column) {
            return str_starts_with($column, 'store_');
        })
        ->map(function ($store) use ($userId) {
            $storeName = str_replace('store_', '', $store); // Remove 'store_' prefix
            $storeName = str_replace('_', ' ', $storeName); // Replace underscores with spaces
            
            // Check if the user has privileges for this store
            $isChecked = $userId ? \App\Models\User::find($userId)->privileges_stores->contains($store) : false;

            return [
                'store_column' => $store, // Original column name
                'store_name' => $storeName, // User-friendly name
                'is_checked' => $isChecked, // Whether the store is checked for the user
            ];
        })
        ->values();

    return response()->json(['stores' => $stores]);
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
            if (!$user) {
                return response()->json(['success' => false, 'message' => 'User not found']);
            }
            Log::info('Fetched User:', ['user' => $user]);
    
            // Update main module
            $user->main_module = $data['main_module'];
    
            // Update sub-modules dynamically
            $subModules = ['order', 'unreceived', 'receiving', 'labeling', 'testing', 'cleaning', 'packing', 'stockroom']; // Ensure these modules are valid
            foreach ($subModules as $module) {
                $user->{$module} = in_array(ucfirst($module), $data['sub_modules'] ?? []) ? 1 : 0;
            }
    
            // Fetch all store columns dynamically
            $storeColumns = DB::select("SHOW COLUMNS FROM tbluser LIKE 'store_%'");
            $storeColumns = array_map(fn($column) => $column->Field, $storeColumns);
    
            // Log store columns
            Log::info('Store Columns:', $storeColumns);
    
            // Reset all store columns to 0
            foreach ($storeColumns as $storeColumn) {
                $user->{$storeColumn} = 0;
            }
    
            // Enable selected stores
            if (!empty($data['privileges_stores'])) {
                foreach ($data['privileges_stores'] as $store) {
                    if (in_array($store, $storeColumns)) {
                        $user->{$store} = 1;
                    } else {
                        Log::warning("Store column '{$store}' does not exist in tbluser.");
                    }
                }
            }
    
            // Save the user privileges
            $user->save();
    
            return response()->json(['success' => true, 'message' => 'User privileges updated successfully!']);
        } catch (\Illuminate\Validation\ValidationException $e) {
            Log::error('Validation Error:', ['errors' => $e->errors()]);
            return response()->json([
                'success' => false,
                'message' => 'Validation failed.',
                'errors' => $e->errors(),
            ]);
        } catch (\Exception $e) {
            Log::error('Error saving user privileges:', ['error' => $e->getMessage()]);
            return response()->json(['success' => false, 'message' => $e->getMessage()]);
        }
    }        
>>>>>>> 6f431f588e2718cfcc8329af616cfe16e4ab199d
}


public function fetchNewlyAddedStoreCol(Request $request)
{
    $stores = []; // Initialize the store list
    $userId = $request->input('user_id'); // Get selected user ID

    // Fetch the stores dynamically from the schema
    $storeColumns = Schema::getColumnListing('tbluser');
    $stores = collect($storeColumns)
        ->filter(function ($column) {
            return str_starts_with($column, 'store_');
        })
        ->map(function ($store) use ($userId) {
            $storeName = str_replace('store_', '', $store); // Remove 'store_' prefix
            $storeName = str_replace('_', ' ', $storeName); // Replace underscores with spaces
            
            // Check if the user has privileges for this store
            $isChecked = $userId ? \App\Models\User::find($userId)->privileges_stores->contains($store) : false;

            return [
                'store_column' => $store, // Original column name
                'store_name' => $storeName, // User-friendly name
                'is_checked' => $isChecked, // Whether the store is checked for the user
            ];
        })
        ->values();

    return response()->json(['stores' => $stores]);
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
            if (!$user) {
                return response()->json(['success' => false, 'message' => 'User not found']);
            }
            Log::info('Fetched User:', ['user' => $user]);
    
            // Update main module
            $user->main_module = $data['main_module'];
    
            // Update sub-modules dynamically
            $subModules = ['order', 'unreceived', 'receiving', 'labeling', 'testing', 'cleaning', 'packing', 'stockroom']; // Ensure these modules are valid
            foreach ($subModules as $module) {
                $user->{$module} = in_array(ucfirst($module), $data['sub_modules'] ?? []) ? 1 : 0;
            }
    
            // Fetch all store columns dynamically
            $storeColumns = DB::select("SHOW COLUMNS FROM tbluser LIKE 'store_%'");
            $storeColumns = array_map(fn($column) => $column->Field, $storeColumns);
    
            // Log store columns
            Log::info('Store Columns:', $storeColumns);
    
            // Reset all store columns to 0
            foreach ($storeColumns as $storeColumn) {
                $user->{$storeColumn} = 0;
            }
    
            // Enable selected stores
            if (!empty($data['privileges_stores'])) {
                foreach ($data['privileges_stores'] as $store) {
                    if (in_array($store, $storeColumns)) {
                        $user->{$store} = 1;
                    } else {
                        Log::warning("Store column '{$store}' does not exist in tbluser.");
                    }
                }
            }
    
            // Save the user privileges
            $user->save();
    
            return response()->json(['success' => true, 'message' => 'User privileges updated successfully!']);
        } catch (\Illuminate\Validation\ValidationException $e) {
            Log::error('Validation Error:', ['errors' => $e->errors()]);
            return response()->json([
                'success' => false,
                'message' => 'Validation failed.',
                'errors' => $e->errors(),
            ]);
        } catch (\Exception $e) {
            Log::error('Error saving user privileges:', ['error' => $e->getMessage()]);
            return response()->json(['success' => false, 'message' => $e->getMessage()]);
        }
    }        
}