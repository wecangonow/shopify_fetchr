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

class SyncYokesiOrderJob extends SyncOrderJob
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;
    
    protected $task;
    
    public function __construct(array $task)
    {
        parent::__construct($task);
    }
    
    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle()
    {
        $client        = new Client();
        $url           = "http://z.yokesi.com/webservice/APIWebService.asmx";
        $id            = $this->task['id'];
        $sku           = $this->task['sku'];
        $order_id      = $this->task['order_id'];
        $num           = $this->task['num'];
        $tracking_no   = $this->task['tracking_no'];
        $location      = $this->task['location'];
        $plus_flag     = $this->task['inventory_plus_flag'];
        $reduce_flag   = $this->task['inventory_reduce_flag'];
        
        try {
            $body = <<<BODY
<?xml version="1.0" encoding="utf-8"?>
<soap:Envelope xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance" xmlns:xsd="http://www.w3.org/2001/XMLSchema" xmlns:soap="http://schemas.xmlsoap.org/soap/envelope/">
  <soap:Body>
    <getOrder_Track xmlns="http://tempuri.org/">
      <Orderid>{tracking_no}</Orderid>
    </getOrder_Track>
  </soap:Body>
</soap:Envelope>
BODY;
            $body = str_replace("{tracking_no}", $tracking_no, $body);
            
            $res = $client->request(
                "POST",
                $url,
                [
                    'headers' => [
                        'Content-Type' => "text/xml; charset=utf-8",
                        'SOAPAction'   => "http://tempuri.org/getOrder_Track",
                    ],
                    'body'    => $body,
                ]
            );
            
            if ($res->getStatusCode() == "200") {
                
                $ret = $res->getBody()->getContents();
                
                if ($ret) {
                    $flag = "<getOrder_TrackResult>";
                    
                    $json_string = trim(
                        substr(
                            $ret,
                            strpos($ret, $flag) + strlen($flag),
                            (strpos($ret, "</getOrder_TrackResult>") - strpos($ret, $flag) - strlen($flag))
                        )
                    );
                    
                    $track_arr = json_decode($json_string, true);
                    
                    $track_info = $track_arr['data'];
                    $track_no   = $track_arr['TrackingNo'];
                    
                    if (count($track_info) > 0) {
                        
                        $newest_info_status_name = $track_info[0]['DetailDesc'];
                        $newest_info_date        = $track_info[0]['OccurTime'];
                        
                        $update_order = Orders::find($id);
                        
                        $update_order->last_step      = $newest_info_status_name;
                        $update_order->last_step_time = $newest_info_date;
                        $update_order->save();
                        foreach ($track_info as $info) {
                            $status_code = $info['DetailDesc'];
                            $status_date = date("Y-m-d", strtotime($info['OccurTime']));
                            if ((strpos($status_code, "Create a waybill") !== false) && !$reduce_flag) {
                                $update_order                        = Orders::find($id);
                                $update_order->inventory_reduce_flag = 1;
                                $update_order->save();
                                Log::info("order_id $order_id delivery order created at " . $status_date);
                                //TODO  更新sku的当地库存
                                $this->sync_inventory($id, $sku, $location, "UPL", $track_no, $num);
                            }
                            
                            if (strpos($status_code, "Delivered") !== false) {
                                $update_order                  = Orders::find($id);
                                $update_order->delivery_status = 1;
                                $update_order->save();
                                Log::info("order_id $order_id delivery finished at " . $status_date);
                            }
                            
                            if ($status_code == "HLD") {
                                $update_order                  = Orders::find($id);
                                $update_order->delivery_status = 2;
                                $update_order->save();
                                //滞留
                                Log::info("order_id $order_id delivery hold at " . $status_date);
                            }
                            
                            if ((strpos($status_code, "Returned to Sender") !== false) && !$plus_flag) {
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
    
}
