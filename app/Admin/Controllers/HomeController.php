<?php

namespace App\Admin\Controllers;

use App\Http\Controllers\Controller;
use Encore\Admin\Facades\Admin;
use Encore\Admin\Layout\Content;
use Illuminate\Http\Request;
use Encore\Admin\Controllers\Dashboard;
use Encore\Admin\Layout\Row;
use Encore\Admin\Layout\Column;


class HomeController extends Controller
{
    public function index(Request $request)
    {
        return Admin::content(function (Content $content) use ($request) {

            $content->header('Dashboard');
            $content->description('Description...');
    
    
            $content->row(Dashboard::title());
            
            $content->row(function (Row $row){
                $row->column(6, function (Column $column){
                   $column->append(Dashboard::environment());
                });
                //$row->column(4, function (Column $column){
                //    $column->append(Dashboard::extensions());
                //});
                $row->column(6, function (Column $column){
                    $column->append(Dashboard::packages());
                });
                
            });
    
        });
    }
}
