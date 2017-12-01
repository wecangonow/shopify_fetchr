<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Database\QueryException;
use App\Orders;

class OrdersController extends Controller
{
    //
    private function verify_webhook($data, $hmac_header)
    {
        $calculated_hmac = base64_encode(hash_hmac('sha256', $data, config('app.shopify_shared_secret'), true));

        return hash_equals($hmac_header, $calculated_hmac);
    }

    public function create(Request $request)
    {
        $data        = $request->getContent();
        $hmac_header = $request->header("X-Shopify-Hmac-Sha256");

        if ($this->verify_webhook($data, $hmac_header)) {
            $order_arr = json_decode($data, true);

            $name            = $order_arr['name'];
            $order_id        = $order_arr['id'];
            $order_full_info = $data;

            $order = new Orders();

            $order->name            = $name;
            $order->order_id        = $order_id;
            $order->order_full_info = $order_full_info;

            try {
                $order->save();

                return "save successfully";

            } catch (QueryException $e) {

                return $e->getMessage();
            }
        }
        else {
            return "mismatched";
        }
    }
}
