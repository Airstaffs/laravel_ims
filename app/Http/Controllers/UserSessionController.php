<?php
namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Session;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Auth;

class UserSessionController extends Controller
{
    /**
     * Check and return current user privileges
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function checkUserPrivileges()
    {
        try {
            $user = Auth::user();
            if (!$user) {
                return response()->json(['error' => 'Not authenticated'], 401);
            }
    
            // Get fresh user data with no cache
            $user = User::find($user->id)->fresh();
    
            // Get main module
            $mainModule = $user->main_module;
    
            // Get enabled modules by checking database columns
            $subModules = [];
    
            // Directly check the boolean columns
            if ($user->order) $subModules[] = 'order';
            if ($user->unreceived) $subModules[] = 'unreceived';
            if ($user->receiving) $subModules[] = 'receiving';
            if ($user->labeling) $subModules[] = 'labeling';
            if ($user->testing) $subModules[] = 'testing';
            if ($user->cleaning) $subModules[] = 'cleaning';
            if ($user->packing) $subModules[] = 'packing';
            if ($user->stockroom) $subModules[] = 'stockroom';
            if ($user->validation) $subModules[] = 'validation';
            if ($user->productionarea) $subModules[] = 'productionarea';
            if ($user->fnsku) $subModules[] = 'fnsku';
            if ($user->notfound) $subModules[] = 'notfound';
            if ($user->asinoption) $subModules[] = 'asinoption';
            if ($user->houseage) $subModules[] = 'houseage';
            if ($user->asinlist) $subModules[] = 'asinlist';
              // 🔴 ADDED: Check printer module explicitly
            if ($user->printer) {
                $subModules[] = 'printer';
                Log::info('Printer module added to user privileges', [
                    'user_id' => $user->id, 
                    'printer_value' => $user->printer
                ]);
            } 
            // Important: Make explicit check for returnscanner and log for debugging
            if ($user->returnscanner) {
                $subModules[] = 'returnscanner';
                Log::info('Return Scanner added to user privileges', [
                    'user_id' => $user->id, 
                    'returnscanner_value' => $user->returnscanner
                ]);
            }

             // Important: Make explicit check for fbmorder and log for debugging
            if ($user->fbmorder) {
                $subModules[] = 'fbmorder';
            }
    
            // Update session with fresh data
            Session::forget('main_module');
            Session::forget('sub_modules');
            Session::put('main_module', strtolower($mainModule));
            Session::put('sub_modules', array_map('strtolower', $subModules));
            Session::save();
    
            // Debugging log (keep this for server logs, but don't return in response)
            Log::info('Updated user privileges and session', [
                'user_id' => $user->id,
                'main_module' => $mainModule,
                'sub_modules' => $subModules,
                'session_data' => [
                    'main_module' => Session::get('main_module'),
                    'sub_modules' => Session::get('sub_modules')
                ]
            ]);
    
            return response()->json([
                'success' => true,
                'main_module' => strtolower($mainModule),
                'sub_modules' => array_map('strtolower', $subModules),
                'modules' => [
                    'order' => 'Order',
                    'unreceived' => 'Unreceived',
                    'receiving' => 'Received',
                    'labeling' => 'Labeling',
                    'testing' => 'Testing',
                    'cleaning' => 'Cleaning',
                    'packing' => 'Packing',
                    'stockroom' => 'Stockroom',
                    'validation' => 'Validation',
                //    'fnsku' => 'FNSKU',
                    'productionarea'=>'Production Area',
                    'returnscanner' => 'Return Scanner',
                    'fbmorder' => 'FBM Order',
                    'notfound' => 'Not Found',
                    'asinoption' => 'ASIN Option',
                    'houseage'=> 'Houseage',
                    'asinlist' => 'ASIN List',
                    'printer' => 'Printer'
                ]
            ]);
    
        } catch (\Exception $e) {
            Log::error('Error checking privileges', [
                'error' => $e->getMessage(),
                'user_id' => Auth::id()
            ]);
    
            return response()->json([
                'error' => 'Failed to check privileges',
                'message' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Refresh user session
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function refreshSession(Request $request)
    {
        try {
            $user = Auth::user();
            if (!$user) {
                return response()->json(['error' => 'Not authenticated'], 401);
            }

            // Force session regenerate
            $request->session()->regenerate();
            
            // Update last activity timestamps
            $request->session()->put('last_activity', time());
            $request->session()->put('session_regenerated', time());
            
            // Re-fetch privileges
            return $this->checkUserPrivileges();
        } catch (\Exception $e) {
            Log::error('Error refreshing session', [
                'error' => $e->getMessage(),
                'user_id' => Auth::id()
            ]);

            return response()->json([
                'error' => 'Failed to refresh session',
                'message' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Keep user session alive - SIMPLIFIED to prevent JSON pollution
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
   public function keepAlive(Request $request)
{
    if (!Auth::check()) {
        return response()->json(['authenticated' => false], 401);
    }
    
    try {
        // Update last activity timestamp
        $request->session()->put('last_activity', time());
        
        // Only regenerate session after half the lifetime has passed
        $halfLifetime = (config('session.lifetime') * 60) / 2;
        $lastRegenerated = session('session_regenerated', 0);
        
        if ((time() - $lastRegenerated) > $halfLifetime) {
            $request->session()->regenerate();
            $request->session()->put('session_regenerated', time());
            
            // Log regeneration for server logs only
            Log::info('Session regenerated during keep-alive', [
                'user_id' => Auth::id(),
                'new_session_id' => session()->getId()
            ]);
        }
        
        // CLEAN RESPONSE - no debug info
        return response()->json([
            'status' => 'alive',
            'message' => 'Session extended'
        ]);
        
    } catch (\Exception $e) {
        Log::error('Error in keepAlive: ' . $e->getMessage(), [
            'exception' => $e
        ]);
        
        return response()->json([
            'status' => 'error',
            'message' => 'Failed to extend session'
        ], 500);
    }
}
    
    /**
     * Get a fresh CSRF token
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function csrfToken(Request $request)
    {
        return response()->json([
            'token' => csrf_token()
        ]);
    }
}