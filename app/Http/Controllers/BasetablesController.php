<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Routing\Controller;

class BasetablesController extends Controller
{
    protected $company;
    protected $tablePrefix = 'tbl';
    
    // Common table names - simplified to use just single tables
    protected $productTable;
    protected $fnskuTable;       // Single FNSKU table for all stores with storename column
    protected $asinTable;        // Single ASIN table
    protected $lpnTable;
    protected $itemProcessHistoryTable;
    protected $addItemStockroomLogsTable;
    protected $doneShippingTable;
    protected $capturedImagesTable;   // Added for image management
    protected $rpnStickerTable;   // Added for image management
    
    /**
     * Constructor to set up company from the authenticated user
     */
    public function __construct()
    {
        // Initialize tables immediately instead of in middleware
        $this->initializeTables();
        
        // Use parent::middleware to avoid IDE warnings
        parent::middleware(function ($request, $next) {
            try {
                // Re-initialize tables in case auth context changed
                $this->initializeTables();
                
                return $next($request);
            } catch (\Exception $e) {
                // Log any errors during initialization
                Log::error('Error in BasetablesController middleware: ' . $e->getMessage(), [
                    'trace' => $e->getTraceAsString()
                ]);
                return $next($request);
            }
        });
    }
    
    /**
     * Initialize all table names
     */
    protected function initializeTables()
    {
        try {
            // Get the company from the logged-in user
            $this->company = $this->getCompanyFromUser();
            
            // Log the company for debugging
            Log::debug('Company from user: ' . $this->company);
            
            // Initialize common table names - simplified table structure
            $this->productTable = $this->getTableName('product');
            $this->fnskuTable = $this->getTableName('fnsku');        // Single FNSKU table
            $this->asinTable = $this->getTableName('asin');
            $this->lpnTable = $this->getTableName('lpn');
            $this->itemProcessHistoryTable = $this->getTableName('itemprocesshistory');
            $this->addItemStockroomLogsTable = $this->getTableName('additemstockroomlogs');
            $this->doneShippingTable = $this->getTableName('doneshipping');
            $this->capturedImagesTable = $this->getTableName('capturedimages'); // Initialize captured images table
            
            $this->rpnStickerTable = $this->getTableName('rpnsticker');
            
            // Log table names for debugging
            Log::debug('Table names: ', [
                'productTable' => $this->productTable,
                'fnskuTable' => $this->fnskuTable,
                'asinTable' => $this->asinTable,
                'capturedImagesTable' => $this->capturedImagesTable,
                'rpnStickerTable' => $this->rpnStickerTable
            ]);
            
        } catch (\Exception $e) {
            Log::error('Error initializing tables: ' . $e->getMessage(), [
                'trace' => $e->getTraceAsString()
            ]);
        }
    }
    
    /**
     * Get company name from the logged-in user
     * 
     * @return string The company name or empty string
     */
    protected function getCompanyFromUser()
    {
        if (Auth::check()) {
            $user = Auth::user();
            $company = $user->company ?? '';
            Log::debug('User company: ' . $company);
            return $company;
        }
        
        // If no user is authenticated, return empty string
        // This might happen in service classes
        Log::warning('No authenticated user found for company determination');
        return '';
    }
    
    /**
     * Get the dynamic table name based on the base name and company
     * 
     * @param string $baseTable The base table name without prefix
     * @return string The full table name with company suffix
     */
    protected function getTableName($baseTable)
    {
        // If company is empty, you might want to use a default or handle this case
        $company = $this->company ?: '';
        $tableName = $this->tablePrefix . $baseTable . $company;
        Log::debug('Generated table name: ' . $tableName . ' from base: ' . $baseTable . ' with company: ' . $company);
        return $tableName;
    }
    
    /**
     * Log an error with context information
     *
     * @param string $message The error message
     * @param \Exception $exception The exception object
     * @param array $additionalContext Additional context to log
     */
    protected function logError($message, $exception, $additionalContext = [])
    {
        $context = array_merge([
            'company' => $this->company,
            'error' => $exception->getMessage(),
            'trace' => $exception->getTraceAsString()
        ], $additionalContext);
        
        Log::error($message, $context);
    }
}