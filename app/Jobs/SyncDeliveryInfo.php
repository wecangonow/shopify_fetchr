<?php

namespace App\Jobs;

use App\SkuAnalysis;
use Illuminate\Bus\Queueable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Support\Facades\Log;

class SyncDeliveryInfo implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected $task;

    /**
     * Create a new job instance.
     *
     * @return void
     */
    public function __construct(array $task)
    {
        //
        $this->task = $task;
    }

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle()
    {
        //
        $sku_name                  = $this->task['sku_name'];
        $order_id                  = $this->task['order_id'];
        $name                      = $this->task['name'];
        $picked_status             = $this->task['picked_status'];
        $hold_status               = $this->task['hold_status'];
        $fulfillment_status        = $this->task['fulfillment_status'];
        $delivery_order_created_at = $this->task['delivery_order_created_at'];
        $delivery_status           = $this->task['delivery_status'];
        $order_created_at          = $this->task['order_created_at'];

        $count = SkuAnalysis::where("sku_name", $sku_name)->where("order_id", $order_id)->count();

        $delivery_order_created_at = trim($delivery_order_created_at) ?
            trim($delivery_order_created_at) : date("Y-m-d", strtotime('1970-11-11'));

        if ($count == 0) {
            $sku_analysis                            = new SkuAnalysis();
            $sku_analysis->name                      = $name;
            $sku_analysis->order_id                  = $order_id;
            $sku_analysis->sku_name                  = $sku_name;
            $sku_analysis->picked_status             = $picked_status;
            $sku_analysis->hold_status               = $hold_status;
            $sku_analysis->fulfillment_status        = $fulfillment_status;
            $sku_analysis->delivery_order_created_at = $delivery_order_created_at;
            $sku_analysis->delivery_status           = $delivery_status;
            $sku_analysis->order_created_at          = $order_created_at;

            $sku_analysis->save();

        }
        else {
            SkuAnalysis::where("sku_name", $sku_name)->where("order_id", $order_id)->update(
                [
                    'picked_status'             => $picked_status,
                    'hold_status'               => $hold_status,
                    'fulfillment_status'        => $fulfillment_status,
                    'delivery_order_created_at' => $delivery_order_created_at,
                    'delivery_status'           => $delivery_status,
                ]
            );
            Log::info("update!");
        }

        Log::info($delivery_order_created_at);
    }
}
