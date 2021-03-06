<?php

use Illuminate\Routing\Router;

Admin::registerHelpersRoutes();

Route::group([
    'prefix'        => config('admin.prefix'),
    'namespace'     => Admin::controllerNamespace(),
    'middleware'    => ['web', 'admin'],
], function (Router $router) {

    $router->get('/', 'HomeController@index');

    $router->resource('products', ProductsController::class);
    $router->resource('orders', OrderController::class);
    $router->resource('excel_history', ExcelHistoryController::class);
    $router->resource('inventory_history', InventoryHistoryController::class);
    $router->resource('purchases', PurchasesController::class);


});
