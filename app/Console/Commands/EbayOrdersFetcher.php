<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use App\Http\Controllers\Ebay\EbayController;
use Illuminate\Http\Request;

class EbayOrdersFetcher extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'ebay:fetch-loop';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Continuously fetch eBay orders every 8 minutes';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        // Set unlimited execution time for the command itself
        set_time_limit(0);
        ini_set('max_execution_time', 0);
        
        // Prevent script from timing out
        ignore_user_abort(true);
        
        $this->info('eBay Order Fetcher started. Press Ctrl+C to stop.');
        
        // Continuous loop
        while (true) {
            $startTime = now();
            $this->info('Fetching eBay orders at: ' . $startTime->format('Y-m-d H:i:s'));
            
            try {
                // Create a request object
                $request = new Request();
                
                // Get the controller instance
                $controller = app(EbayController::class);
                
                // Call the fetchOrders method directly
                $response = $controller->fetchOrders($request);
                
                // Check if we got a response
                if ($response) {
                    $this->info('Orders fetched successfully');
                    Log::info('eBay orders fetched successfully via direct controller call');
                    
                    // If you want to log the number of orders processed
                    if (is_object($response) && method_exists($response, 'getContent')) {
                        $content = json_decode($response->getContent(), true);
                        $orderCount = count($content['processed_orders'] ?? []);
                        $this->info("Processed $orderCount orders");
                    }
                } else {
                    $this->error('No response received from controller');
                    Log::error('No response received from eBay controller');
                }
            } catch (\Exception $e) {
                $this->error('Exception occurred: ' . $e->getMessage());
                Log::error('Exception in eBay fetcher loop: ' . $e->getMessage());
            }
            
            $endTime = now();
            $executionTime = $endTime->diffInSeconds($startTime);
            $this->info("Execution completed in $executionTime seconds");
            
            $this->info('Sleeping for 15 minutes...');
            
            // Sleep for 8 minutes (480 seconds)
            sleep(900);
        }
    }
}