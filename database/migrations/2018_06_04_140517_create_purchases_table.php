<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreatePurchasesTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('purchases', function (Blueprint $table) {
            $table->increments('id');
            $table->string('sku')->default('""');
            $table->integer('count')->comment('采购数量');
            $table->string('delivery_company')->default('“”')->comment('物流公司');
            $table->string('delivery_no')->default('""')->comment('快递号');
            $table->string('publisher')->default('""')->comment('录入人');
            $table->tinyInteger('is_delivered')->comment('是否到货');
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
        Schema::dropIfExists('purchases');
    }
}
