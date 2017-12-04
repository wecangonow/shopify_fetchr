<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Database\QueryException;
use App\Orders;
use App\Products;

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

    public function product_create(Request $request)
    {
        $data        = $request->getContent();
        $hmac_header = $request->header("X-Shopify-Hmac-Sha256");

        if ($this->verify_webhook($data, $hmac_header)) {
            $product_arr = json_decode($data, true);
            if (isset($product_arr['variants'])) {
                $variants = $product_arr['variants'];
                $image    = isset($product_arr['images']['0']['src']) ? $product_arr['images'][0]['src'] : "";

                if (count($variants) > 0) {
                    foreach ($variants as $item) {
                        $product          = new Products();
                        $product->sku     = $item['sku'];
                        $product->picture = $image;
                        try {

                            $product->save();

                            return "save successfully";

                        } catch (QueryException $e) {

                            return $e->getMessage();
                        }

                    }
                }
            }
        }
        else {
            return "mismatched";
        }
    }
}
