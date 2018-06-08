<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use GuzzleHttp\Client;
use Illuminate\Support\Facades\Log;
use App\Orders;
use App\Products;
use App\InventoryHistory;
use Illuminate\Support\Facades\DB;

class SyncOrderJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;
    
    protected $task;
    
    /**
     * Create a new job instance.
     * git init*
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
        $client        = new Client();
        $url           = $this->task['url'];
        $id            = $this->task['id'];
        $sku           = $this->task['sku'];
        $order_id      = $this->task['order_id'];
        $num           = $this->task['num'];
        $location      = $this->task['location'];
        $authorization = $this->task['authorization'];
        $plus_flag     = $this->task['inventory_plus_flag'];
        $reduce_flag   = $this->task['inventory_reduce_flag'];
        
        Log::info("Fetchr url is " . $url);
        
        try {
            $res = $client->request(
                "GET",
                $url,
                ['headers' => ['Authorization' => $authorization]]
            );
            
            Log::info("code: " . $res->getStatusCode());
            
            if ($res->getStatusCode() == "200") {
                
                $contents  = $res->getBody()->getContents();
                $track_arr = json_decode($contents, true);
                
                if (isset($track_arr['order_information']) && isset($track_arr['tracking_information'])) {
                    
                    $track_no   = $track_arr['order_information']['tracking_no'];
                    $track_info = $track_arr['tracking_information'];
                    
                    $count = count($track_info);
                    
                    if (count($track_info) > 0) {
                        
                        $newest_info_status_name = $track_info[$count - 1]['status_name'];
                        $newest_info_status_code = $track_info[$count - 1]['status_code'];
                        $newest_info_date        = $track_info[$count - 1]['status_date_local'];
                        
                        Log::info("status: " . $newest_info_status_name);
                        $update_order                        = Orders::find($id);
                        
                        $update_order->last_step = $newest_info_status_name . "-" . $newest_info_status_code;
                        $update_order->last_step_time = $newest_info_date;
                        $update_order->save();
                        foreach ($track_info as $info) {
                            $status_code = $info['status_code'];
                            $status_date = date("Y-m-d", strtotime($info['status_date']));
                            if ($status_code == "UPL" && !$reduce_flag) {
                                $update_order                  = Orders::find($id);
                                $update_order->inventory_reduce_flag = 1;
                                $update_order->save();
                                Log::info("order_id $order_id delivery order created at " . $status_date);
                                //TODO  更新sku的当地库存
                                $this->sync_inventory($id, $sku, $location, "UPL", $track_no, $num);
                            }
                            
                            if ($status_code == "DLV") {
                                $update_order                  = Orders::find($id);
                                $update_order->delivery_status = 1;
                                $update_order->save();
                                Log::info("order_id $order_id delivery finished at " . $status_date);
                            }
                            
                            if ($status_code == "SLV") {
                                $update_order                  = Orders::find($id);
                                $update_order->delivery_status = 2;
                                $update_order->save();
                                //滞留
                                Log::info("order_id $order_id delivery hold at " . $status_date);
                            }
                            
                            if (($status_code == "RETD" || $status_code == "CXL") && !$plus_flag) {
                                $update_order                      = Orders::find($id);
                                $update_order->delivery_status     = 3;
                                $update_order->inventory_plus_flag = 1;
                                $update_order->save();
                                Log::info("order_id $order_id delivery  returned at " . $status_date);
                                //TODO  更新sku的当地库存
                                $this->sync_inventory($id, $sku, $location, "RETD", $track_no, $num);
                            }
                        }
                    }
                    
                }
                
            }
        } catch (\GuzzleHttp\Exception\RequestException $e) {
            
            Log::info("url $url response " . $e->getResponse()->getBody()->getContents() . " Location: " . $location);
        }
        
    }
    
    // UPL 减库存  HLD加沙特库存
    public function sync_inventory($id, $sku, $location, $type, $track_no, $quantity)
    {
        
        
        if ($type == "UPL") {
            if ($location == "guangzhou") {
                Products::where('sku', $sku)->update(
                    ['shenzhen_inventory' => DB::raw('shenzhen_inventory-' . $quantity)]
                );
                
                $this->add_inventory_history($sku, "guangzhou", $quantity, 0, $track_no, "fetchr");
                
            }
            else {
                Products::where('sku', $sku)->update(
                    ['saudi_inventory' => DB::raw('saudi_inventory-' . $quantity)]
                );
                $this->add_inventory_history($sku, "saudi", $quantity, 0, $track_no, "fetchr");
            }
            
            $message = sprintf("Id is %d SKU %s in %s reduce %d", $id, $sku, $location, $quantity);
            
            Log::info($message);
            
        }
        
        if ($type == "RETD") {
            Products::where('sku', $sku)->update(
                ['saudi_inventory' => DB::raw('saudi_inventory+' . $quantity)]
            );
            
            $this->add_inventory_history($sku, "saudi", $quantity, 1, $track_no, "fetchr");
            
            $message = sprintf("ID is %d SKU %s in saudi add %d", $id, $sku, $quantity);
            
            Log::info($message);
            
        }
        
    }
    
    public function add_inventory_history($sku, $warehouse, $quantity, $type, $track_no, $delivery_company)
    {
        $model = new InventoryHistory();
        
        $sku_id = Products::where("sku", $sku)->get(['id'])->first()['id'];
        
        $model->username        = "automatic";
        $model->deliver_company = $delivery_company;
        $model->deliver_number  = $track_no;
        $model->quantity        = $quantity;
        $model->type            = $type;
        $model->sku_id          = $sku_id;
        $model->warehouse       = $warehouse;
        
        $model->save();
        
        $op      = $type ? "add" : "reduce";
        $message = sprintf("SKU is %s in warehouse %s %s %d", $sku, $warehouse, $op, $quantity);
        
        Log::info($message);
        
    }
}
