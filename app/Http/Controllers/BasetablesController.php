<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Routing\Controller; // Make sure we're extending the right class

class BasetablesController extends Controller
{
    protected $company;
    protected $tablePrefix = 'tbl';
    
    // Common table names
    protected $productTable;
    protected $fnskuTable;
    protected $fnskuAllRenewedTable;
    protected $masterAsinTable; // Make sure this matches what StockroomController expects
    protected $lpnTable;
    protected $itemProcessHistoryTable;
    protected $addItemStockroomLogsTable;
    protected $doneShippingTable;
    
    /**
     * Constructor to set up company from the authenticated user
     */
    public function __construct()
    {
        // Use this instead of $this->middleware to avoid IDE warnings
        parent::middleware(function ($request, $next) {
            try {
                // Get the company from the logged-in user
                $this->company = $this->getCompanyFromUser();
                
                // Debug log the company to find any issues
                Log::debug('Company from user: ' . $this->company);
                
                // Initialize common table names - Make sure these match what's expected in StockroomController
                $this->productTable = $this->getTableName('product');
                $this->fnskuTable = $this->getTableName('fnsku');
                $this->fnskuAllRenewedTable = $this->getTableName('masterfnskuAllrenewed');
                $this->masterAsinTable = $this->getTableName('masterasin'); // This was 'asin' before, changing to match usage
                $this->lpnTable = $this->getTableName('lpn');
                $this->itemProcessHistoryTable = $this->getTableName('itemprocesshistory');
                $this->addItemStockroomLogsTable = $this->getTableName('additemstockroomlogs');
                $this->doneShippingTable = $this->getTableName('doneshipping');
                
                // Debug log the generated table names
                Log::debug('Table names: ', [
                    'fnskuTable' => $this->fnskuTable, 
                    'masterAsinTable' => $this->masterAsinTable
                ]);
                
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
     * Get company name from the logged-in user
     * 
     * @return string The company name or empty string
     */
    protected function getCompanyFromUser()
    {
        if (Auth::check()) {
            $user = Auth::user();
            return $user->company ?? '';
        }
        
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
        $tableName = $this->tablePrefix . $baseTable . $this->company;
        Log::debug('Generated table name: ' . $tableName . ' from base: ' . $baseTable);
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