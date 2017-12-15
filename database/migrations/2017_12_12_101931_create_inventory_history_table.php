<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateInventoryHistoryTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('inventory_history', function (Blueprint $table) {
            $table->increments('id');
            $table->string("username")->default("");
            $table->string("deliver_company")->default("");
            $table->string("deliver_number")->default("");
            $table->integer("quantity")->default(0);
            $table->tinyInteger("type")->default(0);
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
        Schema::dropIfExists('inventory_hisotry');
    }
}
