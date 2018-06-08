<?php

namespace App;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

class Orders extends Model
{
    //
    
    public function allSkuSignInRate()
    {
    
    }
    
    public function totalDeliveryStat()
    {
        $info = DB::table("orders")->select("delivery_status", DB::raw('count(*) as total'))->groupBy("delivery_status")->pluck('total', 'delivery_status')->all();
        
        return $info;
    }
}
