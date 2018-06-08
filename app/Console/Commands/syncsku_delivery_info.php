<?php

namespace App\Console\Commands;

use App\Orders;
use Illuminate\Console\Command;
use App\Jobs\SyncDeliveryInfo;

class syncsku_delivery_info extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'sync:delivery_info';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'sync order delivery info to sku';

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
        $page_size   = 3;
        $days_before = 100;

        $date_condition = date("Y-m-d", strtotime("-" . $days_before . " days"));

        $page_num = ceil(Orders::count() / $page_size);

        if ($page_num > 0) {
            $max_id = 1;
            for ($i = 0; $i < $page_num; $i++) {
                $orders = Orders::where("created_at", ">", $date_condition)
                                ->where("id", ">", $max_id)
                                ->take($page_size)
                                ->get(
                                    [
                                        'id',
                                        'name',
                                        'order_id',
                                        'fulfillment_status',
                                        'order_full_info',
                                        'delivery_status',
                                        'delivery_order_created_at',
                                        'picked_status',
                                        'hold_status',
                                        'created_at',
                                    ]
                                );

                foreach ($orders as $order) {
                    $id = $order['id'];
                    if ($id > $max_id) {
                        $max_id = $id;
                    }
                    $line_items = json_decode($order['order_full_info'], true)['line_items'];
                    if (count($line_items) > 0) {
                        foreach ($line_items as $item) {
                            $sku_name                         = $item['sku'];
                            $job['sku_name']                  = $sku_name;
                            $job['name']                      = $order->name;
                            $job['picked_status']             = $order->picked_status;
                            $job['hold_status']               = $order->hold_status;
                            $job['order_id']                  = $order->order_id;
                            $job['fulfillment_status']        = $order->fulfillment_status;
                            $job['delivery_order_created_at'] = $order->delivery_order_created_at;
                            $job['delivery_status']           = $order->delivery_status;
                            $job['order_created_at']          = date("Y-m-d",strtotime($order->created_at));

                            dispatch(new SyncDeliveryInfo($job));

                        }
                    }
                }

            }
        }

    }
}
