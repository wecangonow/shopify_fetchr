<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Mockery\CountValidator\Exception;
use GuzzleHttp\Client;
use Illuminate\Support\Facades\Log;
use App\Products;

class SyncSku extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'sync:sku';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'sync shopify sku';

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
        //
        $client        = new Client();
        $authorization = "Basic " . base64_encode(
                config('app.shoipfy_app_key') . ":" . config('app.shopify_app_password')
            );

        $get_url = config('app.shopify_api_basic_url') . '/products.json?fields=id,images,variants';

        try {
            $res = $client->request(
                "GET",
                $get_url,
                ['headers' => ['Authorization' => $authorization]]
            );

            $response    = $res->getBody()->getContents();
            $status_code = $res->getStatusCode();

            if ($status_code == "200") {
                $products = json_decode($response, true)['products'];
                if (count($products) > 0) {

                    foreach ($products as $product) {
                        if (isset($product['variants'])) {
                            $variants = $product['variants'];
                            $image    = isset($product['images']['0']['src']) ? $product['images'][0]['src'] : "";

                            if (count($variants) > 0) {
                                foreach ($variants as $item) {

                                    if(!Products::where("sku", $item['sku'])->get(['sku'])->first()['sku']) {
                                        $product          = new Products();
                                        $product->sku     = $item['sku'];
                                        $product->picture = $image;
                                        try {
                                            $product->save();
                                            Log::info("add a sku name is : " . $item['sku']);

                                        } catch (QueryException $e) {

                                            return $e->getMessage();
                                        }
                                    }
                                }
                            }
                        }
                    }
                }
            }


        } catch (Exception $e) {

        }
    }
}
