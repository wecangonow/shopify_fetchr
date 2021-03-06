<?php

use Illuminate\Http\Request;
use App\Http\Controllers\OrdersController;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| is assigned the "api" middleware group. Enjoy building your API!
|
*/

Route::middleware('auth:api')->get('/user', function (Request $request) {
    return $request->user();
});


Route::post("orderCreate", 'OrdersController@create');
Route::post("productCreate", 'OrdersController@product_create');
Route::get("sku", 'OrdersController@get_sku_name');
