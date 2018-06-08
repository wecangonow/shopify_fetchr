<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateSkuAnalysisTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('sku_analysis', function (Blueprint $table) {
            $table->increments('id');
            $table->string('name');
            $table->string('sku_name');
            $table->string('order_id');
            $table->boolean('fulfillment_status')->default(0);
            $table->boolean('delivery_status')->default(0);
            $table->boolean('hold_status')->default(0);
            $table->boolean('picked_status')->default(0);
            $table->date("delivery_order_created_at");
            $table->date("order_created_at");
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('sku_analysis');
    }
}
