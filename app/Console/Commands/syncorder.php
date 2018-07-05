<?php

namespace App\Console\Commands;

use App\Jobs\SyncYokesiOrderJob;
use Illuminate\Console\Command;
use App\Orders;
use Log;
use App\Jobs\SyncOrderJob;

class SyncOrder extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'sync:orders';
    
    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'sync shopify order delivery status';
    
    /**
     * Create a new command instance.
     *
     * @return void
     */
    public function __construct()
    {
        parent::__construct();
    }
    
    /**
     * Execute the console command.
     *
     * @return mixed
     */
    public function handle()
    {
        
        $orders = Orders::where('delivery_status', 0)->orWhere('delivery_status', 2)
                        ->get(
                            [
                                'id',
                                'sku',
                                'client_ref',
                                'tracking_no',
                                'company_name',
                                'num',
                                'inventory_plus_flag',
                                'inventory_reduce_flag',
                            ]
                        );
        
        foreach ($orders as $order) {
            $name = $order->client_ref;
            $url  = config('app.fetchr_api_basic_url') . "/" . $name . "?reference_type=client_ref";
            
            $job['id']                    = $order->id;
            $job['sku']                   = $order->sku;
            $job['num']                   = $order->num;
            $job['tracking_no']           = $order->tracking_no;
            $job['order_id']              = $order->client_ref;
            $job['url']                   = $url;
            $job['inventory_plus_flag']   = $order->inventory_plus_flag;
            $job['inventory_reduce_flag'] = $order->inventory_reduce_flag;
            
            $job['location']      = "guangzhou";
            $job['authorization'] = config('app.fetchr_authorization_guangzhou');
            
            dispatch(new SyncOrderJob($job));
            
        }
        
    }
    
}
