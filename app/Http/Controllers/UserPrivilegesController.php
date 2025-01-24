<?php
namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\User;

class UserPrivilegesController extends Controller
{
    public function update(Request $request)
    {
        // Validate the request
        $request->validate([
            'user_id' => 'required|exists:users,id',
            'main_module' => 'required|string',
            'sub_modules' => 'nullable|array',
        ]);

        // Process and update the user privileges in the database
        $user = User::find($request->user_id);
        $user->main_module = $request->main_module;
        $user->sub_modules = $request->sub_modules ?? [];
        $user->save();

        // Redirect back with success message
        return back()->with('success', 'User privileges updated successfully.');
    }

}
