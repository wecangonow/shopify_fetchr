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
        $order_id      = $this->task['order_id'];
        $location      = $this->task['location'];
        $authorization = $this->task['authorization'];

        Log::info("Fetchr url is " . $url);

        try {
            $res = $client->request(
                "GET",
                $url,
                ['headers' => ['Authorization' => $authorization]]
            );

            if ($res->getStatusCode() == "200") {

                $contents  = $res->getBody()->getContents();
                $track_arr = json_decode($contents, true);

                if (isset($track_arr['order_information']) && isset($track_arr['tracking_information'])) {

                    $track_no = $track_arr['order_information']['tracking_no'];

                    if ($track_no != "" && $this->task['fulfillment_status'] == 0) {
                        if ($this->fulfillment_request($track_no, $order_id)) {
                            //更新fulfillment status
                            $update_order                     = Orders::find($this->task['id']);
                            $update_order->fulfillment_status = 1;
                            $update_order->tracking_no        = $track_no;
                            if ($update_order->save()) {
                                Log::info("order_id $order_id fulfillment successfully");
                            }
                        }
                    }

                    $track_info = $track_arr['tracking_information'];

                    //$track_info = [
                    //    ['status_code' => 'UPL', 'status_date' => '2017-11-27T10:42:41.764027'],
                    //    ['status_code' => 'PKD', 'status_date' => '2017-11-27T10:42:41.764027'],
                    //    ['status_code' => 'DLV', 'status_date' => '2017-11-27T10:42:41.764027'],
                    //    ['status_code' => 'HLD', 'status_date' => '2017-11-27T10:42:41.764027'],
                    //];
                    if (count($track_info) > 0) {
                        foreach ($track_info as $info) {
                            $status_code = $info['status_code'];
                            $status_date = date("Y-m-d", strtotime($info['status_date']));
                            if ($status_code == "UPL" && $this->task['delivery_order_created_at'] == "") {
                                $update_order                            = Orders::find($id);
                                $update_order->delivery_order_created_at = $status_date;
                                $update_order->save();
                                Log::info("order_id $order_id delivery order created at " . $status_date);
                                //TODO  更新sku的当地库存
                                $this->sync_inventory($id, $location, "UPL", $track_no);
                            }
                            if ($status_code == "PKD" && $this->task['picked_status'] == 0) {
                                $update_order = Orders::find($id);
                                Log::info("order_id $order_id delivery order picked at " . $status_date);
                                $update_order->picked_status = 1;
                                $update_order->save();
                                Log::info("order_id $order_id delivery order picked at " . $status_date);
                            }
                            if ($status_code == "DLV") {
                                $update_order                  = Orders::find($id);
                                $update_order->delivery_status = 1;
                                $update_order->save();
                                Log::info("order_id $order_id delivery finished at " . $status_date);
                            }

                            if ($status_code == "HLD") {
                                $update_order              = Orders::find($id);
                                $update_order->hold_status = 1;
                                $update_order->save();
                                Log::info("order_id $order_id delivery on hold at " . $status_date);
                                //TODO  更新sku的当地库存
                                $this->sync_inventory($id, $location, "HLD", $track_no);
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
    public function sync_inventory($id, $location, $type, $track_no)
    {

        $order_full_info = @Orders::where("id", $id)->get(['order_full_info'])[0]['order_full_info'];

        $line_items = @json_decode($order_full_info, true)['line_items'];

        if (count($line_items) > 0) {
            foreach ($line_items as $item) {
                $sku      = $item['sku'];
                $quantity = $item['quantity'];

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

                if ($type == "HLD") {
                    Products::where('sku', $sku)->update(
                        ['saudi_inventory' => DB::raw('saudi_inventory+' . $quantity)]
                    );

                    $this->add_inventory_history($sku, "saudi", $quantity, 1, $track_no, "fetchr");

                    $message = sprintf("ID is %d SKU %s in saudi add %d", $id, $sku, $quantity);

                    Log::info($message);

                }

            }
        }
    }

    public function fulfillment_request($track_no, $order_id)
    {
        $client        = new Client();
        $authorization = "Basic " . base64_encode(
                config('app.shoipfy_app_key') . ":" . config('app.shopify_app_password')
            );
        $post_arr      = [
            'fulfillment' => [
                'tracking_number'  => $track_no,
                'tracking_url'     => config('app.fetchr_tracking_url'),
                'tracking_company' => 'fetchr',
                'notify_customer'  => true,
            ],
        ];

        $post_url = config('app.shopify_api_basic_url') . '/orders/' . $order_id . '/fulfillments.json';

        try {
            $res = $client->request(
                "POST",
                $post_url,
                ['headers' => ['Authorization' => $authorization], 'json' => $post_arr]
            );

            $response    = $res->getBody()->getContents();
            $status_code = $res->getStatusCode();

            if ($status_code == "201") {
                return true;
            }
            else {
                Log::info("order_id $order_id  status code is $status_code" . $response);

                return false;
            }

        } catch (\GuzzleHttp\Exception\ClientException $e) {

            Log::info("order_id $order_id " . $e->getResponse()->getBody()->getContents());

            return false;
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
