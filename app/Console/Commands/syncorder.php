<?php

namespace App\Console\Commands;

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

        $orders = Orders::where("hold_status", 0)->where('delivery_status', 0)->get(
            [
                'id',
                'name',
                'picked_status',
                'order_id',
                'fulfillment_status',
                'delivery_order_created_at',
            ]
        );


        foreach ($orders as $order) {
            $name     = $order->name;
            $url      = config('app.fetchr_api_basic_url') . "/" . $name . "?reference_type=client_ref";

            $job['id'] = $order->id;
            $job['name'] = $order->name;
            $job['order_id'] = $order->order_id;
            $job['fulfillment_status'] = $order->fulfillment_status;
            $job['delivery_order_created_at'] = $order->delivery_order_created_at;
            $job['url'] = $url;
            $job['location'] = "shenzhen";
            $job['authorization'] = config('app.fetchr_authorization_shenzhen');

            dispatch(new SyncOrderJob($job));
            $job['location'] = 'saudi';
            $job['authorization'] = config('app.fetchr_authorization_saudi');
            dispatch(new SyncOrderJob($job));
        }

    }

}
