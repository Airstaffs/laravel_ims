<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Http\Controllers\Controller;

class TableController extends Controller
{
    /**
     * Show the form for cloning tables
     */
    public function showCloneForm()
    {
        return view('clone-table-form');
    }

    /**
     * Clone the specified table and save user data
     */
    public function cloneTable(Request $request)
    {
        // Get form inputs
        $suffix = $request->input('table_suffix'); // This is the company name
        $username = $request->input('email');
        $password = $request->input('password');
        
        // Validate all inputs
        if (empty($suffix)) {
            return redirect()->back()->with('error', 'Please provide a company name');
        }
        
        if (empty($username)) {
            return redirect()->back()->with('error', 'Please provide a username/email');
        }
        
        if (empty($password)) {
            return redirect()->back()->with('error', 'Please provide a password');
        }
        
        try {
            // First, save the user data to tbluser
            DB::table('tbluser')->insert([
                'username' => $username,
                'password' => bcrypt($password), // Hash the password for security
                'company' => $suffix
            ]);
            
            // Now clone the tables
            
            // First table
            $sourceTable1 = 'tblproducttemp';
            $targetTable1 = $sourceTable1 . $suffix;
            
            // Second table
            $sourceTable2 = 'tblapistemp';
            $targetTable2 = $sourceTable2 . $suffix;

            $sourceTable3 = 'tblfnskutemp';
            $targetTable3 = $sourceTable3 . $suffix;
            
            // Clone the first table structure and data
            DB::statement("CREATE TABLE {$targetTable1} LIKE {$sourceTable1}");
            DB::statement("INSERT INTO {$targetTable1} SELECT * FROM {$sourceTable1}");
            
            // Clone the second table structure and data
            DB::statement("CREATE TABLE {$targetTable2} LIKE {$sourceTable2}");
            DB::statement("INSERT INTO {$targetTable2} SELECT * FROM {$sourceTable2}");

            DB::statement("CREATE TABLE {$targetTable3} LIKE {$sourceTable3}");
            DB::statement("INSERT INTO {$targetTable3} SELECT * FROM {$sourceTable3}");
            
            return redirect()->back()->with('success', "Registration successful. User created and tables cloned.");
        } catch (\Exception $e) {
            return redirect()->back()->with('error', "Operation failed: " . $e->getMessage());
        }
    }
}