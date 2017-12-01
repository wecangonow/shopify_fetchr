<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use GuzzleHttp\Client;

class SyncOrderJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected $task;

    /**
     * Create a new job instance.
    git init*
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
        $authorization = $this->task['authorization'];
        try {
            $res = $client->request(
                "GET",
                $url,
                ['headers' => ['Authorization' => $authorization]]
            );
            if ($res->getStatusCode() == "200") {

                $res       = $res->getBody()->getContents();
                $track_arr = json_decode($res, true);

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
                    $track_info = [
                        ['status_code' => 'UPL', 'status_date' => '2017-11-27T10:42:41.764027'],
                        ['status_code' => 'PKD', 'status_date' => '2017-11-27T10:42:41.764027'],
                        ['status_code' => 'DLV', 'status_date' => '2017-11-27T10:42:41.764027'],
                        ['status_code' => 'HLD', 'status_date' => '2017-11-27T10:42:41.764027'],
                    ];
                    if (count($track_info) > 0) {
                        foreach ($track_info as $info) {
                            $status_code = $info['status_code'];
                            $status_date = date("Y-m-d", strtotime($info['status_date']));
                            if ($status_code == "UPL" && $this->task['delivery_order_created_at'] == "") {
                                $update_order                            = Orders::find($id);
                                $update_order->delivery_order_created_at = $status_date;
                                $update_order->save();
                                Log::info("order_id $order_id delivery order created at " . $status_date);
                            }
                            if ($status_code == "PKD" && $this->task['picked_status'] == 0) {
                                $update_order                = Orders::find($id);
                                $update_order->picked_status = 1;
                                $update_order->save();
                                Log::info("order_id $order_id delivery order picked at " . $status_date);
                                //TODO  更新sku的当地库存
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
                            }
                        }
                    }

                }

            }
        } catch (\GuzzleHttp\Exception\RequestException $e) {

            Log::info("url $url response " . $e->getResponse()->getBody()->getContents());
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
                Log::info("order_id $order_id" . $response);

                return false;
            }

        } catch (\GuzzleHttp\Exception\ClientException $e) {

            Log::info("order_id $order_id " . $e->getResponse()->getBody()->getContents());

            return false;
        }

    }
}
